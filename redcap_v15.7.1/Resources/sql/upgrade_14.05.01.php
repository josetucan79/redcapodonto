<?php

$sql = "
CREATE TABLE `redcap_data5` (
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

CREATE TABLE `redcap_data6` (
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

CREATE TABLE `redcap_log_event10` (
`log_event_id` int(11) NOT NULL AUTO_INCREMENT,
`project_id` int(10) NOT NULL DEFAULT '0',
`ts` bigint(14) DEFAULT NULL,
`user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event` enum('UPDATE','INSERT','DELETE','SELECT','ERROR','LOGIN','LOGOUT','OTHER','DATA_EXPORT','DOC_UPLOAD','DOC_DELETE','MANAGE','LOCK_RECORD','ESIGNATURE') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`object_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`sql_log` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`pk` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`data_values` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`legacy` int(1) NOT NULL DEFAULT '0',
`change_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`log_event_id`),
KEY `object_type` (`object_type`),
KEY `project_description` (`project_id`,`description`),
KEY `project_event` (`project_id`,`event`),
KEY `project_page` (`project_id`,`page`(191)),
KEY `project_pk` (`project_id`,`pk`(191)),
KEY `project_ts_description` (`project_id`,`ts`,`description`),
KEY `project_user` (`project_id`,`user`(191)),
KEY `ts_project` (`ts`,`project_id`),
KEY `user_project` (`user`(191),`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_log_event11` (
`log_event_id` int(11) NOT NULL AUTO_INCREMENT,
`project_id` int(10) NOT NULL DEFAULT '0',
`ts` bigint(14) DEFAULT NULL,
`user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event` enum('UPDATE','INSERT','DELETE','SELECT','ERROR','LOGIN','LOGOUT','OTHER','DATA_EXPORT','DOC_UPLOAD','DOC_DELETE','MANAGE','LOCK_RECORD','ESIGNATURE') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`object_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`sql_log` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`pk` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`data_values` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`legacy` int(1) NOT NULL DEFAULT '0',
`change_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`log_event_id`),
KEY `object_type` (`object_type`),
KEY `project_description` (`project_id`,`description`),
KEY `project_event` (`project_id`,`event`),
KEY `project_page` (`project_id`,`page`(191)),
KEY `project_pk` (`project_id`,`pk`(191)),
KEY `project_ts_description` (`project_id`,`ts`,`description`),
KEY `project_user` (`project_id`,`user`(191)),
KEY `ts_project` (`ts`,`project_id`),
KEY `user_project` (`user`(191),`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_log_event12` (
`log_event_id` int(11) NOT NULL AUTO_INCREMENT,
`project_id` int(10) NOT NULL DEFAULT '0',
`ts` bigint(14) DEFAULT NULL,
`user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event` enum('UPDATE','INSERT','DELETE','SELECT','ERROR','LOGIN','LOGOUT','OTHER','DATA_EXPORT','DOC_UPLOAD','DOC_DELETE','MANAGE','LOCK_RECORD','ESIGNATURE') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`object_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`sql_log` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`pk` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`data_values` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`legacy` int(1) NOT NULL DEFAULT '0',
`change_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`log_event_id`),
KEY `object_type` (`object_type`),
KEY `project_description` (`project_id`,`description`),
KEY `project_event` (`project_id`,`event`),
KEY `project_page` (`project_id`,`page`(191)),
KEY `project_pk` (`project_id`,`pk`(191)),
KEY `project_ts_description` (`project_id`,`ts`,`description`),
KEY `project_user` (`project_id`,`user`(191)),
KEY `ts_project` (`ts`,`project_id`),
KEY `user_project` (`user`(191),`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

print $sql;