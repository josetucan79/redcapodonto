<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces;

/**
 * Interface for deleting records.
 */
interface DeleteInterface {

    /**
     * Delete a record by ID.
     *
     * @param integer $id
     * @return boolean
     */
    public function delete(int $id);
}