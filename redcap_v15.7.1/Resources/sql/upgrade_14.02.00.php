<?php

$sql = "
CREATE TABLE `redcap_project_dashboards_folders` (
`folder_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`position` smallint(4) DEFAULT NULL,
PRIMARY KEY (`folder_id`),
UNIQUE KEY `position_project_id` (`position`,`project_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_project_dashboards_folders_items` (
`folder_id` int(10) DEFAULT NULL,
`dash_id` int(10) DEFAULT NULL,
UNIQUE KEY `folder_id_dash_id` (`folder_id`,`dash_id`),
KEY `dash_id` (`dash_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_project_dashboards_folders`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_project_dashboards_folders_items`
ADD FOREIGN KEY (`folder_id`) REFERENCES `redcap_project_dashboards_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`dash_id`) REFERENCES `redcap_project_dashboards` (`dash_id`) ON DELETE CASCADE ON UPDATE CASCADE;

REPLACE INTO redcap_config (field_name, value) VALUES ('user_custom_expiration_message', '');
REPLACE INTO redcap_config (field_name, value) VALUES ('user_with_sponsor_custom_expiration_message', '');
";

print $sql;