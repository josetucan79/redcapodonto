<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES
('create_project_custom_text', ''),
('ai_services_enabled_global', 0),
('ai_improvetext_service_enabled', 0),
('ai_datasummarization_service_enabled', 0),
('ai_mlmtranslator_service_enabled', 0),
('openai_endpoint_url', ''),
('openai_api_key', ''),
('openai_api_version', ''),
('openai_chat_model', '');

ALTER TABLE `redcap_projects` 
    ADD `openai_endpoint_url_project` TEXT NULL DEFAULT NULL AFTER `local_storage_subfolder`, 
    ADD `openai_api_key_project` TEXT NULL DEFAULT NULL AFTER `openai_endpoint_url_project`, 
    ADD `openai_api_version_project` TEXT NULL DEFAULT NULL AFTER `openai_api_key_project`, 
    ADD `openai_chat_model_project` TEXT NULL DEFAULT NULL AFTER `openai_api_version_project`;

CREATE TABLE `redcap_reports_ai_prompts` (
`project_id` int(10) NOT NULL,
`report_id` int(10) DEFAULT NULL,
`field_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`summary_prompt` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
UNIQUE KEY `project_id_report_id_field_name` (`project_id`,`report_id`,`field_name`),
KEY `field_name` (`field_name`),
KEY `project_id` (`project_id`),
KEY `report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_reports_ai_prompts`
ADD FOREIGN KEY (`report_id`) REFERENCES `redcap_reports` (`report_id`) ON DELETE CASCADE ON UPDATE CASCADE;

CREATE TABLE `redcap_ai_log` (
`ai_id` int(11) NOT NULL AUTO_INCREMENT,
`ts` datetime DEFAULT NULL,
`service` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`project_id` int(11) DEFAULT NULL,
`user_id` int(11) DEFAULT NULL,
`num_chars_sent` int(11) DEFAULT NULL,
`num_words_sent` int(11) DEFAULT NULL,
`num_chars_received` int(11) DEFAULT NULL,
`num_words_received` int(11) DEFAULT NULL,
PRIMARY KEY (`ai_id`),
KEY `project_id` (`project_id`),
KEY `ts_type` (`ts`,`type`),
KEY `type_project` (`type`,`project_id`),
KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_ai_log`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;
";


print $sql;