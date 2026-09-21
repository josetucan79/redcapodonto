<?php namespace ExternalModules\Sniffs\Misc;
use ExternalModules\SniffMessages;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class HardcodedTableSniff implements Sniff{

    function register()
    {
        return [
            T_CONSTANT_ENCAPSED_STRING, // strings without variables
            T_DOUBLE_QUOTED_STRING, // strings containing variables
        ];
    }

    function process(File $file, $position)
    {
        $testsDir = realpath(__DIR__ . '/../../');
        if(strpos(realpath($file->path), $testsDir) === 0){
            // Ignore redcap_data references in tests.
            return;
        }
        
        $content = $file->getTokens()[$position]['content'];
        $tableName = SniffMessages::getHardcodedTableName($content);
        if($tableName === 'redcap_data'){
            $file->addWarning("A 'redcap_data' table name is hardcoded.  This will prevent your module from finding data on newer projects.  Please add the following method to your module and use it instead of hardcoding 'redcap_data' in queries.  If your module's 'redcap-version-min' can be set to at least 14.0.0, you do not need to add this method as it will already be available:\n\n" .
            "    public function getDataTable(\$pid) {\n" .
            "        return method_exists('\REDCap', 'getDataTable') ?\n" .
            "            \REDCap::getDataTable(\$pid) : 'redcap_data';\n" .
            "    }\n\n", $position, 'Found');
        }
        else if($tableName === 'redcap_log_event'){
            $file->addWarning("A 'redcap_log_event' table name is hardcoded.  This will prevent your module from finding logs on newer projects.  Please call '\$module->getProject()->getLogTable()' instead of hardcoding 'redcap_log_event' in queries.", $position, 'Found');
        }
        else if($tableName !== null){
            throw new \Exception('An unsupported table name was returned');
        }
    }
}
