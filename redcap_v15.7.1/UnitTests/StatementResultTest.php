<?php

class StatementResultTest extends REDCapTestCase
{
    function test_num_rows(){
        $r = db_query('select ? union select ? union select ?', [1, 2, 3]);
        $this->assertSame(3, $r->num_rows);

        // empty result set
        $r = db_query('select ? from redcap_data where 2=3', [1]);
        $this->assertSame(0, $r->num_rows);
    }

    function test_current_field(){
        $result = db_query('select ?,?', [1,2]);
        
        $this->assertSame(0, $result->current_field);
        $result->fetch_field();
        $this->assertSame(1, $result->current_field);
        $result->fetch_field();
        $this->assertSame(2, $result->current_field);
    }

    function test_field_count(){
        $result = db_query('select ?,?,?,?,?', [1,2,3,4,5]);
        $this->assertSame(5, $result->field_count);
    }

    function test_lengths(){
        $result = db_query("select ?,? union select ?,?", [1, 1.1, 'aa', null]);
        
        $result->fetch_row();
        $this->assertSame([1,3], $result->lengths);

        db_fetch_row($result);
        $this->assertSame([2,0], $result->lengths);
    }

    function test_fetch_field(){
        $result = db_query('select ? as a, ? as b', [1,2]);
        $this->assertSame('a', $result->fetch_field()->name);
        $this->assertSame('b', $result->fetch_field()->name);
        $this->assertNull($result->fetch_field());
    }

    function test_fetch_assoc(){
        $r = db_query('select ? as foo union select ?', [1, 2]);
        $this->assertSame(['foo'=>1], $r->fetch_assoc());
        $this->assertSame(['foo'=>2], db_fetch_assoc($r));
        $this->assertNull($r->fetch_assoc());

        // empty result set
        $r = db_query('select ? from redcap_data where 2=3', [1]);
        $this->assertNull($r->fetch_assoc());
    }

    function test_fetch_row(){
        $r = db_query('select ? union select ?', [1, 2]);
        $this->assertSame([0=>1], $r->fetch_row());
        $this->assertSame([0=>2], db_fetch_row($r));
        $this->assertNull($r->fetch_row());

        // empty result set
        $r = db_query('select ? from redcap_data where 2=3', [1]);
        $this->assertNull($r->fetch_row());
    }

    function test_fetch_array(){
        $r = db_query('select ? union select ? union select ? union select ?', [1, 2, 3, 4]);
        $this->assertSame([0=>1, '?'=>1], $r->fetch_array());
        $this->assertSame([0=>2, '?'=>2], db_fetch_array($r, MYSQLI_BOTH));
        $this->assertSame([0=>3], $r->fetch_array(MYSQLI_NUM));
        $this->assertSame(['?'=>4], db_fetch_array($r, MYSQLI_ASSOC));
        $this->assertNull($r->fetch_array());

        // empty result set
        $r = db_query('select ? from redcap_data where 2=3', [1]);
        $this->assertNull($r->fetch_array());
    }

    function test_db_fetch_field_direct(){
        $fetchField = function($sql, $params){
            $result = db_query($sql, $params);
            $field = $result->fetch_field_direct(0);

            $this->normalizeField($field);

            return $field;
        };

        $expected = $fetchField('select 1 as a', []);
        $actual = $fetchField('select ? as a', [1]);

        $this->assertSame('a', $actual->name);
        $this->assertEquals($expected, $actual);
    }

    function test_fetch_fields(){
        $fetchFields = function($sql, $params){
            $result = db_query($sql, $params);
            $fields = $result->fetch_fields();

            foreach($fields as $field){
                $this->normalizeField($field);
            }

            return $fields;
        };

        $expected = $fetchFields('select 1 as a', []);
        $actual = $fetchFields('select ? as a', [1]);

        $this->assertSame('a', $actual[0]->name);
        $this->assertEquals($expected, $actual);
    }

    function test_data_seek(){
        $r = db_query('select ? as a', [1]);
        $this->assertSame([0=>1], $r->fetch_row());
        $r->data_seek(0);
        $this->assertSame([0=>1], $r->fetch_row());
    }

    function test_fetch_object(){
        $r = db_query("select 'a' as b", []);
        $expected = $r->fetch_object();
        
        $r = db_query('select ? as b', ['a']);
        $actual = db_fetch_object($r);

        $this->assertEquals($expected, $actual);
        $this->assertNull($r->fetch_object());
    }

    function test_fetch_object_constructor_args(){
        $class = FetchObject::class;
        $constructorArgs = [rand(), rand(), rand()];

        $r = db_query("select 'a' as b", []);
        $expected = $r->fetch_object($class, $constructorArgs);
        
        $r = db_query('select ? as b', ['a']);
        $actual = $r->fetch_object($class, $constructorArgs);
        
        $this->assertEquals($expected, $actual);
    }

    private function normalizeField(&$field){
        // These values are different when using a prepared statement.
        unset($field->length);
        unset($field->max_length);
        unset($field->type); // This one is only different on some systems (namely Devilbox on Ubuntu on Mark's personal laptop)
        unset($field->flags); // This one is only different on some systems (namely docker on John's laptop)
    }

    function test_free_result(){
        $result = db_query('select ?', 1);

        // Just make sure they run without exception.
        $result->free();
        $result->close();
        $result->free_result();

        $this->expectNotToPerformAssertions();
    }

    function test_use_result(){
        $assert = function(){
            $r = db_query('select ?', 1, null, MYSQLI_USE_RESULT);

            $this->assertSame(0, db_num_rows($r)); // The number of rows isn't known when using MYSQLI_USE_RESULT
            $this->assertSame(['?' => 1], $r->fetch_assoc());

            return $r;
        };

        $r = $assert();

        $exceptionMessage = null;
        try{
            $assert();
        }
        catch(\Exception $e){
            $exceptionMessage = $e->getMessage();
        }
        $this->assertSame('Statement preparation failed', $exceptionMessage);

        db_free_result($r);

        // Make sure db_free_result() worked, and further queries are allowed
        $assert();
    }
}

class FetchObject{
    public $constructorArgs;
    public $b;

    function __construct(){
        $this->constructorArgs = func_get_args();
    }
}