<?php

use Vanderbilt\REDCap\Classes\Cache\REDCapCache;
use Vanderbilt\REDCap\Classes\Cache\CacheFactory;
use Vanderbilt\REDCap\Classes\Cache\States\DisabledState;
use Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies\ProjectActivityInvalidation;

global $format, $returnFormat, $post;

// If user has "No Access" export rights, then return error
if ($post['export_rights'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you have "No Access" data export rights. Higher level data export rights are required for this operation.'));
}

// Get project attributes
$Proj = new Project();

// Get user rights
$user_rights_proj_user = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights_proj_user[PROJECT_ID][strtolower(USERID)];
$ur = new UserRights();
$user_rights = $ur->setFormLevelPrivileges($user_rights);
unset($user_rights_proj_user);

// De-Identification settings
$hashRecordID = (isset($user_rights['forms_export'][$Proj->firstForm]) && $user_rights['forms_export'][$Proj->firstForm] > 1 && $Proj->table_pk_phi);

// Ensure that this report_id belongs to this project
$allReports = DataExport::getReports(null, array(), array(), PROJECT_ID);
if (!isset($allReports[$post['report_id']])) {
    exit(RestUtility::sendResponse(403, 'The API request cannot complete because report_id='.RCView::escape($post['report_id']).' does not belong to this project.'));
}

## Rapid Retrieval: Cache salt
// Use some user privileges as additional salt for the cache
$cacheManager = CacheFactory::manager(PROJECT_ID);
$cacheOptions = [REDCapCache::OPTION_INVALIDATION_STRATEGIES => [ProjectActivityInvalidation::signature(PROJECT_ID)]];
$cacheOptions[REDCapCache::OPTION_SALT] = [];
$cacheOptions[REDCapCache::OPTION_SALT][] = ['dag'=>$user_rights['group_id']];
// Generate a form-level access salt for caching purposes: Create array of all forms represented by the report's fields
$reportAttr = DataExport::getReports($post['report_id']);
$reportFields = $reportAttr['fields'] ?? [];
$reportForms = [];
foreach ($reportFields as $thisField) {
    $thisForm = $Proj->metadata[$thisField]['form_name'];
    if (isset($reportForms[$thisForm])) continue;
    $reportForms[$thisForm] = true;
}
$reportFormsAccess = array_intersect_key($user_rights['forms_export'], $reportForms);
$reportFormsAccessSalt = [];
foreach ($reportFormsAccess as $thisForm => $thisAccess) {
    $reportFormsAccessSalt[] = "$thisForm:$thisAccess";
}
$cacheOptions[REDCapCache::OPTION_SALT][] = ['form-export-rights' => implode(",", $reportFormsAccessSalt)];
// If the report has filter logic containing datediff() with today or now, then add more salt since these will cause different results with no data actually changing.
if (strpos($reportAttr['limiter_logic'], 'datediff') !== false) {
    list ($ddWithToday, $ddWithNow) = containsDatediffWithTodayOrNow($reportAttr['limiter_logic']);
    if ($ddWithNow) $cacheManager->setState(new DisabledState());  // disable the cache since will never be used
    elseif ($ddWithToday) $cacheOptions[REDCapCache::OPTION_SALT][] = ['datediff'=>TODAY];
}
// If the report has filter logic containing a [user-X] smart variable, then add the USERID to the salt
if (strpos($reportAttr['limiter_logic'], '[user-') !== false) {
    $cacheOptions[REDCapCache::OPTION_SALT][] = ['user'=>USERID];
}

// Export the data for this report
$content = $cacheManager->getOrSet([DataExport::class, 'doReport'], [
    $post['report_id'], 'export', $format, ($post['rawOrLabel'] == 'label'), ($post['rawOrLabelHeaders'] == 'label'),
    false, false, null, $hashRecordID, null, null, null, false, false, array(), array(), false, $post['exportCheckboxLabel'],
    false, true, true, "", "", "", false, (isset($post['csvDelimiter']) ? $post['csvDelimiter'] : ","),
    (isset($post['decimalCharacter']) ? $post['decimalCharacter'] : null), array(),
    false, true, false, false, false, true, true
], $cacheOptions);

// Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);