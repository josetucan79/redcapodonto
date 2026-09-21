<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStatsCollector;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// log FHIR stats for CDP projects
if($DDP->realtime_webservice_type==='FHIR') {
    $fhirStatsCollector = new FhirStatsCollector($DDP->project_id, FhirStatsCollector::REDCAP_TOOL_TYPE_CDP);
    $DDP->attach($fhirStatsCollector, DynamicDataPull::NOTIFICATION_DATA_COLLECTED_FOR_SAVING);
    $DDP->attach($fhirStatsCollector, DynamicDataPull::NOTIFICATION_DATA_SAVED_FOR_ALL_EVENTS);
}
// Save adjudicated data from RTWS and output success message
print $DDP->saveAdjudicatedData($_GET['record'], $_GET['event_id'], $_POST);