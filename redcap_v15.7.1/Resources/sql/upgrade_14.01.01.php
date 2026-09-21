<?php

$sql = "DELETE FROM redcap_ehr_user_map WHERE redcap_userid NOT IN (SELECT ui_id FROM redcap_user_information);\n";

// Drop foreign key
$q = db_query("select * from redcap_ehr_user_map");
if (!$q) {
    // If table doesn't exist when the upgrade page is loaded, then by the time we get here, it will have just one FK
    $sql .= "ALTER TABLE redcap_ehr_user_map DROP FOREIGN KEY `redcap_ehr_user_map_ibfk_1`;\n";
} else {
    $fk = System::getForeignKeyByCol('redcap_ehr_user_map', 'redcap_userid');
    if ($fk != null) $sql .= "ALTER TABLE redcap_ehr_user_map DROP FOREIGN KEY `$fk`;\n";
}

$sql .= "
-- Drop the existing unique constraints
ALTER TABLE redcap_ehr_user_map
DROP INDEX ehr_username,
DROP INDEX redcap_userid;

-- Add the 'ehr_id' column and create a foreign key reference
ALTER TABLE redcap_ehr_user_map
ADD `ehr_id` int(11) DEFAULT NULL,
ADD FOREIGN KEY (ehr_id) REFERENCES redcap_ehr_settings(ehr_id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Add new unique constraints that include 'ehr_id'
ALTER TABLE redcap_ehr_user_map
ADD UNIQUE KEY unique_ehr_username (ehr_id, ehr_username),
ADD UNIQUE KEY unique_redcap_userid (ehr_id, redcap_userid);

-- Update 'redcap_ehr_user_map' with the ehr_id having the lowest 'order' (default) from 'redcap_ehr_settings'
UPDATE redcap_ehr_user_map
SET ehr_id = (
    SELECT ehr_id FROM redcap_ehr_settings
    ORDER BY `order` ASC
    LIMIT 1
)
WHERE EXISTS (
    SELECT 1 FROM redcap_ehr_settings
);

-- remove invalid mappings
DELETE FROM redcap_ddp_mapping WHERE external_source_field_name IN(
	'active-problem-genomics-list',
	'recurrence-problem-genomics-list',
	'relapse-problem-genomics-list',
	'inactive-problem-genomics-list',
	'remission-problem-genomics-list',
	'resolved-problem-genomics-list',
	'unknown-problem-genomics-list',

	'active-problem-medical-history-list',
	'recurrence-problem-medical-history-list',
	'relapse-problem-medical-history-list',
	'inactive-problem-medical-history-list',
	'remission-problem-medical-history-list',
	'resolved-problem-medical-history-list',
	'unknown-problem-medical-history-list',

	'active-problem-reason-for-visit-list',
	'recurrence-problem-reason-for-visit-list',
	'relapse-problem-reason-for-visit-list',
	'inactive-problem-reason-for-visit-list',
	'remission-problem-reason-for-visit-list',
	'resolved-problem-reason-for-visit-list',
	'unknown-problem-reason-for-visit-list',

	'active-encounter-diagnosis-list',
	'recurrence-encounter-diagnosis-list',
	'relapse-encounter-diagnosis-list',
	'inactive-encounter-diagnosis-list',
	'remission-encounter-diagnosis-list',
	'resolved-encounter-diagnosis-list',
	'unknown-encounter-diagnosis-list'
);
";

print $sql;