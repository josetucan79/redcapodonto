<?php

$sql = "
ALTER TABLE `redcap_ehr_fhir_logs` 
    ADD `mrn` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', 
    ADD INDEX `mrn` (`mrn`),
    ADD INDEX `project_id_mrn` (`project_id`, `mrn`),
    ADD INDEX `fhir_id_resource_type` (`fhir_id`, `resource_type`);
";

print $sql;