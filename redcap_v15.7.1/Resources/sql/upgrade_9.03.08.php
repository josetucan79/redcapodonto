<?php

$sql = "
ALTER TABLE `redcap_record_counts` ADD `time_of_list_cache` TIMESTAMP NULL DEFAULT NULL AFTER `record_list_status`, ADD INDEX (`time_of_list_cache`);
";

print $sql;