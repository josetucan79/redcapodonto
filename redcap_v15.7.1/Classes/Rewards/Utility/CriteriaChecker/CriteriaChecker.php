<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker;

use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\CriterionResult;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\AbstractCriterion;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\CriterionInterface;

/**
 * Class CriteriaChecker
 *
 * This class is responsible for managing and executing a collection of criteria checks.
 * It allows the addition of multiple criteria that conform to the CriterionInterface
 * and provides a method to check all criteria, returning the results.
 */
class CriteriaChecker {
    /**
     * @var CriterionInterface[] $criteria An array of CriterionInterface objects.
     */
    private $criteria = [];

    /**
     * Adds a criterion to the checker.
     *
     * @param CriterionInterface $criterion The criterion to add.
     * @return $this Returns the current instance for method chaining.
     */
    public function add($criteria) {
        $this->criteria[] = $criteria;
        return $this;
    }

    /**
     *
     * @param array $lang
     * @return self
     */
    public function applyLang(array $lang) {
        foreach ($this->criteria as $criterion) {
            if ($criterion instanceof AbstractCriterion) {
                $criterion->setLang($lang);
            }
        }
        return $this;
    }

    /**
     * Checks all added criteria.
     *
     * @return CriterionResult[] An array of CriterionResult objects representing the results of each criterion check.
     */
    public function checkAll() {
        $results = [];
        foreach ($this->criteria as $criterion) {
            // Trigger the check method to populate errors
            $isValid = $criterion->check();
            
            $results[] = new CriterionResult(
                $criterion->getTitle(),
                $criterion->getDescription(),
                $isValid,
                $criterion->getErrors()
            );
        }
        return $results;
    }
}
