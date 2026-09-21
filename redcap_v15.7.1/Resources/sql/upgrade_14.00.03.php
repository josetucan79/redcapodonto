<?php

$sql = "
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('UnicodeFixProjectLevel', 'Perform unicode transformation for all projects one at a time.', 'DISABLED', 60, 3600, 1, 0, NULL, 0, NULL);
ALTER TABLE `redcap_user_rights` ADD `api_modules` int(1) NOT NULL DEFAULT '0' AFTER `api_import`;
ALTER TABLE `redcap_user_roles` ADD `api_modules` int(1) NOT NULL DEFAULT '0' AFTER `api_import`;
";

print $sql;