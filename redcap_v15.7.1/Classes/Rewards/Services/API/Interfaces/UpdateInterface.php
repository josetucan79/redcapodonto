<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\API\Interfaces;

/**
 * Interface for updating records.
 */
interface UpdateInterface {

    /**
     * Update an existing record.
     *
     * @param integer $id
     * @param array $data
     * @return mixed
     */
    public function update(int $id, array $data);
}
