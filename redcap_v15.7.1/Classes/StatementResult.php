<?php namespace Vanderbilt\REDCap\Classes;

use Exception;

class StatementResult // extends \mysqli_result
{    
    public $current_field = 0;
    public $field_count;
    public $lengths;
    public $num_rows;

    private $statement;
    private $fields;
    private $row = [];
    private $closed = false;

    function __construct($s, $metadata, $resultmode){
        $this->statement = $s;

        if($resultmode === MYSQLI_STORE_RESULT && !$s->store_result()){
            throw new Exception("The mysqli_stmt::store_result() method failed.");
        }

        $this->num_rows = $s->num_rows;
        $this->fields = $fields = $metadata->fetch_fields();
        if(!$fields){
            throw new Exception("The mysqli_stmt metadata fetch_fields() method failed.");
        }

        $this->field_count = count($fields);

        $this->row = [];
        $refs = [];
        for($i=0; $i<count($fields); $i++){
            // Set up the array of references required for bind_result().
            $refs[$i] = null;
            $this->row[$i] = &$refs[$i];
        }
        
        if(!call_user_func_array([$s, 'bind_result'], $this->row)){
            throw new Exception("Binding statement results failed.");
        }
    }

    /**
     * @return (mixed|null)[]|false|null
     */
    function fetch_assoc(){
        return $this->fetch_array(MYSQLI_ASSOC);
    }

    /**
     * @return (mixed|null)[]|false|null
     */
    function fetch_row(){
        return $this->fetch_array(MYSQLI_NUM);
    }

    /**
     * @return array|false|null|object
     */
    function fetch_object($class_name = 'stdClass', $params = []){
        $row = $this->fetch_assoc();
        if(!$row){
            return $row;
        }

        $reflector = new \ReflectionClass($class_name);
        $object = $reflector->newInstanceArgs($params);
        
        foreach($row as $key=>$value){
            $object->$key = $value;
        }

        return $object;
    }

    /**
     * @return (mixed|null)[]|false|null
     *
     * @param int|mixed $resultType
     */
    function fetch_array($resultType = MYSQLI_BOTH){
        if($this->closed){
            return $this->getClosedReturnValue();
        }

        $fetchResult = $this->statement->fetch();
        if($fetchResult === false){
            throw new Exception('mysqli_stmt::fetch() failed!');
        }
        elseif($fetchResult === null){
            return null;
        }

        $dereferencedRow = [];
        foreach($this->row as $index=>$value){
            $this->lengths[$index] = strlen($value ?? '');

            if($resultType !== MYSQLI_ASSOC){
                $dereferencedRow[$index] = $value;
            }

            if($resultType !== MYSQLI_NUM){
                $columnName = $this->fields[$index]->name;
                $dereferencedRow[$columnName] = $value;
            }
        }

        return $dereferencedRow;
    }

    function fetch_field_direct($fieldnr){
        return $this->fields[$fieldnr];
    }

    function fetch_fields(){
        if($this->closed){
            return $this->getClosedReturnValue();
        }

        return $this->fields;
    }

    /**
     * @return void
     */
    function free(){
        $this->free_result();
    }

    /**
     * @return void
     */
    function close(){
        $this->free_result();
    }

    /**
     * @return false|null
     */
    function free_result(){
        $this->statement->free_result();
        
        $this->closed = true;

        $this->current_field = false;
        $this->lengths = false;
        
        $returnValue = $this->getClosedReturnValue();
        $this->field_count = $returnValue;
        $this->num_rows = $returnValue;

        return $returnValue;
    }

    function fetch_field(){
        $field = $this->fields[$this->current_field] ?? null;
        
        $this->current_field++;

        return $field;
    }

    /**
     * @return void
     */
    function data_seek($i){
        $this->statement->data_seek($i);
    }

    /**
     * @return never
     */
    private function throwNotImplementedException($message){
        throw new Exception('Not yet implemented: ' . $message);
    }

    function __get($name){
        $this->throwNotImplementedException($name);
    }

    function __call($name, $args){
        $this->throwNotImplementedException($name);
    }

    /**
     * @return false|null
     */
    private function getClosedReturnValue(){
        if(PHP_MAJOR_VERSION === 5){
            return null;
        }
        else{
            return false;
        }
    }
}