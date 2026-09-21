<?php

// Fix foreign key
$SQLTableCheck = new SQLTableCheck();
$table = 'redcap_alerts';
$column = 'form_name_event';
$true_fk_name = $SQLTableCheck->get_FK_from_field($table, $column);
$sql2 = "";
if ($true_fk_name != '') {
	$sql2 = "ALTER TABLE `$table` DROP FOREIGN KEY `$true_fk_name`;";
}
// Add drop/add FK commands
$sql = "
set foreign_key_checks = 0;
update redcap_alerts a
	left join redcap_events_metadata e on e.event_id = a.form_name_event
	set a.form_name_event = null
	where a.form_name_event is not null and e.event_id is null;
$sql2
ALTER TABLE `$table` ADD FOREIGN KEY (`$column`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;
set foreign_key_checks = 1;
";

print $sql;