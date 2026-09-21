<?php namespace ExternalModules\Sniffs\Misc;

require_once __DIR__ . '/../SniffMessages.php';

use ExternalModules\SniffMessages;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class RequireNamespaceSniff implements Sniff
{
    private $pathsWithNamespaces = [];

    function register()
    {
        return [T_NAMESPACE, T_FUNCTION, T_CLASS, T_CONST];
    }

    function process(File $file, $position)
    {
        if(
            // No more checking is needed if a namespace is already set
            isset($this->pathsWithNamespaces[$file->path])
            ||
            // It's OK that some replaced classes don't have namespaces.
            str_starts_with(dirname($file->path), realpath(__DIR__ . '/../../../psalm/replaced-classes/'))
        ){
            return;
        }

        $token = $file->getTokens()[$position];
        if($token['code'] === T_NAMESPACE){
            $this->pathsWithNamespaces[$file->path] = true;
        }
        else{
            $file->addError(SniffMessages::MISSING_NAMESPACE, $position, 'Found');
        }
    }
}