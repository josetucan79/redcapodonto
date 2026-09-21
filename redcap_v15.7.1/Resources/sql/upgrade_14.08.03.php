<?php

$sql = "
ALTER TABLE `redcap_error_log` DROP INDEX `log_view_id`, ADD INDEX `log_view_id` (`log_view_id`);
";


print $sql;