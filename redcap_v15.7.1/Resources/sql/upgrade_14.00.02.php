<?php

$sql = "
ALTER TABLE `redcap_projects` ADD `last_logged_event_exclude_exports` DATETIME NULL DEFAULT NULL AFTER `last_logged_event`, ADD INDEX (`last_logged_event_exclude_exports`);
update redcap_projects set last_logged_event_exclude_exports = last_logged_event;
ALTER TABLE `redcap_history_version` DROP PRIMARY KEY;
ALTER TABLE `redcap_history_version` ADD PRIMARY KEY (`date`, `redcap_version`);
";

print $sql;