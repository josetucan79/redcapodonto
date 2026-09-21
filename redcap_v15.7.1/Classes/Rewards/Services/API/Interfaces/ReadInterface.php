<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces;

/**
 * Interface for reading records.
 */
interface ReadInterface {

    /**
     * Read a record by ID.
     *
     * @param integer $id
     * @return Object|false
     */
    public function read(int $id);
}