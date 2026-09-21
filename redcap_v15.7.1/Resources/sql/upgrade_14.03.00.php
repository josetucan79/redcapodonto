<?php

$sql = "
ALTER TABLE `redcap_mycap_participants`
	ADD `join_date_utc` datetime DEFAULT NULL COMMENT 'Date (UTC format) participant joined the project' AFTER `join_date`;
ALTER TABLE `redcap_mycap_participants`
	ADD `timezone` varchar(20) DEFAULT NULL COMMENT 'Participant timezone' AFTER `join_date_utc`;

CREATE TABLE `redcap_custom_queries_folders` (
`folder_id` int(10) NOT NULL AUTO_INCREMENT,
`name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`position` smallint(4) DEFAULT NULL,
PRIMARY KEY (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_custom_queries_folders_items` (
`folder_id` int(10) DEFAULT NULL,
`qid` int(10) DEFAULT NULL,
UNIQUE KEY `folder_id_qid` (`folder_id`,`qid`),
KEY `qid` (`qid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_custom_queries_folders_items`
ADD FOREIGN KEY (`folder_id`) REFERENCES `redcap_custom_queries_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`qid`) REFERENCES `redcap_custom_queries` (`qid`) ON DELETE CASCADE ON UPDATE CASCADE;
";

print $sql;