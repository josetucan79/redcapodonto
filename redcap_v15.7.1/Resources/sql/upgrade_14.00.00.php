<?php

$sql = "
CREATE TABLE `redcap_cache` (
`cache_id` bigint(19) NOT NULL AUTO_INCREMENT,
`project_id` int(11) NOT NULL,
`cache_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
`data` longblob DEFAULT NULL,
`ts` datetime DEFAULT NULL,
`expiration` datetime DEFAULT NULL,
`invalidation_strategies` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`cache_id`),
UNIQUE KEY `project_id_cache_key` (`project_id`,`cache_key`),
KEY `cache_key` (`cache_key`),
KEY `expiration` (`expiration`),
KEY `ts` (`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_cache`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

REPLACE INTO redcap_config (field_name, value) VALUES ('cache_storage_system', 'file');
REPLACE INTO redcap_config (field_name, value) VALUES ('cache_files_filesystem_path', '');

CREATE TABLE `redcap_data2` (
`project_id` int(10) NOT NULL DEFAULT '0',
`event_id` int(10) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`field_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`instance` smallint(4) DEFAULT NULL,
KEY `event_id_instance` (`event_id`,`instance`),
KEY `proj_record_field` (`project_id`,`record`,`field_name`),
KEY `project_field` (`project_id`,`field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_data3` (
`project_id` int(10) NOT NULL DEFAULT '0',
`event_id` int(10) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`field_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`instance` smallint(4) DEFAULT NULL,
KEY `event_id_instance` (`event_id`,`instance`),
KEY `proj_record_field` (`project_id`,`record`,`field_name`),
KEY `project_field` (`project_id`,`field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_data4` (
`project_id` int(10) NOT NULL DEFAULT '0',
`event_id` int(10) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`field_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`instance` smallint(4) DEFAULT NULL,
KEY `event_id_instance` (`event_id`,`instance`),
KEY `proj_record_field` (`project_id`,`record`,`field_name`),
KEY `project_field` (`project_id`,`field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_projects` ADD `data_table` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'redcap_data' COMMENT 'Project redcap_data table' AFTER `log_event_table`;
ALTER TABLE `redcap_projects` 
    ADD `project_db_character_set` VARCHAR(50) NULL DEFAULT NULL AFTER `file_repository_total_size`, 
    ADD `project_db_collation` VARCHAR(50) NULL DEFAULT NULL AFTER `project_db_character_set`;
";

print $sql;