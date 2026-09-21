<?php namespace ExternalModules;

use Exception;

class DataPseudoQuery extends AbstractPseudoQuery{
    private $project;
    private $repeatingFormsByField;
    private $repeatingJoinFields;
    private $nonJoinFields;

    private static $BUILT_IN_FIELDS = [
      'project_id' => true,
      'redcap_repeat_instance' => true
    ];

    private static $SUPPORTED_FIELD_TYPES = [
        'text' => true,
        'radio' => true,
        'yesno' => true,
        'calc' => true,
        'select' => true, // this is the type that comes back for form completion fields.
    ];

    private $getDataCompatible = false;

    function __construct($project){
		$this->project = $project;
        parent::__construct($project->getFramework());
	}

    function getProject(){
        return $this->project;
    }

    /**
     * @return string
     */
    function getTable(){
        return \Records::getDataTable($this->getProject()->getProjectId());
    }

    /**
     * @return string
     */
    function formatColumnFieldName($field){
        $field = str_replace('[', '', $field);
        $field = str_replace(']', '', $field);
        
        return $field;
    }

    /**
     * @return bool
     */
    function isJoinRequired($field){
        if(!isset($this->nonJoinFields)){
            $this->nonJoinFields = array_flip([
                $this->getProject()->getRecordIdField(),
                'project_id',
                'event_id'
            ]);
        }
        
        return !isset($this->nonJoinFields[$field]);        
    }

    /**
     * @return bool
     */
    function isAliasRequired($field){
        $this->ensureFieldTypeSupported($field);

        if($field === $this->getProject()->getRecordIdField()){
            return true;
        }

        return $this->isJoinRequired($field);
    }

    /**
     * @return void
     */
    private function ensureFieldTypeSupported($field){
        if(isset(self::$BUILT_IN_FIELDS[$field])){
            return;
        }

        $type = $this->getProject()->getField($field)->getType();
        if(!isset(self::$SUPPORTED_FIELD_TYPES[$type])){
            throw new Exception(ExternalModules::tt('em_errors_142', $type, $field));
        }
    }

    /**
     * @return string
     */
    function getJoinSQL($field, $fieldString){
        $table = $this->getTable();

        // We'll need to add "event_id" to this query if we ever want to support multiple events.
        // It was excluded out of the gate because it slows down the "group by" inner select.
        $sql = "
            left join $table $field
            on $field.project_id = $table.project_id
            and $field.record = $table.record
            and $field.field_name = '$fieldString'
        ";

        $repeatingForm = $this->getRepeatingForm($field);
        if($repeatingForm !== null){
            $repeatingForm = db_escape($repeatingForm);
            $sql .= " and redcap_repeat_instrument.value = '$repeatingForm'";

            if(isset($this->repeatingJoinFields)){
                $joinInstanceSql = '';
                foreach($this->repeatingJoinFields as $joinField){
                    if(!empty($joinInstanceSql)){
                        $joinInstanceSql .= ', ';
                    }

                    $joinInstanceSql .= "$joinField.instance";
                }

                $sql .= "
                    and (
                        (($field.instance is null and coalesce($joinInstanceSql) is null))
                        or
                        ($field.instance is not null and $field.instance = coalesce($joinInstanceSql))
                    )
                ";
            }

            $this->repeatingJoinFields[] = $field;
        }

        return $sql;
    }

    function isGetDataCompatible(){
        return $this->getDataCompatible;
    }

    /**
     * @param true $value
     */
    function setGetDataCompatible($value){
        $this->getDataCompatible = $value;
    }

    /**
     * @return string
     */
    function getAliasFieldName($field, $forSelect = false){
        if($field === $this->getProject()->getRecordIdField()){
            return $this->getTable() . ".record";
        }

        $aliasFieldName = parent::getAliasFieldName($field, $forSelect);

        if($forSelect){
            $repeatingForm = $this->getRepeatingForm($field);
            if($repeatingForm === null){
                $repeatingForm = '';
            }
    
            if($this->isGetDataCompatible()){
                // Only limit returned values to the current repeating form if we need to compatible with REDCap::getData().
                $aliasFieldName = "IF(redcap_repeat_instrument.value = '$repeatingForm', $aliasFieldName, '')";
            }
        }

        return "IFNULL($aliasFieldName, '')";
    }

    function getRepeatingForm($field){
        return $this->getRepeatingFormsByField()[$field] ?? null;
    }
    
	private function getRepeatingFormsByField(){
		if(!isset($this->repeatingFormsByField)){
			foreach($this->getProject()->getRepeatingForms() as $form){
				foreach($this->getProject()->getForm($form)->getFieldNames() as $field){
					$this->repeatingFormsByField[$field] = db_escape($form);
				}
			}
		}

		return $this->repeatingFormsByField;
	}
}