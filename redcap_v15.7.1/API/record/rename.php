<?php
global $format, $returnFormat, $post;

$content = REDCap::renameRecord(PROJECT_ID, $post['record'], $post['new_record_name'], $post['arm']??null);

# Logging
Logging::logEvent("", "redcap_data", "UPDATE", $post['new_record_name'],
                "old_record = '{$post['record']}',\nnew_record = '{$post['new_record_name']}'".(isset($post['arm']) ? ",\narm=".$post['arm'] : ""),
                "Rename Record (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);