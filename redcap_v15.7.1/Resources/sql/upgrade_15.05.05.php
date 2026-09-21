<?php

$sql = "
ALTER TABLE `redcap_mycap_participants` DROP INDEX `project_id`, ADD INDEX `project_record_event` (`project_id`, `record`, `event_id`);
ALTER TABLE `redcap_rewards_permissions` CHANGE `name` `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL;
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print $sql;