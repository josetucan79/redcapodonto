<?php

$sql = "
ALTER TABLE `redcap_cde_field_mapping` ADD `org_selected` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `steward`;
ALTER TABLE `redcap_cde_field_mapping` ADD INDEX `org_project` (`org_selected`, `project_id`);
ALTER TABLE `redcap_cde_field_mapping` ADD INDEX `steward_project` (`steward`, `project_id`);
ALTER TABLE `redcap_cde_field_mapping` DROP INDEX `project_steward`;
-- Fix alerts bug
update redcap_alerts 
	set email_repetitive = 0, email_repetitive_change = 0, email_repetitive_change_calcs = 0
	where form_name is null and (email_repetitive = 1 or email_repetitive_change = 1 or email_repetitive_change_calcs = 1);
";

print $sql;