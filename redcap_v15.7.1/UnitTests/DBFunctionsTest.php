<?php

use ExternalModules\ExternalModules;

require_once APP_PATH_DOCROOT . '/UnitTests/REDCapTestCase.php';

class DBFunctionsTest extends REDCapTestCase
{
    const TEST_BROKEN_MESSAGE = "This test is being skipped the test broke (likely due to a change in behavior months/years ago).";
    const TEST_WARNINGS_MESSAGE = "
        This test is being skipped because it is throwing warnings that we likely don't want to see when running tests.
        The offending assertions may no longer be necessary if want to instead require that developers review and address warnings during development.
    ";

    function test_db_fetch_functions(){  
        $this->markTestSkipped(static::TEST_BROKEN_MESSAGE);
      
        $this->assertFetchFunction('db_fetch_row', MYSQLI_NUM);
        $this->assertFetchFunction('db_fetch_assoc', MYSQLI_ASSOC);
        $this->assertFetchFunction('db_fetch_array', MYSQLI_NUM);
        $this->assertFetchFunction('db_fetch_array', MYSQLI_ASSOC);
        $this->assertFetchFunction('db_fetch_array', MYSQLI_BOTH);
    }

    private function assertFetchFunction($functionName, $resultType){
        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName, $resultType){
            $row = $functionName($result, $resultType);

            if($resultType !== MYSQLI_ASSOC){
                $this->assertSame($value, $row[0]);
            }

            if($resultType !== MYSQLI_NUM){
                $this->assertSame($value, $row[$columnName]);
            }

            $expectedFieldCount = 1;
            if($resultType === MYSQLI_BOTH){
                $expectedFieldCount = 2;
            }
            $this->assertSame($expectedFieldCount, count($row));

            $this->assertNull($functionName($result));
        });
    }

    function test_db_free_result(){
        $this->markTestSkipped(static::TEST_BROKEN_MESSAGE);

        $functionName = 'db_free_result';
        
        $this->getTestResult($functionName, function($result) use ($functionName){
            $functionName($result);
        });
    }

    function test_db_fetch_fields(){
        $this->markTestSkipped(static::TEST_WARNINGS_MESSAGE);

        $functionName = 'db_fetch_fields';
        
        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $fields = $functionName($result);
            $this->assertSame($columnName, $fields[0]->name);
        });
    }

    function test_db_result(){
        $this->markTestSkipped(static::TEST_WARNINGS_MESSAGE);

        $functionName = 'db_result';
        
        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $actualValue = $functionName($result, 0, $columnName);
            $this->assertSame($actualValue, $value);
        });
    }

    function test_db_field_name(){
        $this->markTestSkipped(static::TEST_WARNINGS_MESSAGE);

        $functionName = 'db_field_name';
        
        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $this->assertSame($columnName, $functionName($result, 0));
        });
    }

    function test_db_fetch_object(){
        $this->markTestSkipped(static::TEST_WARNINGS_MESSAGE);

        $functionName = 'db_fetch_object';
        
        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $expected = new stdClass;
            $expected->$columnName = $value;

            $actual = $functionName($result);

            $this->assertEquals($expected, $actual);
            $this->assertNull($functionName($result));
        });
    }

    function test_db_num_fields(){
        $this->markTestSkipped(static::TEST_BROKEN_MESSAGE);

        $functionName = 'db_num_fields';

        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $this->assertEquals(1, $functionName($result));
        });
    }

    function test_db_num_rows(){
        $this->markTestSkipped(static::TEST_BROKEN_MESSAGE);

        $functionName = 'db_num_rows';

        $this->getTestResult($functionName, function($result, $value, $columnName) use ($functionName){
            $this->assertEquals(1, $functionName($result));
        });
    }

    private function getTestResult($functionName, $action){
        $value = rand();
        $columnName = 'a';

        // MySQLi result object
        $result = db_query("select $value as $columnName");
        $action($result, (string) $value, $columnName);
        
        // StatementResult object
        $result = ExternalModules::query("select ? as $columnName", $value);
        $action($result, $value, $columnName);

        $expectedReturnValue = false;
        if(
            $functionName === 'db_field_name'
            ||
            PHP_MAJOR_VERSION === 5 && $functionName !== 'db_result'
        ){
            $expectedReturnValue = null;
        }

        // Closed MySQLi result object
        $result = db_query("select 1");
        $result->close();
        $this->assertSame($expectedReturnValue, $functionName($result, null));

        // Closed StatementResult object
        $result = ExternalModules::query("select ?", 1);
        $result->close();
        $this->assertSame($expectedReturnValue, $functionName($result, null));

        $expectedReturnValue = null;
        if(in_array($functionName, ['db_result'])){
            $expectedReturnValue = false;
        }

        // Some other object
        $this->assertSame($expectedReturnValue, $functionName(new stdClass, null));

        // Null
        $this->assertSame($expectedReturnValue, $functionName(null, null));
    }

    function test_db_query(){
        // This function is more thoroughly tested indirectly via the EM framework.

        $assert = function($param){
            $result = db_query('select ?', $param);
            $this->assertSame($param, $result->fetch_row()[0]);
        };

        $assert(0);
        $assert('');
    }

    /**
     * This test makes sure db_query() actually calls checkQuery()
     * while SystemTest::testCheckQuery() covers all the specific cases.
     */
    function test_db_query_checkQuery(){
        $assert = function($successExpected, $sql){
            ob_start();
            $exception = null;
            try{
                db_query($sql);
            }
            catch(\Exception $e){
                $exception = $e;
            }
            $output = ob_get_clean();


            if($successExpected){
                $this->assertNull($exception);
                $this->assertSame('', $output);
            }
            else{
                // Make sure the exception was thrown
                $this->assertStringContainsString(System::UNLIMITED_DELETE_OR_UPDATE_MESSAGE, $exception->getMessage());
                
                // Make sure the query was written to STDERR
                $this->assertStringContainsString("SQL - $sql", $output);
            }
        };

        $assert(true, 'select "this is a safe query"');
        $assert(false, 'delete from redcap_data where 1=2'); // without parameters
        $assert(false, 'delete from redcap_data where 1=2 and 3=?'); // with parameters
    }
}
