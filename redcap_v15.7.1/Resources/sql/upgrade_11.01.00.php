<?php

$sql = "
-- added columns for new endpoints and adjusted default value to 0 
ALTER TABLE `redcap_ehr_import_counts`
MODIFY `type` enum('CDP','CDM', 'CDP-I') NOT NULL DEFAULT 'CDP',
MODIFY `counts_Patient` MEDIUMINT(7) DEFAULT 0,
MODIFY `counts_Observation` MEDIUMINT(7) DEFAULT 0,
MODIFY `counts_Condition` MEDIUMINT(7) DEFAULT 0,
CHANGE `counts_MedicationOrder` `counts_Medication` MEDIUMINT(7) DEFAULT 0,
MODIFY `counts_AllergyIntolerance` MEDIUMINT(7) DEFAULT 0,
ADD `counts_Encounter` MEDIUMINT(7) DEFAULT 0,
ADD `counts_Immunization` MEDIUMINT(7) DEFAULT 0,
ADD `counts_AdverseEvent` MEDIUMINT(7) DEFAULT 0;
";

print $sql;