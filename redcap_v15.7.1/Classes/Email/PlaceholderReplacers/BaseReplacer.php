<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

abstract class BaseReplacer implements PlaceholderReplacerInterface {
    
    protected $value;

    public function __construct($value) {
        $this->value = $value;
    }

    abstract public static function token();

    public function replace($subject) {
        return str_replace('['.static::token().']', $this->value, $subject);
    }

}