<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces;

/**
 * Interface for creating records.
 */
interface CreateInterface {

    /**
     * Create a new record.
     *
     * @param array $data
     * @return integer|string|null
     */
    public function create(array $data);
}