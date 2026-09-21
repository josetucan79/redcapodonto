<?php

$sql = "
-- Add config settings for OpenID Connect authentication
REPLACE INTO redcap_config (field_name, value) VALUES 
('openid_connect_primary_admin', ''),
('openid_connect_secondary_admin', ''),
('openid_connect_provider_url', ''),
('openid_connect_metadata_url', ''),
('openid_connect_client_id', ''),
('openid_connect_client_secret', '');
";

// Add S3 endpoint custom URL
$sql .= "
ALTER TABLE `redcap_surveys` 
    ADD `survey_width_percent` INT(3) NULL DEFAULT NULL,
    ADD `survey_show_font_resize` tinyint(1) NOT NULL DEFAULT '1',
    ADD `survey_btn_text_prev_page` text NULL DEFAULT NULL,
    ADD `survey_btn_text_next_page` text NULL DEFAULT NULL,
    ADD `survey_btn_text_submit` text NULL DEFAULT NULL,
    ADD `survey_btn_hide_submit` tinyint(1) NOT NULL DEFAULT '0',
    ADD `survey_btn_hide_submit_logic` text NULL DEFAULT NULL;
";

// New table and config setting
$sql .= "
-- New setting to enable Database Query Tool
set @database_query_tool_enabled = (select value from redcap_config where field_name = 'database_query_tool_enabled');
REPLACE INTO redcap_config (field_name, value) VALUES ('database_query_tool_enabled', if (@database_query_tool_enabled is null, '0', trim(@database_query_tool_enabled)));
-- New table for Database Query Tool
CREATE TABLE IF NOT EXISTS `redcap_custom_queries` (
`qid` int(10) NOT NULL AUTO_INCREMENT,
`title` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`query` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`qid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Auto-enable the Database Query Tool if the MySQL Simple Admin EM is currently enabled
$sql2 = "SELECT 1 FROM redcap_external_modules e, redcap_external_module_settings s
         WHERE e.directory_prefix = 'mysql_simple_admin' AND e.external_module_id = s.external_module_id AND s.project_id IS NULL AND s.`key` = 'version';";
$q = db_query($sql2);
if ($q && db_num_rows($q)) {
    $sql .= "
-- Auto-enable the Database Query Tool if the MySQL Simple Admin EM is currently enabled
UPDATE redcap_config SET value = '1' WHERE field_name = 'database_query_tool_enabled';
";
}

// Migrate any saved queries from the MySQL Simple Admin EM
$MySqlSimpleAdminImport = "";
$sql2 = "SELECT DISTINCT t.`key`, t.`value` FROM redcap_external_modules e, redcap_external_module_settings s, redcap_external_module_settings t
		WHERE e.directory_prefix = 'mysql_simple_admin' AND e.external_module_id = s.external_module_id AND s.project_id IS NULL 
		AND s.`key` = 'version' AND t.external_module_id = s.external_module_id and t.`key` in ('title', 'query')";
$q = db_query($sql2);
$queryTitles = $querySql = [];
while ($row = db_fetch_assoc($q)) {
    $jsonArray = json_decode($row['value'], true);
    if ($row['key'] == 'title') {
        $queryTitles = $jsonArray;
    } else {
        $querySql = $jsonArray;
    }
}
if (!empty($queryTitles)) {
    $sql .= "delete from redcap_custom_queries;\n";
}
foreach ($queryTitles as $key=>$qtitle) {
    $qquery = $querySql[$key] ?? "[No title]";
    $sql .= "insert into redcap_custom_queries (`title`, `query`) values ('".db_escape($qtitle)."', '".db_escape($qquery)."');\n";
}

// Disable the MySQL Simple Admin EM
$sql .= "
-- Disable the MySQL Simple Admin EM
DELETE s.* FROM redcap_external_modules e, redcap_external_module_settings s
    WHERE e.directory_prefix = 'mysql_simple_admin' AND e.external_module_id = s.external_module_id AND s.project_id IS NULL AND s.`key` = 'version';
";

print $sql;