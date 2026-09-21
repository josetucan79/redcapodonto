<?php

// Fix FK issue with redcap_log_view_requests that only affects some installations
$sql = "
-- Fix FK issue with redcap_log_view_requests that only affects some installations
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE `redcap_log_view_requests`;
CREATE TABLE `redcap_log_view_requests` (
`lvr_id` bigint(19) NOT NULL AUTO_INCREMENT,
`log_view_id` bigint(19) DEFAULT NULL COMMENT 'FK from redcap_log_view',
`mysql_process_id` int(10) DEFAULT NULL COMMENT 'Process ID for MySQL',
`php_process_id` int(10) DEFAULT NULL COMMENT 'Process ID for PHP',
`script_execution_time` float DEFAULT NULL COMMENT 'Total PHP script execution time (seconds)',
`is_ajax` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Is request an AJAX request?',
`ui_id` int(11) DEFAULT NULL COMMENT 'FK from redcap_user_information',
PRIMARY KEY (`lvr_id`),
UNIQUE KEY `log_view_id` (`log_view_id`),
UNIQUE KEY `log_view_id_time_ui_id` (`log_view_id`,`script_execution_time`,`ui_id`),
KEY `log_view_id_mysql_id_time` (`log_view_id`,`mysql_process_id`,`script_execution_time`),
KEY `mysql_process_id` (`mysql_process_id`),
KEY `php_process_id` (`php_process_id`),
KEY `ui_id` (`ui_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `redcap_log_view_requests`
ADD FOREIGN KEY (`log_view_id`) REFERENCES `redcap_log_view` (`log_view_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`ui_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;
SET FOREIGN_KEY_CHECKS = 1;
";


print $sql;