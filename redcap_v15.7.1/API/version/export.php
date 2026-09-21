<?php
global $post;

// Logging
if(defined("PROJECT_ID")) Logging::logEvent("", "redcap_config", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export REDCap version (API$playground)");
else Logging::logEvent("", "redcap_config", "MANAGE", "", "", "Export REDCap version (API$playground)");

// Send the response to the requestor
RestUtility::sendResponse(200, $redcap_version, 'csv');