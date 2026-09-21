<?php namespace ExternalModules\Sniffs\Misc;

class NewFunctionsSniff extends \PHPCompatibility\Sniffs\FunctionUse\NewFunctionsSniff{
    public function register()
    {
        foreach([
            'is_countable',
            'hrtime',
            'array_key_first',
            'array_key_last',
        ] as $name){
            unset($this->newFunctions[$name]);
        }

        return parent::register();
    }
}