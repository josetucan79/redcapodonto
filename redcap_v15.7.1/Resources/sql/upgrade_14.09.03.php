<?php

$sql = "
CREATE TABLE IF NOT EXISTS `redcap_ehr_resource_imports` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`ts` datetime DEFAULT NULL,
`type` enum('CDP','CDM','CDP-I') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CDP',
`adjudicated` tinyint(1) NOT NULL DEFAULT '0',
`project_id` int(11) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`ehr_id` int(11) DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `ehr_id` (`ehr_id`),
KEY `project_record` (`project_id`,`record`),
KEY `ts_project_adjud` (`ts`,`project_id`,`adjudicated`),
KEY `type_adjud_project_record` (`type`,`adjudicated`,`project_id`,`record`),
KEY `type_project_record` (`type`,`project_id`,`record`),
FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
FOREIGN KEY (`ehr_id`) REFERENCES `redcap_ehr_settings` (`ehr_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `redcap_ehr_resource_import_details` (
`count_id` int(11) NOT NULL AUTO_INCREMENT,
`ehr_import_count_id` int(11) NOT NULL,
`category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`resource` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`count` mediumint(7) DEFAULT '0',
PRIMARY KEY (`count_id`),
KEY `ehr_import_count_id` (`ehr_import_count_id`),
FOREIGN KEY (`ehr_import_count_id`) REFERENCES `redcap_ehr_resource_imports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";


print $sql;