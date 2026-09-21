-- Add new MyCap table
CREATE TABLE `redcap_mycap_message_notifications` (
`notification_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`dag_id` int(10) DEFAULT NULL,
`notify_user` int(1) NOT NULL DEFAULT '0' COMMENT 'Notify study coordinator upon receiving message via email?',
`user_emails` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'List of user emails',
`custom_email_text` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Custom Email text',
PRIMARY KEY (`notification_id`),
KEY `project_id` (`project_id`),
KEY `dag_id` (`dag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_mycap_message_notifications`
ADD FOREIGN KEY (`dag_id`) REFERENCES `redcap_data_access_groups` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;