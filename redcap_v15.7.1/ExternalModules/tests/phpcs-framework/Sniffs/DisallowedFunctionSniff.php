<?php namespace ExternalModules\Sniffs\Misc;

use ExternalModules\ExternalModules;
use PHP_CodeSniffer\Files\File;

class DisallowedFunctionSniff extends AbstractReferenceCountSniff
{
    const EXPECTED_REFERENCES = [
        'db_query' => 1, // All other calls should use ExternalModules::query() or $module->query() to encourage parameter use
        'EDOC_PATH' => 1, // All other calls should use ExternalModules::getEdocPath() to ensure that getSafePath() is used.
        'USERID' => 1, // All other calls should use ExternalModules::getUsername() to ensure impersonation is used when appropriate.
        'SUPER_USER' => 3, // All other calls should use ExternalModules::isSuperUser() to ensure impersonation is respected.
        'GLOB_BRACE' => 0, // We should avoid using this because it is not available on some systems (see note in PHP docs)
        'error_log' => 1, // All other calls should use ExternalModules::errorLog() to ensure that long logs are chunked.
        
        /**
         * There's a good chance new calls should be referencing getFrameworkInstance() instead.
         * Minimizing the number of places getModuleInstnace() is called significantly reduces
         * the risk of module code crashing REDCap due to bugs, PHP version compatibility issues, etc.
         * Generally getFrameworkInstance() can & should be used instead for framework provided operations.
         */
        'getModuleInstance' => 30,
        
        'die' => 0, // Please call exit() instead for consistency.

        /**
         * Make sure any new exit() calls are appropriate before incrementing this.
         * Exit calls are unsafe within hooks and any framework methods hooks might call
         * because exiting in the middle of a hook prevents other modules from executing for that hook.
         * Modules themselves can use exitAfterHook() to delay the exit call.
         * Within the framework, please use `return` instead when possible
         * to reduce the number of `exit` calls required to troubleshoot when
         * tracking down difficult to diagnose crashes.  It also simplifies unit testing.
         */
        'exit' => 16,

        /**
         * In almost all new cases, the getEnabledVersion() method should be used to retrieve the version from the in memory cache.
         * If either of the following are used in the wrong context, it will cause problems
         * (like requests or cron jobs crashing if a module is updated while they're in progress).
         */
        'KEY_VERSION' => 9,
        'getModuleVersionByPrefix' => 2,
        'db_affected_rows' => 1, // Does not work with prepared statements.  The Query class should be used instead.

        /**
         * If any new instances of these are added, we should make sure they're output
         * is only written to the log, and never returned via a web request.
         */
        'debug_backtrace' => 1 ,
        'debug_print_backtrace' => 0 ,
    ];

    private $errorsByFunction = [];
    
    function __construct(){
        parent::__construct(self::EXPECTED_REFERENCES);

        $this->addErrors(
            [
                '_query',
                '_multi_query',
                '_multi_query_rc'
            ],
            'does not support query parameters.  Please use ExternalModules::query() or $module->query() instead.'
        );

        $this->addErrors(
            [
                '_affected_rows'
            ],
            'will not work with prepared statements.  Please see the External Module query documentation for an alternative.'
        );
    }

    private function addErrors($suffixes, $error){
        foreach(['db', 'mysql', 'mysqli'] as $prefix){
            foreach($suffixes as $suffix){
                $this->errorsByFunction[$prefix.$suffix] = $error;
            }
        }
    }

    function register()
    {
        return [T_STRING, T_EXIT];
    }

    function process(File $file, $position)
    {
        if($this->isTest($file)){
            return;
        }

        $string = $file->getTokens()[$position]['content'];

        $referenceLimit = @self::EXPECTED_REFERENCES[$string];
        if($referenceLimit !== null){
            $this->countReference($string);
        }
        else{
            $error = @$this->errorsByFunction[$string];
            if($error){
                $file->addError("The '$string' function is not allowed since it $error", $position, 'Found');
            }
        }
    }

    private function isTest(File $file){
        return str_starts_with($file->getFilename(), APP_PATH_EXTMOD . 'tests');
    }
}