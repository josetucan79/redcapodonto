<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Exception;

class CriterionResult {
    private $title;
    private $description;
    private $isMet;
    private $errors;

    /**
     * CriterionResult constructor.
     *
     * @param string $title The title of the criterion.
     * @param string $description A human-readable description of the criterion.
     * @param bool $isMet Indicates whether the criterion was met.
     * @param Exception[] $errors Optional. An array of errors encountered during the check.
     */
    public function __construct($title, $description, $isMet, $errors=[]) {
        $this->title = $title;
        $this->description = $description;
        $this->isMet = $isMet;
        $this->errors = $errors;
    }

    /**
     * Gets the title of the criterion.
     *
     * @return string The title of the criterion.
     */
    public function getTitle() {
        return $this->title;
    }

    /**
     * Gets the description of the criterion.
     *
     * @return string The description of the criterion.
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * Indicates whether the criterion was met.
     *
     * @return bool True if the criterion was met, false otherwise.
     */
    public function isMet() {
        return $this->isMet;
    }

    /**
     * Gets the errors related to the criterion.
     *
     * @return Exception[] An array of errors, or an empty array if there are no errors.
     */
    public function getErrors() {
        return $this->errors;
    }
}
