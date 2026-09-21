<?php



/**
 * SQLTableCheck
 * This class provides methods for evaluating tables or parts of tables that are missing
 * from among REDCap's MySQL tables, and provides output to fix those tables.
 */
class SQLTableCheck
{

	// Constructor
	public function __construct()
	{
		// Make sure sql_mode isn't set to ANSI_QUOTES
		db_query("SET SQL_MODE=TRADITIONAL");
	}

	/**
	 * OBTAIN "CREATE TABLE" STATEMENT FOR ALL REDCAP TABLES
	 * Return array with table name as array key.
	 */
	public function get_create_table_for_all_tables()
	{
		$tables = array();
		// Get CREATE TABLE statement of all "redcap_" tables
		$sql = "show tables like 'redcap\_%'";
		$q = db_query($sql);
		while ($row = db_fetch_array($q)) {
			$q2 = db_query("show create table `{$row[0]}`");
			$row2 = db_fetch_assoc($q2);
			$tables[$row2['Table']] = $row2['Create Table'];
		}
		// Sort tables alphabetically for consistency
		ksort($tables);
		// Return tables
		return $tables;
	}


	/**
	 * Determine if we're using utf8mb4 encoding or utf8 in the tables
	 */
	public static function using_utf8mb4()
	{
		$sql = "show create table redcap_config";
		$q = db_query($sql);
		$row = db_fetch_assoc($q);
		return (strpos($row['Create Table'], 'utf8mb4_unicode_ci') !== false);
	}


	/**
	 * BUILD ARRAY OF CURRENT TABLE STRUCTURE AND DO A DIFF OF IT WITH INSTALL.SQL AND INSTALL_DATA.SQL
	 */
	public function build_table_fixes($autoFix=false, $forceConvertMb4=false)
	{
		// Auto fix it?
		if ($autoFix) {
			$autoFixSuccess = Upgrade::autoFixTables();
			if ($autoFixSuccess !== false) return '';
		}
		## PARSE INSTALL.SQL
		// Obtain install.sql from Resources/sql/ directory
		$install_sql = file_get_contents(APP_PATH_DOCROOT . "Resources/sql/install.sql");
		// Replace all \r\n with just \n for compatibility
		$install_sql = str_replace("\r\n", "\n", $install_sql);
		// Obtain a version of install.sql from current table structure
		$this_install_sql = $this->build_install_file_from_tables($forceConvertMb4);
        // Remove any /* mariadb-5.3 */ inline comments for datetime and time fields
        $this_install_sql = preg_replace('/\s?\/\*.*?\*\//s', '', $this_install_sql);
        // Vanderbilt-specific: Replace special PRIMARY key on redcap_log_event
        $this_install_sql = str_replace('PRIMARY KEY (`log_event_id`,`project_id`)', 'PRIMARY KEY (`log_event_id`)', $this_install_sql);
		// Array for placing the table differences
		$diff_tables = $diff_fks = array();
		// Parse the install SQL files into an array with table attributes
		$install_tables = $this->parse_install_sql($install_sql);
		$this_install_tables = $this->parse_install_sql($this_install_sql);
		// Loop through install.sql array and note anything missing or different from it
		foreach ($install_tables as $table=>$attr) {
			// If table is missing
			if (!isset($this_install_tables[$table])) {
				$diff_tables[] = $attr['create_table'];
				if (isset($attr['create_table_fks']) && $attr['create_table_fks'] != '') {
					$diff_fks[] = $attr['create_table_fks'];
				}
				// Go to next loop since there's nothing else to do here
				continue;
			}
			// Check all fields
			if ($attr['fields'] !== $this_install_tables[$table]['fields']) {
				// Get table-level collation to use when column-level collation is missing from SHOW CREATE TABLE output
				// (affects MariaDB versions 10.3.37+, 10.4.27+, 10.5.18+, 10.6.11+, 10.7.7+, 10.8.6+, 10.9.4+, 10.10.2+, 10.11.0+)
				$string = $this_install_tables[$table]['create_table']; // last line of SHOW CREATE TABLE output
				// example: ") ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci";
				if (preg_match('/COLLATE=(\w+)/', $string, $collation_match) === 1) {
					// Replace utf8mb3 with utf8 for legacy compatibility
					$collation = str_replace("utf8mb3", "utf8", $collation_match[1]);
					// Should consider spelling out utf8 as utf8mb3 soon, as the utf8_* naming is gradually being deprecated for utf8mb3_*.
				} else {
					$collation = false;
				}
				// Loop through fields
				$prev_field = null;
				foreach ($attr['fields'] as $field=>$line) {
                    // Replace duplicate collation
                    $line = str_replace(" COLLATE utf8_unicode_ci COLLATE utf8_unicode_ci", " COLLATE utf8_unicode_ci", $line);
                    $line = str_replace(" COLLATE utf8mb3_unicode_ci COLLATE utf8mb3_unicode_ci", " COLLATE utf8mb3_unicode_ci", $line);
                    $line = str_replace(" COLLATE utf8mb4_unicode_ci COLLATE utf8mb4_unicode_ci", " COLLATE utf8mb4_unicode_ci", $line);
					// If field is missing
					if (!isset($this_install_tables[$table]['fields'][$field])) {
						$diff_tables[] = "ALTER TABLE `$table` ADD $line" . ($prev_field == null ? "" : " AFTER `$prev_field`;");
					}
					// If field is different
					elseif ($line != $this_install_tables[$table]['fields'][$field]) {

						// For MariaDB versions released after Sep 2022
						// ...Add table-level collation (charset implied) to each column definition if collation isn't given in SHOW CREATE TABLE output						
						// Regex tested here -- https://regex101.com/r/7kyOla/6
 						if ((strpos($this_install_tables[$table]['fields'][$field]," COLLATE ") === false) && !empty($collation)) { 
							$collFind = "/(`$field` (enum\(.+\) |(var|)char\(\d{1,4}\) |(long|medium|tiny|)text |set\(.+\) ))/";
							$collRepl = "$1COLLATE $collation ";
							$this_install_tables[$table]['fields'][$field] = preg_replace($collFind, $collRepl, $this_install_tables[$table]['fields'][$field]);
                        }
						
						// MySQL 8 will return " int " instead of " int(X) " for all integer types, so we'll have to insert the length "(X)" manually for conformity with install.sql
						$numeric_types = array("tinyint", "smallint", "mediumint", "bigint", "int");
						foreach ($numeric_types as $numericType) {
							// Search for "int ", which should only show up in MySQL 8 (other versions would have "int(" instead)
							$thisSearch = "`$field` $numericType ";
							if (strpos($this_install_tables[$table]['fields'][$field], $thisSearch) !== false) {
								// Insert the int length manually into the "show create table" results by pulling it from install.sql
								list ($thisReplace, $nothing) = explode(") ", $line, 2);
								$thisReplace .= ") "; // Append ending to use as full replacement
								$this_install_tables[$table]['fields'][$field] = str_replace($thisSearch, $thisReplace, $this_install_tables[$table]['fields'][$field]);
							}
						}
						// check again...
						if ($this_install_tables[$table]['fields'][$field] != $line) {
							$diff_tables[] = "ALTER TABLE `$table` CHANGE `$field` $line;";
						}
					}
					// Set for next loop
					$prev_field = $field;
				}
			}
			// Check primary key
			if ($attr['pk'] != $this_install_tables[$table]['pk']) {
				// If primary key is missing
				if ($this_install_tables[$table]['pk'] == '' && $attr['pk'] != '') {
					$diff_tables[] = "ALTER TABLE `$table` ADD {$attr['pk']};";
				}
				// If primary key is different
				else {
					// In case this table's PK is used as an FK in another table, check if so, and generate DROP FOREIGN KEY and then ADD FOREIGN KEY for it
					$addFKs = [];
					list ($nothing, $formattedPK) = explode("(", $attr['pk'], 2);
					$thesePKs = (strpos($formattedPK, ",") !== false) ? explode(",", $formattedPK) : [$formattedPK];
					foreach ($thesePKs as &$thisPK) {
						$thisPK = substr($thisPK, 1);
						$thisPK = '"'.substr($thisPK, 0, strpos($thisPK, "`")).'"';
					}
					$sql = "select concat(\"ALTER TABLE `$table` DROP FOREIGN KEY \", constraint_name, \";\") as dropsql, constraint_name
							from information_schema.KEY_COLUMN_USAGE 
							where CONSTRAINT_SCHEMA = '{$GLOBALS['db']}' and TABLE_NAME = '$table' 
							and referenced_column_name is not null and COLUMN_NAME IN (".implode(", ", $thesePKs).")";
					$q = db_query($sql);
					if ($q) {
						$removeFKquery = db_result($q, 0, "dropsql");
						$fkName = db_result($q, 0, "constraint_name");
						if ($removeFKquery != '') {
							// Add drop FK to array
							$diff_tables[] = $removeFKquery;
							// Obtain the line of the FK definition from "show create table" so we know how to re-add it afterward
							$sql = "show create table `$table`";
							$q = db_query($sql);
							if ($q) {
								$createTable = explode("\n", db_fetch_assoc($q)['Create Table']);
								foreach ($createTable as $thisline) {
									$thisline = trim($thisline);
									if (strpos($thisline, "CONSTRAINT `$fkName`") === 0) {
										$thisline = trim(str_replace("CONSTRAINT `$fkName`", "", $thisline));
										$addFKs[] = "ALTER TABLE `$table` ADD ".$thisline.";";
										break;
									}
								}
							}
						}
					}
                    // If table has an extra, unexpected primary key, then ignore it for institutions that have been
                    // told by their IT folks that they need to add PKs to all tables for clustering/replication purposes.
                    $dropPK = true;
                    if ($this_install_tables[$table]['pk'] != '') {
                        // Get name of PK in current db table, if any
                        $pk_field = str_replace(["PRIMARY KEY"," ","(",")","`"], [""], $this_install_tables[$table]['pk']);
                        // If PK is an extra field, do not drop it
                        $dropPK = !($pk_field != '' && isset($this_install_tables[$table]['fields'][$pk_field]) && !isset($attr['fields'][$pk_field]));
                    }
                    // Drop and add PK
                    if ($dropPK) {
                        $diff_tables[] = "ALTER TABLE `$table` DROP INDEX `PRIMARY`;";
                        if ($attr['pk'] != '') {
                            $diff_tables[] = "ALTER TABLE `$table` ADD {$attr['pk']};";
                        }
                    }
					// Re-add FKs
					if (!empty($addFKs)) {
						$diff_tables = array_merge($diff_tables, $addFKs);
					}
				}
			}
			// Check unique keys
			if ($attr['uks'] !== $this_install_tables[$table]['uks']) {
				// Loop through uks
				foreach ($attr['uks'] as $key=>$line) {
					// If key is missing
					if (!isset($this_install_tables[$table]['uks'][$key])) {
						// If key already exists as a normal key, then drop the key first
						if (isset($this_install_tables[$table]['keys'][$key])) {
							$diff_tables[] = "ALTER TABLE `$table` DROP INDEX `$key`;";
						}
						$diff_tables[] = "ALTER TABLE `$table` ADD $line;";
					}
					// If key is different
					elseif ($line != $this_install_tables[$table]['uks'][$key]) {
						$diff_tables[] = "ALTER TABLE `$table` DROP INDEX `$key`;";
						$diff_tables[] = "ALTER TABLE `$table` ADD $line;";
					}
				}
			}
			// Check keys
			if ($attr['keys'] !== $this_install_tables[$table]['keys']) {
				// Loop through uks
				foreach ($attr['keys'] as $key=>$line) {
					// If key is missing
					if (!isset($this_install_tables[$table]['keys'][$key])) {
						// If key already exists as a unique key, then drop the unique key first
						if (isset($this_install_tables[$table]['uks'][$key])) {
							$diff_tables[] = "ALTER TABLE `$table` DROP INDEX `$key`;";
						}
						$diff_tables[] = "ALTER TABLE `$table` ADD $line;";
					}
					// If key is different
					elseif ($line != $this_install_tables[$table]['keys'][$key]) {
						$diff_tables[] = "ALTER TABLE `$table` DROP INDEX `$key`;";
						$diff_tables[] = "ALTER TABLE `$table` ADD $line;";
					}
				}
			}
			// Check foreign keys
			if ($attr['fks'] !== $this_install_tables[$table]['fks']) {
				// Loop through uks
				foreach ($attr['fks'] as $key=>$line) {
                    $this_line = $this_install_tables[$table]['fks'][$key] ?? null;
                    // Format FK line to be compatible with certain MySQL versions (e.g., "ON DELETE NO ACTION ON UPDATE NO ACTION" might be omitted in the "create table" statement for MySQL 8)
                    if ($this_line !== null && strpos($this_line, " ON DELETE ") === false) {
                        $this_line = str_replace("` (`$key`)", "` (`$key`) ON DELETE NO ACTION", $this_line);
                    }
                    if ($this_line !== null && strpos($this_line, " ON UPDATE ") === false) {
                        $this_line .= " ON UPDATE NO ACTION";
                    }
					// If key is missing
					if ($this_line === null) {
						$diff_fks[] = "ALTER TABLE `$table` ADD $line;";
					}
					// If key is different
					elseif ($line != $this_line) {
						// Since $key is probably not the name of the FK (since they are often auto-named when created), determine this FK name
						$true_fk_name = $this->get_FK_from_field($table, $key);
						// Add drop/add FK commands
						if ($true_fk_name !== false) {
							$diff_fks[] = "ALTER TABLE `$table` DROP FOREIGN KEY `$true_fk_name`;";
							$diff_fks[] = "ALTER TABLE `$table` ADD $line;";
						}
					}
				}
			}
		}

		## PARSE INSTALL_DATA.SQL
		// Now obtain SQL for any missing rows for tables in install_data.sql
		$install_data_sql_fixes = $this->parse_install_data_sql();

		// Remove any tables beginning with redcap_ztemp_, which are just temp tables
		$temp_tables = array();
		foreach (array_keys($this->get_create_table_for_all_tables()) as $table) {
			if (strpos($table, "redcap_ztemp_20") === 0) {
				$temp_tables[] = "DROP TABLE IF EXISTS `$table`;";
			}
		}

		// Merge all SQL together and return as SQL
		$sql = trim(implode("\n", array_merge($diff_tables, $diff_fks, $install_data_sql_fixes, $temp_tables)));
		// Correct the 'drop primary key' syntax because it's slightly different
		$sql = str_replace("DROP INDEX `PRIMARY`;", "DROP PRIMARY KEY;", $sql);

		return $sql;
	}


	/**
	 * OBTAIN FOREIGN KEY NAME USING THE FIELD THAT IT REFERENCES IN THE TABLE
	 * NOTE: This will query the existing table that exists in the REDCap database to determine this.
	 * If FALSE is returned, then the FK does not exist.
	 */
	public function get_FK_from_field($table, $column)
	{
		// Find all foreign keys from redcap_mobile_app_log, then delete them, then re-add them (except for log_event_id)
		$sql2 = "SHOW CREATE TABLE `$table`";
		$q = db_query($sql2);
		if ($q && db_num_rows($q) == 1)
		{
			// Get the 'create table' statement to parse
			$result = db_fetch_array($q);
			// Set as lower case to prevent case sensitivity issues
			$createTableStatement = strtolower($result[1]);
			## REMOVE ALL EXISTING FOREIGN KEYS
			// Set regex to pull out strings
			$regex = "/(constraint `)([a-zA-Z0-9_]+)(` foreign key \(`)([a-zA-Z0-9_]+)(`\))/";
			// Do regex
			preg_match_all($regex, $createTableStatement, $matches);
			if (isset($matches[0]) && !empty($matches[0]))
			{
				// Find the foreign key by column
				foreach ($matches[4] as $this_key=>$this_fk)
				{
					if ($this_fk == $column) {
						return $matches[2][$this_key];
					}
				}
			}
		}
		// If we got this far, then we didn't find the FK. So return false.
		return false;
	}


	/**
	 * PARSE "INSERT" STATEMENTS IN INSTALL_DATA.SQL AND PLACE PIECES INTO ARRAY FOR COMPARISON WITH CURRENT ROWS
	 * The table name will be the array key
	 */
	private function parse_install_data_sql()
	{
		// Obtain install_data.sql from Resources/sql/ directory
		$install_data_sql = file_get_contents(APP_PATH_DOCROOT . "Resources/sql/install_data.sql");
		// Replace all \r\n with just \n for compatibility
		$install_data_sql = str_replace("\r\n", "\n", $install_data_sql);
		// Set table name that we're currently on
		$current_table = null;
		// Set insert statement prefix for this table
		$current_table_insert = null;
		// Array that holds table attributes
		$tables = array();
		// Array that holds SQL fixes
		$sql_fixes = array();
		// Loop through file line by line to parse it into an array
		foreach (explode("\n", $install_data_sql) as $line) {
			// Trim it
			$line = trim($line);
			// If blank or a comment, then ignore
			if ($line == '' || substr($line, 0, 3) == '-- ') continue;
			// If first line of table (insert into), then capture table name
			if (strtolower(substr($line, 0, 12)) == 'insert into ') {
				// Get table name
				$current_table = trim(str_replace("`", "", substr($line, 12, strpos($line, " ", 12)-12)));
				// Get insert table prefix
				$tables[$current_table]['insert'] = $line;
				// Detect first field in "insert into" line
				$pos_first_paren = strpos($line, "(")+1;
				$first_field = trim(str_replace("`", "", substr($line, $pos_first_paren, strpos($line, ",")-$pos_first_paren)));
				// Get fields in current table in database
				$tables[$current_table]['fields_current'] = $this->get_current_table_fields($current_table, $first_field);
			}
			// Secondary table line (data row)
			elseif (substr($line, 0, 1) == '(') {
				// Get the value of the first field in the "values" part of the query
				if (substr($line, 1, 1) == "'") {
					$value = trim(substr($line, 2, strpos($line, ",")-3));
				} else {
					$value = trim(substr($line, 1, strpos($line, ",")-1));
				}
				// Convert any double apostrophes to single ones (due to escaping in SQL)
				$value = str_replace("''", "'", $value);
				// If value does not exist in fields_current, then add to fields_missing
				if (!in_array($value, $tables[$current_table]['fields_current'])) {
					// Remove comma and semi-colon on the end
					if (substr($line, -1) == ',' || substr($line, -1) == ';') {
						$line = substr($line, 0, -1);
					}
					// Add to fixes array
					$sql_fixes[] = $tables[$current_table]['insert'] . " $line;";
				}
			} else {
				// Unknown
				continue;
			}
		}
		// Return string of SQL fix statements
		return $sql_fixes;
	}


	/**
	 * RETURN ARRAY OF FIELDS IN DESIRED COLUMN OF DESIRED TABLE
	 */
	private function get_current_table_fields($current_table, $field_name)
	{
		$fields = array();
		$sql = "select $field_name from $current_table";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$fields[] = $row[$field_name];
		}
		return $fields;
	}


	/**
	 * PARSE "CREATE TABLE" STATEMENT AND PLACE PIECES INTO ARRAY
	 * The table name will be the array key with sub-array keys "fields", "pk", "uks", "fks", and "keys"
	 */
	private function parse_install_sql($install_sql)
	{
		// Set table name that we're currently on
		$current_table = null;
		// Array that holds table attributes
		$tables = array();
		// Capture the full "create table" and "alter table" statements to keep in case we need the whole thing
		$create_table = $alter_table = null;
		// Some syntax is not capitalized in earlier versions of MySQL, so replace with capitalized versions
		$orig_syntax = array(' auto_increment', ' default ', ' collate ', ' character set ');
		$repl_syntax = array();
		foreach ($orig_syntax as $i) $repl_syntax[] = strtoupper($i);
		// Loop through file line by line to parse it into an array
		foreach (explode("\n", $install_sql) as $line) {
			// If blank, then ignore
			if ($line == '') continue;
			// Check if we're beginning a new table with CREATE TABLE
			if (substr($line, 0, 13) == 'CREATE TABLE ') {
				$create_table = "$line\n";
				$current_table = trim(str_replace('`', '', substr($line, 13, -2)));
				$tables[$current_table] = array("fields"=>array(), "pk"=>'', "uks"=>array(), "keys"=>array(), "fks"=>array(),
					"create_table"=>"", "create_table_fks"=>"");
			}
			// Check if we're beginning a new FK with ALTER TABLE
			elseif (substr($line, 0, 12) == 'ALTER TABLE ') {
				$alter_table = "$line\n";
				$current_table = trim(str_replace('`', '', substr($line, 12)));
			}
			// If a foreign key
			elseif (substr($line, 0, 16) == 'ADD FOREIGN KEY ') {
				$alter_table .= "$line\n";
				if (substr($line, -1) == ';') {
					$tables[$current_table]['create_table_fks'] = trim($alter_table);
				}
				$key_name = trim(str_replace("`", "", substr($line, 17, strpos($line, "`", 19)-17)));
				$line = substr($line, 4, -1);
				$tables[$current_table]['fks'][$key_name] = $line;
			}
			// If a primary key
			elseif (substr($line, 0, 12) == 'PRIMARY KEY ') {
				// Some versions of MySQL might put 2 spaces after "key", so remove the extra space
				$line = str_replace('PRIMARY KEY  ', 'PRIMARY KEY ', $line);
				// Add line
				$create_table .= "$line\n";
				if (substr($line, -1) == ',') $line = substr($line, 0, -1);
				$tables[$current_table]['pk'] = $line;
			}
			// If a unique key
			elseif (substr($line, 0, 11) == 'UNIQUE KEY ') {
				$create_table .= "$line\n";
				$key_name = trim(str_replace("`", "", substr($line, 11, strpos($line, " ", 11)-11)));
				if (substr($line, -1) == ',') $line = substr($line, 0, -1);
				$tables[$current_table]['uks'][$key_name] = $line;
			}
			// If a normal index
			elseif (substr($line, 0, 4) == 'KEY ') {
				$create_table .= "$line\n";
				$key_name = trim(str_replace("`", "", substr($line, 4, strpos($line, " ", 4)-4)));
				if (substr($line, -1) == ',') $line = substr($line, 0, -1);
				$tables[$current_table]['keys'][$key_name] = $line;
			}
			// Last line of "create table"
			elseif (substr($line, 0, 2) == ') ') {
				$create_table .= $line;
				$tables[$current_table]['create_table'] = $create_table;
			}
			// Table field
			else {
				// Some syntax is not capitalized in earlier versions of MySQL, so replace with capitalized versions
				$line = str_replace($orig_syntax, $repl_syntax, $line);
				// Add line
				$create_table .= "$line\n";
				if (!empty($line) && strlen($line) > 2) {
                    $field_name = trim(str_replace("`", "", substr($line, 0, strpos($line, "`", 2))));
                    if (substr($line, -1) == ',') $line = substr($line, 0, -1);
                    $tables[$current_table]['fields'][$field_name] = $line;
                }
			}
		}
		// Return array of table attributes
		return $tables;
	}


	/**
	 * GET "CREATE TABLE" STATEMENT AND SEPARATE FOREIGN KEY ALTER TABLES FROM IT
	 */
	private function split_create_table_fks($table_name, $create_table_statement)
	{
		// Make sure all line breaks are \n and not \r
		$create_table_statement = str_replace(array("\r\n", "\r", "\n\n"), array("\n", "\n", "\n"), trim($create_table_statement));
		// Remove auto_increment number
		if (stripos($create_table_statement, "auto_increment")) {
			$create_table_statement = preg_replace("/(\s+)(AUTO_INCREMENT)(\s*)(=)(\s*)(\d+)(\s+)/", " ", $create_table_statement);
		}
		// Place all SQL into strings, segregating create table statements and foreign key statements
		$create_table = $foreign_keys = $primary_key = $unique_keys = "";
		$foreign_key_array = $unique_key_array = $key_array = array();
		// Separate statement into separate lines
		$create_array = explode("\n", $create_table_statement);
		// Check each line
		foreach ($create_array as $line)
		{
			// Trim the line
			$line = trim($line);
			// If a foreign key
			if (substr($line, 0, 11) == 'CONSTRAINT ') {
				// Format the line
				$fkword_pos = strpos($line, "FOREIGN KEY ");
				$fkline = trim(substr($line, $fkword_pos));
				if (substr($fkline, -1) == ',') $fkline = substr($fkline, 0, -1);
				$fkline = "ADD ".$fkline;
				// Isolate the field names
				$first_paren_pos = strpos($fkline, "(")+1;
				$fk_field = trim(str_replace("`", "", substr($fkline, $first_paren_pos, strpos($fkline, ")")-$first_paren_pos)));
				// Add FK line to FK array
				$foreign_key_array[$fk_field] = $fkline;
			}
			// If a primary key
			elseif (substr($line, 0, 12) == 'PRIMARY KEY ') {
				$primary_key = $line;
			}
			// If a unique key
			elseif (substr($line, 0, 11) == 'UNIQUE KEY ') {
				$key_name = trim(str_replace("`", "", substr($line, 11, strpos($line, " ", 11)-11)));
				if (substr($line, -1) == ',') $line = substr($line, 0, -1);
				$unique_key_array[$key_name] = $line;
			}
			// If a normal index
			elseif (substr($line, 0, 4) == 'KEY ') {
				$key_name = trim(str_replace("`", "", substr($line, 4, strpos($line, " ", 4)-4)));
				if (substr($line, -1) == ',') $line = substr($line, 0, -1);
				$key_array[$key_name] = $line;
			}
			// Table field
			else {
				$create_table .= "\n$line";
			}
		}
		// Format strings
		$create_table = $this->remove_comma_from_create_table(trim($create_table).";");
		// Insert primary key into create_table statement above the last line
		if ($primary_key != '') {
			$last_line_break_pos = strrpos($create_table, "\n");
			$create_table = substr($create_table, 0, $last_line_break_pos) . ",\n$primary_key" . substr($create_table, $last_line_break_pos);
			$create_table = $this->remove_comma_from_create_table($create_table);
		}
		// Sort the UKs for consistency from install to install and insert into create table statement
		if (!empty($unique_key_array)) {
			ksort($unique_key_array);
			$last_line_break_pos = strrpos($create_table, "\n");
			$create_table = substr($create_table, 0, $last_line_break_pos) . ",\n" . implode(",\n", $unique_key_array) . substr($create_table, $last_line_break_pos);
			$create_table = $this->remove_comma_from_create_table($create_table);
		}
		// Sort the keys for consistency from install to install and insert into create table statement
		if (!empty($key_array)) {
			ksort($key_array);
			$last_line_break_pos = strrpos($create_table, "\n");
			$create_table = substr($create_table, 0, $last_line_break_pos) . ",\n" . implode(",\n", $key_array) . substr($create_table, $last_line_break_pos);
			$create_table = $this->remove_comma_from_create_table($create_table);
		}
		// Sort the FKs for consistency from install to install
		if (!empty($foreign_key_array)) {
			ksort($foreign_key_array);
			$foreign_keys = "ALTER TABLE `$table_name`\n".implode(",\n", $foreign_key_array).";";
		}
		// Return the strings
		return array($create_table, $foreign_keys);
	}


	/**
	 * REMOVE COMMA FROM END OF SECOND-TO-LAST LINE OF "CREATE TABLE" STATEMENT
	 */
	private function remove_comma_from_create_table($create_table)
	{
		$create_array = explode("\n", $create_table);
		$second_to_last_key = count($create_array)-2;
		if (substr($create_array[$second_to_last_key], -1) == ',') {
			$create_array[$second_to_last_key] = substr($create_array[$second_to_last_key], 0, -1);
			$create_table = implode("\n", $create_array);
		}
		return $create_table;
	}


	/**
	 * BUILD INSTALL.SQL FILE FROM "SHOW CREATE TABLE" OF ALL REDCAP TABLES
	 */
	public function build_install_file_from_tables($forceConvertMb4=false, $addCollationToGoldStandard=false)
	{
		// Set "show create table" to quote the table names with backticks
		db_query("SET SESSION sql_quote_show_create = 1");
		// Place all SQL into strings, segregating create table statements and foreign key statements
		$create_table = $foreign_keys = array();
		// Get create table statement for all tables
		$tables = $this->get_create_table_for_all_tables();
		// Loop through each table and get CREATE TABLE sql
		foreach ($tables as $table=>$create_table_sql) {
			// Get CREATE TABLE statement with separate FK piece
			$this_create_table = $this->split_create_table_fks($table, $create_table_sql);
            // Remove any rows containing `pk_id` (the optional auto-incrementing Primary Key)
            if (strpos($this_create_table[0], "`pk_id`") !== false) {
                $this_table = [];
                foreach (explode("\n", $this_create_table[0]) as $this_row) {
                    if (strpos($this_row, "`pk_id`") === false) {
                        $this_table[] = $this_row;
                    }
                }
                $this_create_table[0] = implode("\n", $this_table);
            }
			// Add SQL to arrays
			$create_table[] = $this_create_table[0];
			if ($this_create_table[1] != '') {
				$foreign_keys[] = $this_create_table[1];
			}
		}
		// Build SQL file
		$sql = implode("\n\n", $create_table) . "\n\n" . implode("\n\n", $foreign_keys);
		// Replace all \r\n with just \n for compatibility
		$sql = str_replace("\r\n", "\n", $sql);
		// Because MySQl 8.0+ introduces "CHARACTER SET utf8XXXX" and "ZEROFILL" for field definitions, remove this for compatibility
		$sql = str_replace(" CHARACTER SET utf8mb4", "", $sql);
		$sql = str_replace(" CHARACTER SET utf8mb3", "", $sql);
		$sql = str_replace(" CHARACTER SET utf8", "", $sql);
		$sql = str_replace(" ZEROFILL", "", $sql);
		// Because MariaDB 10.2+ uses the syntax "DEFAULT 0" instead of "DEFAULT '0'", compensate for that
		$sql = preg_replace("/( DEFAULT )(\d+)/", "$1'$2'", $sql);
		// Because MariaDB 10.2+ adds "DEFAULT NULL" to column definitions for TEXT and BLOB types that have default null, compensate for that
		// by adding it across the board (because it's much easier to remove it than to figure out where to insert it).
		$sql = str_replace("text COLLATE utf8mb4_unicode_ci", "text COLLATE utf8mb4_unicode_ci DEFAULT NULL", $sql);
		$sql = str_replace("text COLLATE utf8mb3_unicode_ci", "text COLLATE utf8mb3_unicode_ci DEFAULT NULL", $sql);
		$sql = str_replace("text COLLATE utf8_unicode_ci", "text COLLATE utf8_unicode_ci DEFAULT NULL", $sql);
		$sql = str_replace("` longblob", "` longblob DEFAULT NULL", $sql);
		$sql = str_replace("` blob", "` blob DEFAULT NULL", $sql);
		$sql = str_replace("NOT NULL DEFAULT current_timestamp()", "NOT NULL DEFAULT CURRENT_TIMESTAMP", $sql);
		// Because MariaDB 10.6.1+ now reports character sets and collations in 'utf8' columns and tables as 'utf8mb3', remove mb3 for backward compatibility to match install sql.
		$sql = str_replace(" COLLATE utf8mb3_unicode_ci", " COLLATE utf8_unicode_ci", $sql);
		$sql = str_replace(" COLLATE utf8mb3_bin", " COLLATE utf8_bin", $sql);
		$sql = str_replace("=utf8mb3", "=utf8", $sql);
		// Fix any duplicates caused in replacements above
		$sql = str_replace("DEFAULT NULL DEFAULT NULL", "DEFAULT NULL", $sql);
		$sql = str_replace("DEFAULT NULLDEFAULT NULL", "DEFAULT NULL", $sql);
		$sql = str_replace("DEFAULT NULLNOT NULL", "NOT NULL", $sql);
		$sql = str_replace("DEFAULT NULL NOT NULL", "NOT NULL", $sql);
		// If a table is missing COLLATE in its "create table" statement, auto-add it
		$sql = str_replace(" CHARSET=utf8mb4;", " CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", $sql);
		$sql = str_replace(" CHARSET=utf8;", " CHARSET=utf8 COLLATE=utf8_unicode_ci;", $sql);
        // Do not include ROW_FORMAT settings (we'll deal with this via Configuration Check page separately)
		$sql = str_replace([" ROW_FORMAT=COMPACT", " ROW_FORMAT=DYNAMIC", " ROW_FORMAT=REDUNDANT", " ROW_FORMAT=COMPRESSED"], "", $sql);
        // If we're building the gold standard install.sql on a system where "COLLATE utf8mb4_unicode_ci " does not exist in "show create table" results
        // (because using newer MariaDB version), then manually add COLLATE to specific column types in install.sql.
        if ($addCollationToGoldStandard) {
            // Add "COLLATE utf8mb4_unicode_ci " to all columns with the following data type: char, varchar, longtext, mediumtext, enum
            $sql = preg_replace("/(` )(char\(\d{1,4}\)|varchar\(\d{1,4}\)|longtext|mediumtext|text|enum\(.+\))( )/", "` $2 COLLATE utf8mb4_unicode_ci ", $sql);
	        // Remove some false positives for utf8mb4_bin
	        $sql = str_replace("COLLATE utf8mb4_unicode_ci COLLATE utf8mb4_bin ", "COLLATE utf8mb4_bin ", $sql);
            // Remove some false positives where we want to keep latin1 collation
            $sql = str_replace("COLLATE utf8mb4_unicode_ci CHARACTER SET latin1 ", "CHARACTER SET latin1 ", $sql);
        }
		// Return SQL
		return trim($sql);
	}


	/**
	 * DETECT IF INNODB ENGINE IS ENABLED IN MYSQL. Return boolean.
	 */
	public function innodb_enabled()
	{
		$q = db_query("SHOW ENGINES");
		while ($row = db_fetch_assoc($q)) {
			if ($row['Engine'] == 'InnoDB') {
				return (strtoupper($row['Support']) != 'NO');
			}
		}
		return false;
	}

	// Parse a block of multiple SQL queries, and return them as individual queries in an array
	public static function parseMultipleSqlQueries($sql)
	{
		//START_OF_PARSER
		$iCur = 0;            //Current character pointer inside the SQL content
		$iInside = 0;         //The context, in which the pointer is currently located (is the pointer inside a
		//comment, an SQL query, or deeper into an SQL query value?)
		$sBuffer = "";        //The buffer of the next individual query
		$aQueries = array();  //The list of queries
		$sFileContents = $sql;
		while($iCur < strlen($sFileContents)) {

			switch ($iInside) {
				case 0: //Inside query-context
					//Change context: Comments beginning with --
					if(substr($sFileContents, $iCur, 2) === "--") {
						$iCur++;
						$iInside = 2;

						//Change context: Comments beginning with /*
					} elseif(substr($sFileContents, $iCur, 2) === "/*") {
						$iCur++;
						$iInside = 3;

						//Change context: Comments beginning with #
					} elseif(substr($sFileContents, $iCur, 1) === "#") {
						$iInside = 2;

						//Separator for a new query
					} elseif(substr($sFileContents, $iCur, 1) === ";") {
						$aQueries[] = trim($sBuffer); //$sBuffer;  //Add current buffer to a unique array query item
						$sBuffer = "";  //Start a new buffer

						//Change context: query values opened with '
					} elseif(substr($sFileContents, $iCur, 1) === "'") {
						$sBuffer .= substr($sFileContents, $iCur, 1);
						$iInside = 1;

						//Change context: query values opened with "
					} elseif(substr($sFileContents, $iCur, 1) === '"') {
						$sBuffer .= substr($sFileContents, $iCur, 1);
						$iInside = 4;

						//Not a special character
					} else {
						$sBuffer .= substr($sFileContents, $iCur, 1);
					}
					break;

				case 1: //Inside value-context, ending with '

					//Escaping character found within the query-value
					if(substr($sFileContents, $iCur, 1) === "\\") {
						$sBuffer .= substr($sFileContents, $iCur, 2);
						$iCur++;  //Skip next char

						//The ending character for the query-value is found
					} elseif(substr($sFileContents, $iCur, 1) === "'") {
						$sBuffer .= substr($sFileContents, $iCur, 1);
						$iInside = 0;

						//Not a special character
					} else {
						$sBuffer .= substr($sFileContents, $iCur, 1);
					}
					break;

				case 4: //Inside value-context, ending with "

					//Escaping character found within the query-value
					if(substr($sFileContents, $iCur, 1) === "\\") {
						$sBuffer .= substr($sFileContents, $iCur, 2);
						$iCur = $iCur + 1;  //Skip next char

						//The ending character for the query-value is found
					} elseif(substr($sFileContents, $iCur, 1) === '"') {
						$sBuffer .= substr($sFileContents, $iCur, 1);
						$iInside = 0;

						//Not a special character
					} else {
						$sBuffer .= substr($sFileContents, $iCur, 1);
					}
					break;

				case 2: //Inside comment-context, ending with newline

					//A two-character newline is found, signalling the end of the comment
					if(substr($sFileContents, $iCur, 2) === "\r\n") {
						$iCur++;
						$iInside = 0;

						//A single-character newline is found, signalling the end of the comment
					} elseif(substr($sFileContents, $iCur, 1) === "\n" || substr($sFileContents, $iCur, 1) === "\r") {
						$iInside = 0;
					}
					break;

				case 3: //Inside comment-context, ending with */

					//A two-character */ is found, signalling the end of the comment
					if(substr($sFileContents, $iCur, 2) === "*/") {
						$iCur++;
						$iInside = 0;
					}
					break;

				default:
					break;
			}
			$iCur++;
		}
		//END_OF_PARSER
		return $aQueries;
	}

    // Generate SQL to add primary keys to all tables, if desired (for clustering or replication needs)
    public function suggestSqlPrimaryKeyAllTables()
    {
        $tables = $this->get_create_table_for_all_tables();
        $suggestedSql = "";
        foreach ($tables as $table=>$createTableSql) {
            if (strpos($createTableSql, "PRIMARY KEY (`") === false) {
                $suggestedSql .= "ALTER TABLE `$table` ADD `pk_id` BIGINT(13) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`pk_id`);\n";
            }
        }
        return $suggestedSql;
    }

}
