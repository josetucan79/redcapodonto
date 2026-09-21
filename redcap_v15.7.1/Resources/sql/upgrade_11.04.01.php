<?php

$sql = "";

// Remove index from redcap_user_roles (if exists) v11.2.0
if (Upgrade::getDecVersion(REDCAP_VERSION) > 110200) {
    $sql2 = "SHOW CREATE TABLE redcap_user_roles";
    $q = db_query($sql2);
    if ($q && db_num_rows($q) == 1) {
        // Get the 'create table' statement to parse
        $result = db_fetch_array($q);
        $createTableStatement = strtolower($result[1]);
        if (stripos($createTableStatement, "key `project_id` (`project_id`)") !== false) {
            $sql .= "ALTER TABLE `redcap_user_roles` DROP INDEX `project_id`;\n";
        }
    }
}

$sql .= <<<EOF
ALTER TABLE `redcap_projects` 
    ADD `protected_email_mode_logo` INT(10) NULL DEFAULT NULL AFTER `protected_email_mode_trigger`,
    ADD INDEX(`protected_email_mode_logo`),
    ADD FOREIGN KEY (`protected_email_mode_logo`) REFERENCES `redcap_edocs_metadata`(`doc_id`) ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO `redcap_config` (`field_name`, `value`) VALUES ('google_cloud_storage_api_bucket_name','');
INSERT INTO `redcap_config` (`field_name`, `value`) VALUES ('google_cloud_storage_api_project_id','');
INSERT INTO `redcap_config` (`field_name`, `value`) VALUES ('google_cloud_storage_api_service_account','');
INSERT INTO `redcap_config` (`field_name`, `value`) VALUES ('google_cloud_storage_api_use_project_subfolder','1');

INSERT INTO redcap_config (field_name, value) VALUES ('override_system_bundle_ca', '1');
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, 
cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('CDPAutoAdjudication', 'Automatically adjudicate data for Clinical Data Pull projects', 'ENABLED', 300, 3600, 1, 0, NULL, 0, NULL);
ALTER TABLE `redcap_projects` ADD `fhir_cdp_auto_adjudication_cronjob_enabled` tinyint(1) NOT NULL DEFAULT '0' 
    COMMENT 'If true, the cron job will auto adjudicate data in CDP projects' AFTER `fhir_cdp_auto_adjudication_enabled`;
EOF;


print $sql;