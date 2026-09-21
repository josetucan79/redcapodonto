<?php

$sql = "
CREATE TABLE `redcap_rewards_access_token` (
`access_token_id` int(11) NOT NULL AUTO_INCREMENT,
`access_token` text COLLATE utf8mb4_unicode_ci NOT NULL,
`scope` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`expires_in` int(11) DEFAULT NULL,
`token_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` datetime DEFAULT NULL,
`project_id` int(11) NOT NULL,
`provider_id` int(11) NOT NULL,
PRIMARY KEY (`access_token_id`),
KEY `project_id` (`project_id`),
KEY `provider_id` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_rewards_actions` (
`action_id` int(11) NOT NULL AUTO_INCREMENT,
`order_id` int(11) DEFAULT NULL,
`project_id` int(11) DEFAULT NULL,
`arm_number` int(11) DEFAULT '1',
`record_id` int(11) DEFAULT NULL,
`stage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g., eligibility_review, financial_authorization, compensation_delivery',
`event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'e.g., approval, rejection, revert, error, unknown, redeem_code_generated, email_sent',
`status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g., pending, completed, error',
`comment` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reason for rejection, if applicable',
`details` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'extra details (e.g.: errors, payload)',
`performed_by` int(11) DEFAULT NULL,
`performed_at` datetime DEFAULT NULL,
PRIMARY KEY (`action_id`),
KEY `fk_action_performed_by` (`performed_by`),
KEY `fk_action_project` (`project_id`),
KEY `fk_action_reward_option` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_rewards_emails` (
`email_id` int(11) NOT NULL AUTO_INCREMENT,
`sendable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Type of the related entity',
`sendable_id` int(11) DEFAULT NULL COMMENT 'ID of the related entity',
`email_subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Subject of the email sent',
`email_content` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Content of the email sent',
`sender_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email address of the sender',
`recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email address of the recipient',
`sent_at` datetime DEFAULT NULL COMMENT 'Timestamp when the email was sent',
`sent_by` int(11) DEFAULT NULL COMMENT 'User ID who sent the email',
PRIMARY KEY (`email_id`),
KEY `fk_email_sent_by` (`sent_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_rewards_logs` (
`log_id` int(11) NOT NULL AUTO_INCREMENT,
`table_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`payload` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`project_id` int(11) DEFAULT NULL,
`created_at` datetime DEFAULT NULL,
PRIMARY KEY (`log_id`),
KEY `fk_log_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_rewards_orders` (
`order_id` int(11) NOT NULL AUTO_INCREMENT,
`reward_option_id` int(11) DEFAULT NULL,
`project_id` int(11) DEFAULT NULL,
`arm_number` int(11) DEFAULT '1',
`record_id` int(11) DEFAULT NULL,
`internal_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'a reference ID for internal use',
`reference_order` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'order ID from the reward provider',
`eligibility_logic` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'stored for history',
`reward_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`reward_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`reward_value` decimal(10,2) DEFAULT NULL,
`redeem_link` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`scheduled_action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`uuid` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
`created_by` int(11) DEFAULT NULL,
`created_at` datetime DEFAULT NULL,
PRIMARY KEY (`order_id`),
UNIQUE KEY `idx_uuid_unique` (`uuid`),
KEY `fk_order_created_by` (`created_by`),
KEY `fk_order_redcap_project` (`project_id`),
KEY `fk_order_reward_option` (`reward_option_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_rewards_permissions` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_rewards_project_providers` (
`project_id` int(11) NOT NULL,
`provider_id` int(11) NOT NULL,
UNIQUE KEY `unique_project_provider` (`project_id`,`provider_id`),
KEY `provider_id` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_rewards_project_settings` (
`project_setting_id` int(11) NOT NULL AUTO_INCREMENT,
`project_id` int(11) NOT NULL,
`setting_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`setting_value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`project_setting_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_rewards_providers` (
`provider_id` int(11) NOT NULL AUTO_INCREMENT,
`provider_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`is_default` tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_rewards_reward_option` (
`reward_option_id` int(11) NOT NULL AUTO_INCREMENT,
`project_id` int(11) DEFAULT NULL,
`provider_product_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`value_amount` decimal(10,2) DEFAULT NULL,
`eligibility_logic` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`deleted_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`reward_option_id`),
KEY `fk_reward_option_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_rewards_settings` (
`setting_id` int(11) NOT NULL AUTO_INCREMENT,
`provider_id` int(11) NOT NULL,
`setting_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`setting_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
PRIMARY KEY (`setting_id`),
KEY `redcap_rewards_settings_ibfk_1` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_rewards_user_permissions` (
`user_id` int(11) NOT NULL,
`permission_id` int(11) NOT NULL,
`project_id` int(11) NOT NULL,
PRIMARY KEY (`user_id`,`permission_id`,`project_id`),
KEY `permission_id` (`permission_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_rewards_access_token`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`provider_id`) REFERENCES `redcap_rewards_providers` (`provider_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_rewards_actions`
ADD FOREIGN KEY (`order_id`) REFERENCES `redcap_rewards_orders` (`order_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`performed_by`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `redcap_rewards_emails`
ADD FOREIGN KEY (`sent_by`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE NO ACTION;

ALTER TABLE `redcap_rewards_logs`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `redcap_rewards_orders`
ADD FOREIGN KEY (`created_by`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`reward_option_id`) REFERENCES `redcap_rewards_reward_option` (`reward_option_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `redcap_rewards_project_providers`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`provider_id`) REFERENCES `redcap_rewards_providers` (`provider_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_rewards_project_settings`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_rewards_reward_option`
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`);

ALTER TABLE `redcap_rewards_settings`
ADD FOREIGN KEY (`provider_id`) REFERENCES `redcap_rewards_providers` (`provider_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_rewards_user_permissions`
ADD FOREIGN KEY (`permission_id`) REFERENCES `redcap_rewards_permissions` (`id`) ON DELETE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`user_id`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO redcap_rewards_permissions (id, name) VALUES
(1, 'review_eligibility'),
(2, 'place_orders'),
(3, 'manage_permissions'),
(4, 'manage_project_settings'),
(5, 'view_logs'),
(6, 'manage_reward_options'),
(7, 'manage_api_settings'),
(8, 'view_orders');

INSERT INTO redcap_rewards_providers (provider_id, provider_name, is_default) VALUES
(1, 'Tango', 1);

ALTER TABLE `redcap_projects` ADD `rewards_enabled` tinyint(1) NOT NULL DEFAULT '0';
INSERT INTO redcap_crons (cron_name, cron_description, cron_enabled, cron_frequency, cron_max_run_time, cron_instances_max, cron_instances_current, cron_last_run_end, cron_times_failed, cron_external_url) 
	VALUES ('ProcessScheduledRewardOrders', 'Processes scheduled actions assigned to Rewards Orders.', 'ENABLED', 60, 3600, 5, 0, NULL, 0, NULL);
REPLACE INTO redcap_config (field_name, value) VALUES ('rewards_enabled_global', '0');
REPLACE INTO redcap_config (field_name, value) VALUES ('rewards_enabled_by_super_users_only', '0');
REPLACE INTO redcap_config (field_name, value) VALUES ('rewards_enable_type', 'admin');
REPLACE INTO redcap_config (field_name, value) VALUES ('rewards_enablement_message', '');
REPLACE INTO redcap_config (field_name, value) VALUES ('rewards_display_info_project_setup', '0');
";

// Set processed = '1' (action needed = no) to automated messages sent from app upon joining, rejoining and deleting project from app-side
// These messages are not visible in message list and after executing below SQL, these will not counted as messages needed action
$sql .= "
UPDATE 
    redcap_mycap_messages 
SET 
    processed = '1' 
WHERE body = '#FIRST_JOINED PROJECT'
    OR body = '#JOINED PROJECT'
    OR body = '#DELETED PROJECT';
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print $sql;