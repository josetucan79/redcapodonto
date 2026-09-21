<?php

$sql = "
-- Remove unnecessary parts of db table
update redcap_new_record_cache set event_id = null, arm_id = null;
";

$fk = System::getForeignKeyByCol('redcap_new_record_cache', 'arm_id');
if ($fk != null) {
	$sql .= "ALTER TABLE redcap_new_record_cache DROP FOREIGN KEY `$fk`;\n";
}

$fk = System::getForeignKeyByCol('redcap_new_record_cache', 'event_id');
if ($fk != null) {
	$sql .= "ALTER TABLE redcap_new_record_cache DROP FOREIGN KEY `$fk`;\n";
}

$sql .= "
ALTER TABLE `redcap_new_record_cache`
    DROP INDEX `record_arm`,
    DROP INDEX `proj_record_event`,
    DROP `arm_id`,
    DROP `event_id`,
    DROP INDEX `project_id`,
    ADD UNIQUE `proj_record` (`project_id`, `record`);
";

print $sql;
