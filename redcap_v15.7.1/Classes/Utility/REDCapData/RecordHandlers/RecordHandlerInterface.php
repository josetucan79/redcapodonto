<?php
namespace Vanderbilt\REDCap\Classes\Utility\REDCapData\RecordHandlers;

interface RecordHandlerInterface
{
    /**
     * handle the given record data.
     *
     * @param string $record_id The ID of the current record.
     * @param array $record_data The data associated with the record.
     * @return array|null Modified record data or null to filter out the record.
     */
    public function handle(string $record_id, array $record_data): ?array;
}