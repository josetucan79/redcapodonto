<?php

$sql = <<<EOF
set @reports_allow_public = (select value from redcap_config where field_name = 'reports_allow_public');
REPLACE INTO redcap_config (field_name, value) VALUES ('reports_allow_public', if (@reports_allow_public is null, '1', trim(@reports_allow_public)));

ALTER TABLE `redcap_reports`
	ADD `hash` VARCHAR(16) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT NULL,
	ADD `short_url` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
	ADD `is_public` TINYINT(1) NOT NULL DEFAULT '0',
	ADD `report_display_include_repeating_fields` TINYINT(1) NOT NULL DEFAULT '1',
	ADD `report_display_header` ENUM('LABEL','VARIABLE','BOTH') NOT NULL DEFAULT 'BOTH',
	ADD `report_display_data` ENUM('LABEL','RAW','BOTH') NOT NULL DEFAULT 'BOTH';
ALTER TABLE `redcap_todo_list` 
    ADD `todo_type_id` INT(11) NULL DEFAULT NULL AFTER `todo_type`,
	DROP INDEX `project_id`, 
	ADD UNIQUE `project_id_todo_type_id` (`project_id`, `todo_type`, `todo_type_id`);
EOF;

print $sql;