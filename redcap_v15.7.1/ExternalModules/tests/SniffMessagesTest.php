<?php
namespace ExternalModules;

class SniffMessagesTest extends BaseTest
{
    function testGetHardcodedTableName(){
        $assert = function($expected, $s, $testWithAppendedNumbers=true){
            $this->assertSame($expected, SniffMessages::getHardcodedTableName($s));

            if($testWithAppendedNumbers){
                $s = str_replace('redcap_data', 'redcap_data2', $s);
                $this->assertSame($expected, SniffMessages::getHardcodedTableName($s));
    
                $s = str_replace('redcap_data2', 'redcap_data20', $s);
                $this->assertSame($expected, SniffMessages::getHardcodedTableName($s));
            }
        };

        $assert('redcap_data', 'redcap_data');
        $assert('redcap_data', ' redcap_data');
        $assert('redcap_data', 'redcap_data ');
        $assert('redcap_data', '.redcap_data');
        $assert('redcap_data', 'redcap_data)');

        $assert(null, 'redcap_data_');
        $assert(null, '_redcap_data');
        $assert(null, '^redcap_data'); // Ignore data table regexes like from https://github.com/ctsit/move_data_to_other_event
        
        // Ignore object type column references like from https://github.com/vanderbilt-redcap/redcap_log_audit_tool
        $assert(null, "object_type = 'redcap_data'", false);
        $assert(null, 'object_type = "redcap_data"', false);
        $assert(null, "object_type in ('redcap_data')", false);
        $assert(null, 'object_type in ("redcap_data")', false);

        /**
         * If the entire string is just a table name, rather than a larger string containing one,
         * it is likely a fallback for older REDCap versions where getDataTable() didn't exist yet,
         * like the following:
         * 		return method_exists('\REDCap', 'getDataTable') ? \REDCap::getDataTable($project_id) : "redcap_data";
         * 
         * Do NOT consider this a hardcoded table name.
         * Strings are wrapped in quotes because that is the way they are passed in from phpcs.
         */
        $assert(null, '"redcap_data"', false);
        $assert(null, "'redcap_data'", false);
        $assert(null, '"redcap_log_event"', false);
        $assert(null, "'redcap_log_event'", false);
    }
}