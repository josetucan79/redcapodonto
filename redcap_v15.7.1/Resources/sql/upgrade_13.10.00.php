<?php

$sql = "
-- Add new table
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS redcap_mycap_tasks_schedules;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `redcap_mycap_tasks_schedules` (
`ts_id` int NOT NULL AUTO_INCREMENT,
`task_id` int DEFAULT NULL,
`event_id` int DEFAULT NULL,
PRIMARY KEY (`ts_id`),
KEY `task_id` (`task_id`),
KEY `event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_mycap_tasks_schedules`
ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`task_id`) REFERENCES `redcap_mycap_tasks` (`task_id`) ON DELETE CASCADE ON UPDATE CASCADE;


-- add event_id to mycap participants table
ALTER TABLE `redcap_mycap_participants`
	ADD `event_id` int(10) DEFAULT NULL AFTER `record`;

ALTER TABLE `redcap_mycap_participants`
    ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;


-- add event_id to mycap sync issues table
ALTER TABLE `redcap_mycap_syncissues`
	ADD `event_id` int(10) DEFAULT NULL AFTER `instrument`,
	ADD KEY `event_id` (`event_id`);

ALTER TABLE `redcap_mycap_syncissues`
    ADD FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;


-- add converted_to_flutter to mycap projects table
ALTER TABLE `redcap_mycap_projects`
	ADD `converted_to_flutter` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Participant join URL will display flutter app link if TRUE';
	
-- new config value
replace into redcap_config (field_name, value) values ('test_email_address', 'redcapemailtest@gmail.com');
";

print $sql;