<?php
global $format, $returnFormat, $post;
if (empty($post['dag'])) $post['dag'] = 0;
$module = new DAGSwitcher();
$content = $module->switchToDAG($post['dag']);

if ($content == 1) {
    $_SESSION['api_group_id'] = '';
}
# Logging
Logging::logEvent("", "redcap_data_access_groups_users", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Switch DAG (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);