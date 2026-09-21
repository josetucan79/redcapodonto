<?php

$sql = "
CREATE TABLE `redcap_cde_cache` (
`tinyId` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`steward` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`choices` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`updated_on` datetime DEFAULT NULL,
PRIMARY KEY (`tinyId`),
KEY `steward` (`steward`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_cde_field_mapping` (
`project_id` int(10) DEFAULT NULL,
`field_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`tinyId` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`steward` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
UNIQUE KEY `project_field` (`project_id`,`field_name`),
KEY `project_steward` (`project_id`,`steward`),
KEY `tinyId_project` (`tinyId`,`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_cde_field_mapping`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";

print $sql;