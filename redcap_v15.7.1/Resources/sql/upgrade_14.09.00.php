<?php

$sql = "
REPLACE INTO `redcap_config` (`field_name`, `value`) VALUES ('local_storage_use_project_subfolder','0');
ALTER TABLE `redcap_projects` ADD `local_storage_subfolder` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `task_complete_status`;
";


print $sql;