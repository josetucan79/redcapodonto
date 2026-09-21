<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\DTOs;

trait RecordLinkTrait {
    function getLink($project_id, $record=null) {
        $record = $this->record ?? $record;
        $link = APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . "/DataEntry/record_home.php?pid=$project_id&id=$record";
        return $link;
    }
}