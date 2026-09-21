<?php namespace ExternalModules;

class LogPseudoQuery extends AbstractPseudoQuery{
    /**
     * @return string
     */
    function getTable(){
        return 'redcap_external_modules_log';
    }

    function formatColumnFieldName($field){
        return $field;
    }

    /**
     * @return null|string
     *
     * @param array $whereFields
     */
    function getFirstStandardWhereClause($whereFields){
        if(!in_array('external_module_id', $whereFields)){
            $prefix = $this->getFramework()->getModuleInstance()->PREFIX;
            return AbstractExternalModule::EXTERNAL_MODULE_ID_STANDARD_WHERE_CLAUSE_PREFIX . " = '$prefix')";
        }
        
        return null;
    }

    function addStandardWhereClauses($parsedWhere, $whereFields){
        $standardWhereClauses = [];

        $firstStandardWhereClause = $this->getFirstStandardWhereClause($whereFields);
        if($firstStandardWhereClause !== null){
            $standardWhereClauses[] = $firstStandardWhereClause;
        }

        if(!in_array('project_id', $whereFields)){
            $projectId = $this->getFramework()->getProjectId();
            if (!empty($projectId)) {
                $standardWhereClauses[] = $this->getTable() . ".project_id = $projectId";
            }
        }

        if(!empty($standardWhereClauses)){
            $standardWhereClausesSql = 'where ' . implode(' and ', $standardWhereClauses);

            if($parsedWhere === null){
                // Set it to an empty array, since array_merge() won't work on null.
                $parsedWhere = [];
            }
            else{
                // The empty parenthesis will get populated with the given where clause below.
                $standardWhereClausesSql .= ' and ()';
            }

            $parsedStandardWhereClauses = $this->parse($standardWhereClausesSql);
            $newWhere = $parsedStandardWhereClauses['WHERE'];

            if(!empty($parsedWhere)){
                $newWhere[count($newWhere)-1]['sub_tree'] = $parsedWhere;
            }

            $parsedWhere = $newWhere;
        }

        return $parsedWhere;
    }

    /**
     * @return bool
     */
    function isJoinRequired($field){
        $logParamsOnMainTable = array_flip(array_merge(AbstractExternalModule::$RESERVED_LOG_PARAMETER_NAMES, AbstractExternalModule::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE));
        return !isset($logParamsOnMainTable[$field]);
    }

    /**
     * @return bool
     */
    function isAliasRequired($field){
        return $this->isJoinRequired($field);
    }

    /**
     * @return string
     */
    function getJoinSQL($field, $fieldString){
        return "
            left join redcap_external_modules_log_parameters $field
            on $field.log_id = redcap_external_modules_log.log_id
            and $field.name = '$fieldString'
        ";
    }
}