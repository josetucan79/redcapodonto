<?php

$sql = "
-- added column for reference value hash, defaults to NULL
ALTER TABLE `redcap_multilanguage_ui` ADD `hash` CHAR(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `item`;
ALTER TABLE `redcap_multilanguage_ui_temp` ADD `hash` CHAR(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `item`;
-- Add config setting for default form-level full access for new instruments added while in production - value of 0 (default) or 1
set @new_form_default_prod_user_access = (select value from redcap_config where field_name = 'new_form_default_prod_user_access');
REPLACE INTO redcap_config (field_name, value) VALUES ('new_form_default_prod_user_access', if (@new_form_default_prod_user_access is null, '0', trim(@new_form_default_prod_user_access)));
";

// Drop all FKs for redcap_ehr_fhir_logs and re-add them
$q = db_query( "select constraint_name from information_schema.KEY_COLUMN_USAGE 
				where CONSTRAINT_SCHEMA = '{$GLOBALS['db']}' and TABLE_NAME = 'redcap_ehr_fhir_logs' 
                and referenced_column_name is not null");
while ($row = db_fetch_assoc($q)) {
    $constraint_name = $row["constraint_name"] ?? ($row["CONSTRAINT_NAME"] ?? "");
    if ($constraint_name == '') continue;
    $sql .= "ALTER TABLE `redcap_ehr_fhir_logs` DROP FOREIGN KEY `{$constraint_name}`;\n";
}
$sql .= "
ALTER TABLE `redcap_ehr_fhir_logs`
    DROP INDEX project_id_mrn,
    DROP INDEX user_project_mrn_resource,
    DROP `mrn`,
    CHANGE `project_id` `project_id` INT(11) NULL DEFAULT NULL COMMENT 'project ID is NULL during an EHR launch',
    CHANGE `fhir_id` `fhir_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD INDEX project_id_fhir_id  (`project_id`,`fhir_id`),
    ADD INDEX user_project_fhir_id_resource (`user_id`,`project_id`,`fhir_id`,`resource_type`),
    ADD `environment` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CRON or direct user request' AFTER `status`;
ALTER TABLE `redcap_ehr_fhir_logs`
    ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD FOREIGN KEY (`user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";

print $sql;