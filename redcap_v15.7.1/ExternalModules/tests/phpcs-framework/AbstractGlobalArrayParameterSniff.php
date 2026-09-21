<?php namespace ExternalModules\Sniffs\Misc;

use PHP_CodeSniffer\Files\File;

abstract class AbstractGlobalArrayParameterSniff extends AbstractReferenceCountSniff
{
    abstract function getArrayName();

    function register()
    {
        return [T_VARIABLE];
    }

    function process(File $file, $position)
    {
        $string = $file->getTokens()[$position]['content'];
        if($string === $this->getArrayName()){
            $paramNameToken = $file->getTokens()[$position+2];
            if($paramNameToken['type'] === 'T_CONSTANT_ENCAPSED_STRING'){
                $paramName = $paramNameToken['content'];
                $paramName = str_replace("'", "", $paramName);
                $paramName = str_replace('"', '', $paramName);

                $this->countReference($paramName);
            }
        }
    }
}