<?php

$sql = "
ALTER TABLE `redcap_alerts` CHANGE `cron_repeat_for` `cron_repeat_for` FLOAT NOT NULL DEFAULT '0' COMMENT 'Repeat every # of days';
";

print $sql;