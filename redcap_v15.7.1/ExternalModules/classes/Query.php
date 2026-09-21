<?php namespace ExternalModules;

const QUERY_PARAMETER_PLACEHOLDER = "This is a placeholder string that would never actually be used as a parameter to a query.";

class Query{
    private $sql = '';
    private $parameters = null;

    /**
     * @return static
     *
     * @param string $sql
     * @param array|numeric|string $parameters
     * 
     * @psalm-taint-sink sql $sql
     */
    function add($sql, $parameters = QUERY_PARAMETER_PLACEHOLDER){
        $this->sql .= " $sql ";

        if($parameters !== QUERY_PARAMETER_PLACEHOLDER){
            if(!is_array($parameters)){
                $parameters = [$parameters];
            }

            $this->parameters = array_merge($this->parameters ?? [], $parameters);
        }
        
        return $this;
    }

    /**
     * @param string $columnName
     * @param ((int|string)|mixed)[]|int|null $values
     * 
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    function addInClause($columnName, $values): Query{
        list($sql, $parameters) = ExternalModules::getSQLInClause($columnName, $values, true);
        return $this->add($sql, $parameters);
    }

    /**
     * @return \mysqli_result
     */
    function execute(){
        return ExternalModules::query($this->getSql(), $this->getParameters());
    }

    function getSQL(){
        return $this->sql;
    }

    function getParameters(){
        return $this->parameters;
    }

    function __get($name){
        if($name === 'affected_rows'){
            return db_affected_rows();
        }

        throw new \Exception('Not yet implemented: ' . $name);
    }
}
