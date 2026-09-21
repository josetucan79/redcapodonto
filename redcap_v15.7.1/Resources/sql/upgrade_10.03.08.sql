ALTER TABLE `redcap_projects` ADD `datamart_cron_end_date` datetime DEFAULT NULL COMMENT 'stop processing the cron job after this date' AFTER `datamart_cron_enabled`;
ALTER TABLE `redcap_surveys_pdf_archive`
    DROP INDEX `record_event_survey_instance`,
    ADD INDEX `record_event_survey_instance` (`record`, `event_id`, `survey_id`, `instance`);