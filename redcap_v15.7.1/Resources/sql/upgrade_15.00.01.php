<?php

$sql = "
CREATE TABLE IF NOT EXISTS `redcap_edocs_data_mapping` (
`doc_id` int(10) NOT NULL,
`project_id` int(10) DEFAULT NULL,
`event_id` int(10) DEFAULT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`field_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`instance` smallint(4) DEFAULT NULL,
PRIMARY KEY (`doc_id`),
KEY `event_id_record` (`event_id`,`record`),
KEY `proj_record_event_field` (`project_id`,`record`,`event_id`,`field_name`),
FOREIGN KEY (`doc_id`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
FOREIGN KEY (`event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";


print $sql;