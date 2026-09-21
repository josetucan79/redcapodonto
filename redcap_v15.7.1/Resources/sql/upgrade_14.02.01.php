<?php

// This same column was also added to the redcap_data_import table in 14.0.14 LTS,
// so do not re-add the column if user is upgrading while on v14.0.14 and less than v14.1.0.
if (version_compare(REDCAP_VERSION, '14.0.14', '<') || version_compare(REDCAP_VERSION, '14.1.0', '>='))
{
    $sql = "
ALTER TABLE `redcap_data_import` 
    ADD `dag_id` INT(10) NULL DEFAULT NULL COMMENT 'Current DAG of user importing data' AFTER `user_id`, 
    ADD INDEX (`dag_id`),
    ADD FOREIGN KEY (`dag_id`) REFERENCES `redcap_data_access_groups`(`group_id`) ON DELETE SET NULL ON UPDATE CASCADE;
";
    print $sql;
}