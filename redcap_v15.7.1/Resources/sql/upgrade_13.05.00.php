<?php

$sql = "
CREATE TABLE IF NOT EXISTS `redcap_error_log` (
`error_id` int(10) NOT NULL AUTO_INCREMENT,
`log_view_id` bigint(19) DEFAULT NULL,
`time_of_error` datetime DEFAULT NULL,
`error` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`error_id`),
UNIQUE KEY `log_view_id` (`log_view_id`),
KEY `time_of_error` (`time_of_error`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_error_log`
ADD FOREIGN KEY (`log_view_id`) REFERENCES `redcap_log_view` (`log_view_id`) ON DELETE CASCADE ON UPDATE CASCADE;

REPLACE INTO redcap_config (field_name, value) VALUES ('oauth2_azure_ad_tenant', 'common');
";
print $sql;