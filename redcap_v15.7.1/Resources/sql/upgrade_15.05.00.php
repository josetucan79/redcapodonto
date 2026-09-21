<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('max_records_development_global', '0');        
ALTER TABLE `redcap_projects` ADD `max_records_development` int(11) NOT NULL DEFAULT '0' COMMENT '0=Disabled';

-- New survey CSS feature
ALTER TABLE `redcap_surveys`
    ADD `custom_css` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `font_family`;

-- New tables for form properties
CREATE TABLE `redcap_forms` (
    `project_id` int(10) DEFAULT NULL,
    `form_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `custom_css` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    UNIQUE KEY `proj_form` (`project_id`,`form_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_forms`
   ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

CREATE TABLE `redcap_forms_temp` (
    `project_id` int(10) DEFAULT NULL,
    `form_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `custom_css` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    UNIQUE KEY `proj_form` (`project_id`,`form_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_forms_temp`
   ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_docs_folders` ADD `admin_only` TINYINT(1) NOT NULL DEFAULT '0' AFTER `role_id`;
ALTER TABLE `redcap_docs_folders` ADD INDEX `project_id_admin_only` (`project_id`, `admin_only`);
ALTER TABLE `redcap_sessions` CHANGE `session_data` `session_data` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
";


print $sql;