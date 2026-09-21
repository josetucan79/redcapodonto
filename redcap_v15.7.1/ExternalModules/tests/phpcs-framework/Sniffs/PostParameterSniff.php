<?php namespace ExternalModules\Sniffs\Misc;

require_once __DIR__ . '/../AbstractGlobalArrayParameterSniff.php';

class PostParameterSniff extends AbstractGlobalArrayParameterSniff
{
    function __construct(){
        parent::__construct([
            'prefix' => 1, // Use getPrefixFromPost() instead
        ]);
    }
    
    function getArrayName(){
        return '$_POST';
    }
}