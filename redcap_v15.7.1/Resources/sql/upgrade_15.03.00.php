<?php

$sql = "
CREATE TABLE `redcap_email_users_messages` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`subject` text COLLATE utf8mb4_unicode_ci NOT NULL,
`body` text COLLATE utf8mb4_unicode_ci NOT NULL,
`sent_by` int(11) NOT NULL,
`created_at` datetime DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `created_at` (`created_at`),
KEY `sent_by` (`sent_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_email_users_queries` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`name` text COLLATE utf8mb4_unicode_ci NOT NULL,

`description` text COLLATE utf8mb4_unicode_ci NOT NULL,
`query` text COLLATE utf8mb4_unicode_ci NOT NULL,
`created_at` datetime DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_email_users_messages`
ADD FOREIGN KEY (`sent_by`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";


print $sql;