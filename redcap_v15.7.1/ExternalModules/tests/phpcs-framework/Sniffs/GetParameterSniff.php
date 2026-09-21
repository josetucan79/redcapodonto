<?php namespace ExternalModules\Sniffs\Misc;

require_once __DIR__ . '/../AbstractGlobalArrayParameterSniff.php';

class GetParameterSniff extends AbstractGlobalArrayParameterSniff
{
    function __construct(){
        parent::__construct([
            'pid' => 2, // Use getProjectId() & setProjectId() instead to ensure proper & consistent input sanitization in all places.
            'prefix' => 3, // Use getPrefix() & setPrefix() instead to ensure proper & consistent input sanitization in all places.
        ]);
    }

    function getArrayName(){
        return '$_GET';
    }
}