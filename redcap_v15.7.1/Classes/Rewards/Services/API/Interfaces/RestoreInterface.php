<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces;

/**
 * Interface for recovering records.
 */
interface RestoreInterface {

    /**
     * Recover a deleted record by ID.
     *
     * @param integer $id
     * @return boolean
     */
    public function restore(int $id);
}