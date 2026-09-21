<?php

$sql = "
CREATE TABLE `redcap_descriptive_popups` (
`popup_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`hex_link_color` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`inline_text` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`inline_text_popup_description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`active_on_surveys` tinyint(1) NOT NULL DEFAULT '1',
`active_on_data_entry_forms` tinyint(1) NOT NULL DEFAULT '1',
`first_occurrence_only` tinyint(1) NOT NULL DEFAULT '0',
`list_instruments` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`list_survey_pages` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`popup_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_descriptive_popups`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";


print $sql;