<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('admin_email_external_user_creation', '0');
REPLACE INTO redcap_config (field_name, value) VALUES ('user_welcome_email_external_user_creation', '0');
REPLACE INTO redcap_config (field_name, value) VALUES ('openid_connect_response_type', 'query');
REPLACE INTO redcap_config (field_name, value) VALUES ('restricted_upload_file_types', '');

ALTER TABLE `redcap_user_rights` 
    ADD `alerts` int(1) NOT NULL DEFAULT '0' AFTER `design`;
ALTER TABLE `redcap_user_roles` 
    ADD `alerts` int(1) NOT NULL DEFAULT '0' AFTER `design`;
update redcap_user_rights set alerts = 1 where design = 1;
update redcap_user_roles set alerts = 1 where design = 1;

REPLACE INTO redcap_config (field_name, value) VALUES ('file_repository_total_size', '');
REPLACE INTO redcap_config (field_name, value) VALUES ('file_repository_allow_public_link', '1');
ALTER TABLE `redcap_projects` ADD `file_repository_total_size` INT(10) DEFAULT NULL COMMENT 'MB';

CREATE TABLE `redcap_docs_folders` (
`folder_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(11) DEFAULT NULL,
`name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`parent_folder_id` int(10) DEFAULT NULL,
`dag_id` int(11) DEFAULT NULL COMMENT 'DAG association',
`role_id` int(10) DEFAULT NULL COMMENT 'User role association',
`deleted` tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY (`folder_id`),
KEY `dag_id` (`dag_id`),
KEY `parent_folder_id` (`parent_folder_id`),
KEY `project_id_name_parent_id` (`project_id`,`name`,`parent_folder_id`,`deleted`),
KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_docs_folders`
ADD FOREIGN KEY (`dag_id`) REFERENCES `redcap_data_access_groups` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`parent_folder_id`) REFERENCES `redcap_docs_folders` (`folder_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`role_id`) REFERENCES `redcap_user_roles` (`role_id`) ON DELETE CASCADE ON UPDATE CASCADE;

CREATE TABLE `redcap_docs_folders_files` (
`docs_id` int(10) NOT NULL,
`folder_id` int(10) DEFAULT NULL,
PRIMARY KEY (`docs_id`),
UNIQUE KEY `docs_folder_id` (`docs_id`,`folder_id`),
KEY `folder_id` (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_docs_folders_files`
ADD FOREIGN KEY (`docs_id`) REFERENCES `redcap_docs` (`docs_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`folder_id`) REFERENCES `redcap_docs_folders` (`folder_id`) ON DELETE CASCADE ON UPDATE CASCADE;

CREATE TABLE `redcap_docs_share` (
`docs_id` int(10) NOT NULL AUTO_INCREMENT,
`hash` varchar(100) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT NULL,
PRIMARY KEY (`docs_id`),
UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `redcap_docs_share`
ADD FOREIGN KEY (`docs_id`) REFERENCES `redcap_docs` (`docs_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";

print $sql;


// Add Messenger system notification
$title = "File Repository Improvements";
$msg = "The File Repository page has been redesigned to make it easier to store, organize, and share the files in your projects. 

You now have the ability to create folders and sub-folders to help organize your files more effectively. If you are using Data Access Groups or user roles, you may optionally limit access to a new folder so that it is DAG-restricted and/or role-restricted. 

Uploading multiple files is much faster with a new drag-n-drop feature that allows for uploading dozens of files at a time. Sharing files is better too, in which you may obtain a public link to conveniently share a file with someone. New API methods also exist that allow you to upload, download, and delete files programmatically using the API. Additionally, the File Repository has a new built-in Recycle Bin folder that makes it easy to restore files that have been deleted.

We hope you enjoy these improvements!";
print Messenger::generateNewSystemNotificationSQL($title, $msg);