-- Integrate Stealth Queue external module
ALTER TABLE `redcap_projects` ADD `survey_queue_hide` TINYINT(1) NOT NULL DEFAULT '0' AFTER `survey_queue_custom_text`;
-- Migrate Stealth Queue settings and disable the module system-wide
update redcap_external_modules e, redcap_external_module_settings s, redcap_external_module_settings t, redcap_projects p
	set p.survey_queue_hide = 1
    where e.directory_prefix = 'stealth_queue' and e.external_module_id = s.external_module_id and s.project_id is not null
    and s.`key` = 'enabled' and s.`value` = 'true' and t.project_id = s.project_id and t.external_module_id = s.external_module_id and p.project_id = s.project_id;
delete s.* from redcap_external_modules e, redcap_external_module_settings s
    where e.directory_prefix = 'stealth_queue' and e.external_module_id = s.external_module_id and s.project_id is null;
-- Add granularity for outgoing emails count table
ALTER TABLE `redcap_outgoing_email_counts`
    ADD `smtp` INT(10) NULL DEFAULT '0' AFTER `send_count`,
    ADD `sendgrid` INT(10) NULL DEFAULT '0' AFTER `smtp`,
    ADD `mandrill` INT(10) NULL DEFAULT '0' AFTER `sendgrid`,
    CHANGE `send_count` `send_count` INT(10) NULL DEFAULT '1' COMMENT 'Total';
update redcap_outgoing_email_counts set smtp = send_count;