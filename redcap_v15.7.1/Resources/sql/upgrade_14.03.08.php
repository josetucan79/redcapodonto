<?php

$sql = "
-- Adjust db table for better performance
ALTER TABLE `redcap_log_view_requests` 
    DROP INDEX `log_view_id_mysql_id_time`,
    DROP INDEX `log_view_id_time_ui_id`, 
    DROP INDEX `ui_id`, 
    ADD INDEX `ui_id_log_view_id` (`ui_id`,`log_view_id`);
-- Enable MTB measures
REPLACE INTO redcap_config (field_name, value) VALUES ('mtb_enabled', '1');
";

print $sql;