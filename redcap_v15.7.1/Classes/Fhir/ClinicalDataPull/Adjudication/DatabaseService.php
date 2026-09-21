<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication;

class DatabaseService
{
    private $projectId;

    public function __construct($projectId)
    {
        $this->projectId = $projectId;
    }

    public function updateItemCount($record, $count)
    {
        // Update the item count for the record in the database
        $sql = "UPDATE redcap_ddp_records SET item_count = $count WHERE record = ? AND project_id = ?";
        return db_query($sql, [$record, $this->projectId]);
    }

}
