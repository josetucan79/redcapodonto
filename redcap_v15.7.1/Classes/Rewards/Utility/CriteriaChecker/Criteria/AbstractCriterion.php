<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Exception;

abstract class AbstractCriterion implements CriterionInterface {
    protected $lang = [];
    protected $errors = [];

    /**
     * Sets the translation array.
     *
     * @param array $lang The translation array.
     */
    public function setLang(array $lang) {
        $this->lang = $lang;
    }

    /**
     * add an error
     *
     * @param Exception $e
     * @return void
     */
    public function addError(Exception $e) {
        array_push($this->errors, $e);
    }

    /**
     * Checks whether the criterion is met.
     *
     * This method must be implemented by any subclass.
     *
     * @return bool Returns true if the criterion is met, false otherwise.
     */
    abstract public function check();

    /**
     * Provides a title of the criterion.
     *
     * This method must be implemented by any subclass.
     *
     * @return string A human-readable title of the criterion.
     */
    abstract public function getTitle();
    
    /**
     * Gets the description of the criterion and steps to take if not met.
     * 
     * This method must be implemented by any subclass.
     *
     * @return string The detailed description of the criterion.
     */
    abstract public function getDescription();

    /**
     * gets the errors
     *
     * @return Exception[]
     */
    public function getErrors() {
        return $this->errors;
    }
}
