-- New email logging right
ALTER TABLE `redcap_user_rights`
    ADD `email_logging` int(1) NOT NULL DEFAULT '0' AFTER `data_logging`;
ALTER TABLE `redcap_user_roles`
    ADD `email_logging` int(1) NOT NULL DEFAULT '0' AFTER `data_logging`;
update redcap_user_rights set email_logging = 1 where user_rights = 1;
update redcap_user_roles set email_logging = 1 where user_rights = 1;
-- New email logging categories
ALTER TABLE `redcap_outgoing_email_sms_log` CHANGE `category` `category` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL;