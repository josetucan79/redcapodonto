-- Added new field for mailgun to keep track of email count
ALTER TABLE `redcap_outgoing_email_counts` ADD `mailgun` INT(10) NULL DEFAULT '0';

-- Added 2 config variables for mailgun
INSERT INTO redcap_config (field_name, value) VALUES
('mailgun_api_key', ''),
('mailgun_domain_name', '');

ALTER TABLE `redcap_projects` ADD `bypass_branching_erase_field_prompt` TINYINT(1) NOT NULL DEFAULT '0' AFTER `project_dashboard_min_data_points`;

ALTER TABLE `redcap_user_roles`
    ADD `unique_role_name` VARCHAR(50) NULL DEFAULT NULL AFTER `role_name`,
    DROP INDEX `project_id`,
    ADD UNIQUE `project_id_unique_role_name` (`project_id`, `unique_role_name`);