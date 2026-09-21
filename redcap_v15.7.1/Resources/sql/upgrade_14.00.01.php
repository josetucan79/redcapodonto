<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('mtb_enabled', '0');
DELETE FROM redcap_user_rights WHERE project_id NOT IN (SELECT project_id FROM redcap_projects);
DELETE FROM redcap_user_roles WHERE project_id NOT IN (SELECT project_id FROM redcap_projects);
delete from redcap_outgoing_email_sms_log where project_id is not null and project_id not in (select project_id from redcap_projects);
";
// Fix foreign key
$fk = System::getForeignKeyByCol('redcap_outgoing_email_sms_log', 'project_id');
if ($fk != null) {
    $sql .= "
ALTER TABLE `redcap_outgoing_email_sms_log` DROP FOREIGN KEY `$fk`, ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";
}


print $sql;