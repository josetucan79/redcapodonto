-- alterations for randomization enhancements
ALTER TABLE `redcap_randomization`
    DROP INDEX `project_id`,
    ADD UNIQUE KEY `target` (`project_id`, `target_field`, `target_event`),
    ADD `trigger_option` INT(1) NULL AFTER `source_event15`, 
    ADD `trigger_instrument` VARCHAR(100) NULL AFTER `trigger_option`, 
    ADD `trigger_event_id` INT(10) NULL AFTER `trigger_instrument`, 
    ADD `trigger_logic` TEXT NULL AFTER `trigger_event_id`;

ALTER TABLE `redcap_randomization` ADD FOREIGN KEY (`trigger_event_id`) REFERENCES `redcap_events_metadata`(`event_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `redcap_randomization_allocation` 
    ADD `target_field_alt` VARCHAR(100) NULL AFTER `target_field`,
    ADD `allocation_time` DATETIME NULL AFTER `is_used_by`, 
    ADD `allocation_time_utc` DATETIME NULL AFTER `allocation_time`; 