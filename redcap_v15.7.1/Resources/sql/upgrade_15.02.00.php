<?php

// Revise OpenAI Endpoint URLs to contain model and remove model setting at system-level and project-level
$SystemSettingImport = $ProjectSettingImport = "";
$sql1 = "SELECT * FROM redcap_config WHERE field_name = 'openai_endpoint_url' OR field_name = 'openai_chat_model'";
$q1 = db_query($sql1);
$openai_chat_model = $openai_endpoint_url = "";
while ($row = db_fetch_assoc($q1)) {
    if ($row['field_name'] == 'openai_endpoint_url') {
        $openai_endpoint_url = $row['value'];
    }
    if ($row['field_name'] == 'openai_chat_model') {
        $openai_chat_model = $row['value'];
    }
}
if ($openai_endpoint_url != '') {
    // Ensure URL ends with a slash
    if (substr($openai_endpoint_url, -1) != '/') {
        $openai_endpoint_url .= '/';
    }
    $new_openai_endpoint_url = $openai_endpoint_url."openai/deployments/".$openai_chat_model;
    $SystemSettingImport .= "UPDATE redcap_config SET value = '" . db_escape($new_openai_endpoint_url) . "' WHERE field_name = 'openai_endpoint_url';\n";
}

// Get all values for each project and transform
$sql2 = "SELECT * FROM redcap_projects";
$q2 = db_query($sql2);
while ($row = db_fetch_assoc($q2))
{
    $pid = $row['project_id'];
    $openai_endpoint_url = $row['openai_endpoint_url_project'];
    $chat_model = $row['openai_chat_model_project'];
    if ($openai_endpoint_url != '') {
        // Ensure URL ends with a slash
        if (substr($openai_endpoint_url, -1) != '/') {
            $openai_endpoint_url .= '/';
        }
        $new_openai_endpoint_url = $openai_endpoint_url."openai/deployments/".$chat_model;
        $ProjectSettingImport .= "UPDATE redcap_projects SET openai_endpoint_url_project = '" . db_escape($new_openai_endpoint_url) . "' WHERE project_id = '" . $pid . "';\n";
    }
}


$sql = "
$SystemSettingImport
$ProjectSettingImport

-- Remove OpenAI Chat Model setting from redcap_config and redcap_projects table
ALTER TABLE `redcap_projects` DROP `openai_chat_model_project`;
DELETE FROM `redcap_config` WHERE `field_name` = 'openai_chat_model';

-- Add Gemini config settings
ALTER TABLE `redcap_projects` 
    ADD `geminiai_api_key_project` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `openai_api_version_project`,
    ADD `geminiai_api_model_project` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `geminiai_api_key_project`,
    ADD `geminiai_api_version_project` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `geminiai_api_model_project`;
REPLACE INTO redcap_config (field_name, value) VALUES ('geminiai_api_key', '');
REPLACE INTO redcap_config (field_name, value) VALUES ('geminiai_api_model', '');
REPLACE INTO redcap_config (field_name, value) VALUES ('geminiai_api_version', '');

-- Add Twilio setting
ALTER TABLE `redcap_projects` ADD `twilio_alphanum_sender_id` VARCHAR(50) NULL DEFAULT NULL AFTER `twilio_from_number`;
";


print $sql;