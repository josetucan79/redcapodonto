<?php

$sql = "
delete from redcap_queue;
ALTER TABLE `redcap_queue`
    DROP `updated_at`,
    ADD `description` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `key`,
    CHANGE `status` `status` ENUM('waiting','processing','completed','warning','error','canceled') COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `description`,
    ADD `priority` int(11) DEFAULT NULL AFTER `status`,
    ADD `message` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `priority`,
    ADD `started_at` datetime DEFAULT NULL AFTER `created_at`,
    ADD `completed_at` datetime DEFAULT NULL AFTER `started_at`;
ALTER TABLE `redcap_projects` 
    ADD `mosio_api_key` VARCHAR(100) NULL DEFAULT NULL AFTER `twilio_delivery_preference_field_map`,
    ADD `mosio_hide_in_project` TINYINT(1) NOT NULL DEFAULT '0' AFTER `mosio_api_key`;
ALTER TABLE `redcap_outgoing_email_counts` 
    ADD `mosio_sms` INT(10) NOT NULL DEFAULT '0' AFTER `twilio_sms`;
-- Remove duplicates in redcap_surveys_phone_codes
delete d.* from redcap_surveys_phone_codes d, 
    (select min(c.pc_id) as pc_id, c.phone_number, c.twilio_number, c.access_code, c.project_id from redcap_surveys_phone_codes c
    group by c.phone_number, c.twilio_number, c.access_code, c.project_id
    having count(*) > 1) e
    where e.phone_number = d.phone_number and e.twilio_number = d.twilio_number and e.access_code = d.access_code and e.project_id = d.project_id
    and e.pc_id != d.pc_id;
ALTER TABLE `redcap_surveys_phone_codes`
    ADD `session_id` VARCHAR(32) NULL DEFAULT NULL AFTER `project_id`, 
    ADD INDEX (`session_id`),
    ADD UNIQUE `phone_access_project` (`phone_number`, `twilio_number`, `access_code`, `project_id`);
REPLACE INTO redcap_config (field_name, value) VALUES ('mosio_enabled_global', '1');
REPLACE INTO redcap_config (field_name, value) VALUES ('mosio_display_info_project_setup', '0');
REPLACE INTO redcap_config (field_name, value) VALUES ('mosio_enabled_by_super_users_only', '0');
-- Add system-level option for display_inline_pdf_in_pdf
set @display_inline_pdf_in_pdf = (select value from redcap_config where field_name = 'display_inline_pdf_in_pdf' limit 1);
REPLACE INTO redcap_config (field_name, value) VALUES ('display_inline_pdf_in_pdf', if(@display_inline_pdf_in_pdf is null, 1, @display_inline_pdf_in_pdf));
";

print $sql;