<?php

$sql = "
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('DbHealthCheck', 'Kill any long-running database queries and check percentage of database connections being used', 'ENABLED', 120, 180, 1, 0, NULL, 0, NULL);
";

print $sql;