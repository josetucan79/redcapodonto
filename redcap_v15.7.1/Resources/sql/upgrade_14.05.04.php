<?php

$sql = "
ALTER TABLE `redcap_mycap_projects` ADD `notification_time` time DEFAULT '08:00:00';
";

print $sql;