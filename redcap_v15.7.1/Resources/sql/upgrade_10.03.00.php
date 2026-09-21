<?php

$sql = "
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) VALUES
('ProcessQueue', 'Process queue with a worker.', 'ENABLED', 60, 3600, 5, 0, NULL, 0, NULL);
CREATE TABLE `redcap_queue` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`data` blob DEFAULT NULL,
`status` enum('ready','processing','completed','error','canceled') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` datetime DEFAULT NULL,
`updated_at` datetime DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

print $sql;