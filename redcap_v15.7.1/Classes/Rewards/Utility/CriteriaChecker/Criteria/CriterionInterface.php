<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Exception;

/**
 * Interface CriterionInterface
 *
 * This interface defines a contract for implementing specific criteria checks.
 * Each criterion implementing this interface should provide a `check` method
 * to determine if the criterion is met and a `getDescription` method to provide
 * a human-readable description of the criterion.
 */
interface CriterionInterface {
    
    /**
     * Checks whether the criterion is met.
     *
     * @return bool Returns true if the criterion is met, false otherwise.
     */
    public function check();

    /**
     * Provides a description of the criterion.
     *
     * @return string A human-readable description of the criterion.
     */
    public function getTitle();

    /**
     * Provides a description of the criterion.
     *
     * @return string A human-readable description of the criterion.
     */
    public function getDescription();

    /**
     * get the errors, if any
     *
     * @return Exception[]
     */
    public function getErrors();
}
