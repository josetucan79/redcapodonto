<?php

$sql = "
DROP TABLE IF EXISTS `redcap_cde_orgs`;
DROP TABLE IF EXISTS `redcap_cde_questions_cache`;
DROP TABLE IF EXISTS `redcap_cde_cache`;
DROP TABLE IF EXISTS `redcap_cde_field_mapping`;

CREATE TABLE `redcap_cde_cache` (
`cache_id` int(10) NOT NULL AUTO_INCREMENT,
`tinyId` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`publicId` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`steward` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`question` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`choices` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`updated_on` datetime DEFAULT NULL,
PRIMARY KEY (`cache_id`),
UNIQUE KEY `publicId` (`publicId`),
UNIQUE KEY `tinyId` (`tinyId`),
KEY `steward` (`steward`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_cde_field_mapping` (
`project_id` int(10) DEFAULT NULL,
`field_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`tinyId` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`publicId` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`questionId` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`steward` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`web_service` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`org_selected` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
UNIQUE KEY `project_field` (`project_id`,`field_name`),
KEY `org_project` (`org_selected`,`project_id`),
KEY `publicId` (`publicId`),
KEY `questionId` (`questionId`),
KEY `steward_project` (`steward`,`project_id`),
KEY `tinyId_project` (`tinyId`,`project_id`),
KEY `web_service` (`web_service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_cde_field_mapping`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";

print $sql;

// Add Messenger system notification
$title = "New feature: @INLINE Action Tag";
$msg = "The new @INLINE Action Tag allows a PDF file or image file (JPG, JPEG, GIF, PNG, TIF, BMP) that is uploaded to a File Upload field to be displayed in an inline manner on the survey page or data entry form so that the PDF/image can be viewed without having to download it. This new feature can be enabled in the Online Designer simply by adding the <b>@INLINE</b> action tag for any File Upload field.

<b class=\"fs15\">New feature: \":inline\" Piping Option</b>
As a separate but similar feature, the \":inline\" piping option can be used when piping File Upload fields to other pages (e.g., <b>[image_field:inline]</b>), in which the option allows you to pipe a PDF/image for an uploaded file to be displayed in an inline manner on a page outside of the field's own instrument. So while the @INLINE action tag can be used to display the PDF/image on the field's instrument, the \":inline\" piping option allows you to display the PDF/image in places outside the field's instrument.";
print Messenger::generateNewSystemNotificationSQL($title, $msg);