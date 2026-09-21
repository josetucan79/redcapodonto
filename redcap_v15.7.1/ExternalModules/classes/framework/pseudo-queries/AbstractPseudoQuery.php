<?php namespace ExternalModules;

use Exception;
use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;
use PHPSQLParser\utils\PHPSQLParserConstants;

abstract class AbstractPseudoQuery{
	// static $SQL_RESERVED_WORDS;

	private $framework;

	function __construct($framework){
		$this->framework = $framework;
	}

	// private static function getSQLReservedWords(){
	// 	if(!isset(static::$SQL_RESERVED_WORDS)){
	// 		$instance = PHPSQLParserConstants::getInstance();
	// 		$class = new \ReflectionClass(get_class($instance));
	// 		$reserved = $class->getProperty('reserved');
	// 		$reserved->setAccessible(true);
			
	// 		static::$SQL_RESERVED_WORDS = $reserved->getValue($instance);
	// 	}

	// 	return static::$SQL_RESERVED_WORDS;
	// }

	// private static function isSQLReservedWord($string){
	// 	return isset(static::getSqlReservedWords()[strtoupper($string)]);
	// }

	function getFramework(){
		return $this->framework;
	}

    /**
     * @return string
     */
    function getNonAliasFieldName($field){
        return $this->getTable() . ".$field";
    }

    /**
     * @return string
     *
     * @param bool $forSelect
     */
    function getAliasFieldName($field, $forSelect = false){
        return "$field.value";
	}
	
	/**
	 * @return array
	 *
	 * @param string $sql
	 */
	function parse($sql){
		$parser = new PHPSQLParser();
		return $parser->parse($sql);
	}

	/**
	 * @return void
	 */
	private function checkForDisallowedSQL($items){
		if($items === false){
			return;
		}

		foreach($items as $item){
			$type = $item['expr_type'];
			if(!in_array($type, [
				'const',
				'operator',
				'colref',
				'reserved',
				'expression',
				'function',
				'aggregate_function',
				'bracket_expression',
				'in-list'
			])){
				throw new Exception(ExternalModules::tt('em_errors_152', $type, $item['base_expr']));
			}

			$this->checkForDisallowedSQL($item['sub_tree']);
		}
	}

	/**
	 * @return void
	 */
	private function checkForDisallowedSQLInLimit($items){
		foreach($items as $name=>$value){
			if(!in_array($name, ['offset', 'rowcount'])){
				throw new Exception('Invalid LIMIT clause ' . $name);
			}

			if($value !== '' && !ctype_digit($value)){
				throw new Exception(ExternalModules::tt('em_errors_146', $value));
			}
		}
	}

	/**
	 * @param array $whereFields
	 */
	function addStandardWhereClauses($parsedWhere, $whereFields){
		return $parsedWhere;
	}

	/**
	 * @return string
	 *
	 * @param string $sql
	 */
	function getActualSQL($sql){
		$table = $this->getTable();

		$parsed = $this->parse($sql);

		if(!isset($parsed['SELECT']) || !isset($parsed['SELECT'][0]['expr_type'])){
			//= Queries must start with a 'select' statement:
			throw new Exception(ExternalModules::tt('em_errors_151') . " $sql");
		}

		/**
		 * We use inclusive rather than exclusive logic on what SQL is allowed
		 * to minimize the risk of SQL injection vulnerabilities.
		 */
		foreach($parsed as $section=>$items){
			if(!in_array($section, ['SELECT', 'WHERE', 'GROUP', 'ORDER', 'LIMIT'])){
				throw new Exception(ExternalModules::tt('em_errors_145', $section));
			}

			if($section === 'LIMIT'){
				$this->checkForDisallowedSQLInLimit($items);
			}
			else{
				$this->checkForDisallowedSQL($items);
			}
		}

		$selectFields = [];
		$whereFields = [];
		$orderByFields = [];
		$groupByFields = [];
		$this->processPseudoQuery($parsed['SELECT'], $selectFields, true);
		$this->processPseudoQuery($parsed['WHERE'], $whereFields, false);
		$this->processPseudoQuery($parsed['ORDER'], $orderByFields, false);
		$this->processPseudoQuery($parsed['GROUP'], $groupByFields, false);
		$fields = array_merge($selectFields, $whereFields, $orderByFields, $groupByFields);

		if($this->isDataQuery()){
			/**
			 * Queries must be distinct for data queries because REDCap stores
			 * duplicate rows in redcap_data for some reason.
			 * This causes duplicate joins are 
			 */
			array_unshift($parsed['SELECT'], [
				"expr_type" => "reserved",
				"alias" => false,
				"base_expr" => "DISTINCT",
				"sub_tree" => false,
				"delim" => " "
			]);
		}
		
		$parsed['WHERE'] = $this->addStandardWhereClauses($parsed['WHERE'], $whereFields);

		$fields = array_unique($fields);
		$joinUsername = false;
		$parameterFields = [];
		$repeatingForm = null;
		$excludeMissingInstancesClauses = [];
		$repeatInstanceSql = "COALESCE(";
		foreach ($fields as $field) {
			if ($this->isLogQuery() && $field == 'username') {
				$joinUsername = true;
			} else if ($this->isJoinRequired($field)) {
				$parameterFields[] = $field;
			}

			if($this->isDataQuery()){
				$form = $this->getRepeatingForm($field);
				if($form != null){
					if($repeatingForm === null){
						$repeatingForm = $form;
					}
					else if($repeatingForm !== $form){
						throw new Exception(ExternalModules::tt('em_errors_143', $sql));	
					}

					$excludeMissingInstancesClauses[] = "$field.record is not null"; // We could check any column here except `value` to determine if the join succeeded.
					$repeatInstanceSql .= "$field.instance, ";
				}
			}
		}

		$repeatInstanceSql .= "'')";

		$from = ' from ';

		if($this->isDataQuery()){
			$recordIdFieldName = db_escape($this->getProject()->getRecordIdField());
			$pid = $this->getProject()->getProjectId(); // No escaping necessary because it's guaranteed to be an integer already.

			// We originally had a simple select against the redcap_data table here (more like LogPseudoQuery)
			// This was causing a lot of unnecessary joins because the record id row is duplicate a lot in redcap_data (not sure why).
			// This was slowing down queries significantly, so we switched to the "group by" join below which should be fast enough
			// in the case of complex queries.
			// The only other option would likely be to modify REDCap core to clean up those duplicate values.
			$from .= "(
				select project_id, record
				from $table
				where project_id = $pid 
				and field_name = '$recordIdFieldName'
				and instance is null
				group by project_id, record
			) as $table";

			$repeatInstrumentSelects = [
				"select '' as value"
			];

			$selectHasRepeatingFields = false;
			if($repeatingForm !== null){
				$repeatingForm = db_escape($repeatingForm);

				$hasRepeatingFieldsInWhereClause = false;
				$firstWhereFieldIsRepeating = null;
				foreach($whereFields as $whereField){
					if($whereField === $recordIdFieldName){
						continue;
					}

					$form = $this->getRepeatingForm($whereField);
					if($form){
						$hasRepeatingFieldsInWhereClause = true;
					}

					if($firstWhereFieldIsRepeating === null){
						$firstWhereFieldIsRepeating = $form !== null;
					}
				}

				$selectHasNonRepeatingFieldsOtherThanRecordId = false;
				foreach($selectFields as $selectField){
					if($selectField === $recordIdFieldName && $hasRepeatingFieldsInWhereClause){
						continue;
					}

					$repeatingFormForSelectField = $this->getRepeatingForm($selectField);
					if($repeatingFormForSelectField === null){
						$selectHasNonRepeatingFieldsOtherThanRecordId = true;
					}
					else{
						$selectHasRepeatingFields = true;
					}
				}

				if(
					$selectHasRepeatingFields
					||
					!$selectHasNonRepeatingFieldsOtherThanRecordId // Record ID must be the only select field.
				){
					$repeatInstrumentSelects[] = "select '$repeatingForm' as value";

					if(
						(
							$selectHasRepeatingFields // Record ID must be the only select field.
							&&
							(
								!$selectHasNonRepeatingFieldsOtherThanRecordId
								||
								$firstWhereFieldIsRepeating
							)
						)
					){
						// Remove the select statement for non-repeating fields.
						array_shift($repeatInstrumentSelects);
					}
				}

				$this->modifyParsedSQL($parsed, 'WHERE', function($where) use ($excludeMissingInstancesClauses, $repeatInstanceSql){
					$excludeMissingInstancesSql = "
						(redcap_repeat_instrument.value = '' and $repeatInstanceSql = '')
						or
						(redcap_repeat_instrument.value != '' and (" . implode(' or ', $excludeMissingInstancesClauses) . ")
					";
					
					if(empty($where)){
						$where = "WHERE $excludeMissingInstancesSql";
					}
					else{
						$where = preg_replace('/WHERE/', 'WHERE (', $where, 1);
						$where .= ") and ($excludeMissingInstancesSql)";
					}

					return $where;
				});
			}

			$from .= "					
				left join (
					" . implode(' union ', $repeatInstrumentSelects) . "
				) as redcap_repeat_instrument on true
			";

			// Tricky 'order by' to cover both numeric & non-numeric record IDs.
			$defaultOrderBySql = "order by $table.record + 0, $table.record";
			
			$repeatingForms = $this->getProject()->getRepeatingForms();
			if(!empty($repeatingForms)){
				if(
					in_array($recordIdFieldName, $selectFields)
					||
					$selectHasRepeatingFields
				){
					$defaultOrderBySql .= ",
						redcap_repeat_instrument.value,
						cast($repeatInstanceSql as unsigned)
					";
				}

				if(in_array($recordIdFieldName, $selectFields)){
					$search = "as $recordIdFieldName";
					$replace = "
						$search,
						redcap_repeat_instrument.value as redcap_repeat_instrument,
						if(
							redcap_repeat_instrument.value = '',
							'',
							if(
								$repeatInstanceSql = '',
								1,
								$repeatInstanceSql
							)
						) as redcap_repeat_instance
					";

					// The following didn't cover all cases.  The REDCap::getData() implementation is a little quirky here.
					// We just excluded form completion values from unit tests for now.
					// if($selectFields === [$recordIdFieldName]){
					// 	foreach($repeatingForms as $form){
					// 		$replace .= ", '' as {$form}_complete";
					// 	}
					// }

					$this->modifyParsedSQL($parsed, 'SELECT', function($select) use ($search, $replace){
						return preg_replace("/$search/", $replace, $select, 1);
					});
				}
			}

			$this->modifyParsedSQL($parsed, 'ORDER', function($order) use ($defaultOrderBySql){
				if(!empty($order)){
					return $order;
				}
				
				return $defaultOrderBySql;
			});
		}
		else{
			$from .= $table;
		}

		foreach ($parameterFields as $field) {
			// The invalid character check below should be enough, but lets escape too just to be safe.
			$field = db_escape($field);

			// Needed for field names with spaces.
			$fieldString = str_replace("`", "", $field);
			
			// Prevent SQL injection.
			ExternalModules::checkForInvalidLogParameterNameCharacters($fieldString);

			$from .= $this->getJoinSQL($field, $fieldString);
		}

		if ($joinUsername) {
			$from .= "
						left join redcap_user_information on redcap_user_information.ui_id = redcap_external_modules_log.ui_id
					";
		}

		$fromPlaceholder = 'FROM some_fake_table_that_does_not_exist';
		$this->modifyParsedSQL($parsed, 'FROM', function($from) use ($fromPlaceholder){
			return $fromPlaceholder;
		});

		$creator = new PHPSQLCreator();
		$sql = $creator->create($parsed);
		$sql = str_replace($fromPlaceholder, $from, $sql);

		return $sql;
	}

	/**
	 * @return void
	 *
	 * @param array $parsed
	 * @param string $clause
	 * @param \Closure $action
	 */
	function modifyParsedSQL(&$parsed, $clause, $action){
		$creator = new PHPSQLCreator();

		if($clause === 'SELECT'){
			$dummySQL = '';
			$clauseSQL = $creator->create(['SELECT' => $parsed['SELECT']]);
		}
		else{
			$dummySQL = 'select 1';
			$dummyParsed = $this->parse($dummySQL);

			$clauseSQL = $creator->create(['SELECT' => $dummyParsed['SELECT'], $clause => $parsed[$clause] ?? null]);
			$clauseSQL = substr($clauseSQL, strlen($dummySQL));
		}

		$clauseSQL = $action($clauseSQL);
		$clauseParsed = $this->parse("$dummySQL $clauseSQL");
		$parsed[$clause] = $clauseParsed[$clause];
	}

	function getRepeatingForm($field){
		return null;
	}

	/**
	 * @return void
	 *
	 * @param array $fields
	 * @param bool $addAs
	 * @param array $parsed
	 */
	private function processPseudoQuery(&$parsed, &$fields, $addAs, $parentItem = null)
	{
		if($parsed === null){
			return;
		}

		for ($i = 0; $i < count($parsed); $i++) {
			$item =& $parsed[$i];
			$subtree =& $item['sub_tree'];

			if (is_array($subtree)) {
				$baseExpr = strtolower($item['base_expr']);
				if($item['expr_type'] === 'function' && $baseExpr === 'datediff' && count($subtree) > 2){
					$this->processPseudoQuery($subtree, $fields, false, $item);

					// This is a REDCap datediff() call (as opposed to a MySQL datediff() call).
					$this->convertDateDiff($item);
				}
				else if($item['expr_type'] === 'function' && in_array($baseExpr, ['contains', '!contains'])){
					$this->processPseudoQuery($subtree, $fields, false, $item);
					$item['base_expr'] = str_replace('contains', 'locate', $baseExpr);
					$item['sub_tree'] = array_reverse($item['sub_tree']);
				}
				else{
					$this->processPseudoQuery($subtree, $fields, $addAs, $item);
				}
			} else if ($item['expr_type'] == 'colref'){
				if($item['base_expr'] === '*'){
					if(strtolower($parentItem['base_expr'] ?? '') !== 'count'){
						throw new Exception("Log queries do not currently '*' for selecting column names.  Columns must be explicitly defined in all log queries.");
					}
				}
				else{
					$field = $item['base_expr'];
					if(
						// static::isSQLReservedWord($field)
						// ||
						in_array($field, ['?', 'true', 'false'])
					){
						continue;
					}

					$field = $this->formatColumnFieldName($field);

					$fields[] = $field;

					if ($field === 'username') {
						/**
						 * Always return lowercase usernames so that testLogAndQueryLog() succeeds on usernames with capital letters,
						 * also for consistency with how REDCap treats such names.
						 */
						$newField = 'lower(redcap_user_information.username)';
						if ($addAs && ($item['alias'] ?? null) === false) {
							$newField .= " as $field";
						}
					} else if(!$this->isAliasRequired($field)) {
						$newField = $this->getNonAliasFieldName($field);
					} else {
						$forTopLevelSelect = $addAs && ($item['alias'] ?? null) === false;
						$newField = $this->getAliasFieldName($field, $forTopLevelSelect);

						if ($forTopLevelSelect) {
							$newField .= " as $field";
						}
					}

					$item['base_expr'] = $newField;
				}
			}
		}
	}

	/**
	 * @return void
	 */
	private function convertDateDiff(&$item){
		/**
		 * MySQL's TIMESTAMPDIFF() does not support fractions like REDCap's datediff() does.
		 * We create them by performing calculations for smaller units.
		 * In the case of years & months, we use leap year adjusted second based calculations,
		 * like REDCap's datediff().
		 */
		$map = [
			'y' => [
				'unit' => 'SECOND',
				'denominator' => 31556952
			],
			'M' => [
				'unit' => 'SECOND',
				'denominator' => 2630016
			],
			'd' => [
				'unit' => 'HOUR',
				'denominator' => 24
			],
			'h' => [
				'unit' => 'MINUTE',
				'denominator' => 60
			],
			'm' => [
				'unit' => 'SECOND',
				'denominator' => 60
			],
			's' => [
				'unit' => 'MICROSECOND',
				'denominator' => 1000000
			],
		];

		$getString = function($item): string{
			$s = $item['base_expr'] ?? '';
			$s = str_replace('"', '', $s);
			$s = str_replace('\'', '', $s);
			
			return $s;
		};

		$getDate = function($item) use($getString){
			if($getString($item) === 'now'){
				$item['expr_type'] = 'function';
				$item['base_expr'] = 'NOW';
			}
			else if($getString($item) === 'today'){
				$item['expr_type'] = 'function';
				$item['base_expr'] = 'CURDATE';
			}

			return $item;
		};
		
		$item['expr_type'] = 'bracket_expression';
		$item['alias'] = false;
		$item['delim'] = false;
		$item['base_expr'] = "
			This value is just for display and is not used to generate the SQL.
			We replace it here anyway for clarity since the original value
			will no longer be correct.
		";

		$oldSubTree = $item['sub_tree'];
		$type = $getString($oldSubTree[2]);
		$signed = $getString($oldSubTree[3] ?? null) === 'true';
		$unit = $map[$type]['unit'];
		$denominator = $map[$type]['denominator'];
		
		$newSubTree = [
			[
				"expr_type" => "function",
				"base_expr" => 'TIMESTAMPDIFF',
				"sub_tree" => [
					[
						"expr_type" => "colref",
						"base_expr" => $unit,
						"no_quotes" => [
							"delim" => false,
							"parts" => [
								$unit
							]
						],
						"sub_tree" => false
					],
					$getDate($oldSubTree[0]),
					$getDate($oldSubTree[1])
				]
			]
		];

		if(!$signed){
			$newSubTree = [
				[
					"expr_type" => "function",
					"base_expr" => 'ABS',
					"sub_tree" => $newSubTree,
				]
			];
		}

		$newSubTree[] = [
			'expr_type' => 'operator',
			'base_expr' => '/',
			'sub_tree' => false
		];

		$newSubTree[] = [
			'expr_type' => 'const',
			'base_expr' => (string) $denominator,
			'sub_tree' => false
		];

		$item['sub_tree'] = $newSubTree;
	}

	/**
	 * @return bool
	 */
	function isLogQuery(){
		return get_class($this) === LogPseudoQuery::class;
	}

	/**
	 * @return bool
	 */
	function isDataQuery(){
		return get_class($this) === DataPseudoQuery::class;
	}

	abstract function getJoinSQL($field, $fieldString);
	abstract function isAliasRequired($field);
	abstract function formatColumnFieldName($field);
}