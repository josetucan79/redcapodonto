<?php

$sql = "
CREATE TABLE `redcap_econsent` (
`consent_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`survey_id` int(10) DEFAULT NULL,
`version` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`active` tinyint(1) NOT NULL DEFAULT '0',
`type_label` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`custom_econsent_label` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`firstname_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`firstname_event_id` int(11) DEFAULT NULL,
`lastname_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`lastname_event_id` int(11) DEFAULT NULL,
`dob_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`dob_event_id` int(11) DEFAULT NULL,
`allow_edit` tinyint(1) NOT NULL DEFAULT '0',
`signature_field1` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`signature_field2` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`signature_field3` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`signature_field4` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`signature_field5` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`consent_form_location_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Display consent form below this field',
PRIMARY KEY (`consent_id`),
UNIQUE KEY `survey_id` (`survey_id`),
KEY `dob_event_id` (`dob_event_id`),
KEY `firstname_event_id` (`firstname_event_id`),
KEY `lastname_event_id` (`lastname_event_id`),
KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_econsent_forms` (
`consent_form_id` int(10) NOT NULL AUTO_INCREMENT,
`consent_id` int(10) DEFAULT NULL,
`version` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`consent_form_active` tinyint(1) DEFAULT NULL COMMENT 'null=Inactive, 1=Active',
`creation_time` datetime DEFAULT NULL,
`uploader` int(10) DEFAULT NULL,
`consent_form_pdf_doc_id` int(10) DEFAULT NULL COMMENT 'Consent form PDF document',
`consent_form_richtext` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Consent form text (alternate to PDF)',
`consent_form_filter_dag_id` int(10) DEFAULT NULL COMMENT 'Consent form DAG filter',
`consent_form_filter_lang_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Consent form MLM filter',
PRIMARY KEY (`consent_form_id`),
UNIQUE KEY `consent_id_version_active_dag_lang` (`consent_id`,`version`,`consent_form_active`,`consent_form_filter_dag_id`,`consent_form_filter_lang_id`),
KEY `consent_form_filter_dag_id` (`consent_form_filter_dag_id`),
KEY `consent_form_filter_lang_id` (`consent_form_filter_lang_id`),
KEY `consent_form_pdf_doc_id` (`consent_form_pdf_doc_id`),
KEY `uploader` (`uploader`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_pdf_snapshots` (
`snapshot_id` int(10) NOT NULL AUTO_INCREMENT,
`project_id` int(10) DEFAULT NULL,
`active` tinyint(1) NOT NULL DEFAULT '0',
`name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`custom_filename_prefix` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`consent_id` int(10) DEFAULT NULL COMMENT 'Used for eConsent',
`trigger_surveycomplete_survey_id` int(10) DEFAULT NULL COMMENT 'Trigger based on survey completion',
`trigger_surveycomplete_event_id` int(10) DEFAULT NULL,
`trigger_logic` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Trigger based on logic',
`selected_forms_events` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Instruments/events to include in snapshot',
`pdf_save_to_file_repository` tinyint(1) NOT NULL DEFAULT '0',
`pdf_save_to_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`pdf_save_to_event_id` int(10) DEFAULT NULL,
`pdf_save_translated` tinyint(1) NOT NULL DEFAULT '0',
`pdf_compact` tinyint(1) NOT NULL DEFAULT '1',
PRIMARY KEY (`snapshot_id`),
UNIQUE KEY `consent_survey_id` (`consent_id`,`trigger_surveycomplete_survey_id`),
KEY `pdf_save_to_event_id` (`pdf_save_to_event_id`),
KEY `project_id_active_name` (`project_id`,`active`,`name`),
KEY `survey_id_active_name` (`trigger_surveycomplete_survey_id`,`active`,`name`),
KEY `trigger_surveycomplete_event_id` (`trigger_surveycomplete_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redcap_pdf_snapshots_triggered` (
`snapshot_id` int(10) NOT NULL,
`record` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
PRIMARY KEY (`snapshot_id`,`record`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_econsent`
ADD FOREIGN KEY (`dob_event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`firstname_event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`lastname_event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`survey_id`) REFERENCES `redcap_surveys` (`survey_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_econsent_forms`
ADD FOREIGN KEY (`consent_form_filter_dag_id`) REFERENCES `redcap_data_access_groups` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`consent_form_pdf_doc_id`) REFERENCES `redcap_edocs_metadata` (`doc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`consent_id`) REFERENCES `redcap_econsent` (`consent_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`uploader`) REFERENCES `redcap_user_information` (`ui_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `redcap_pdf_snapshots`
ADD FOREIGN KEY (`consent_id`) REFERENCES `redcap_econsent` (`consent_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`pdf_save_to_event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD FOREIGN KEY (`project_id`) REFERENCES `redcap_projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`trigger_surveycomplete_event_id`) REFERENCES `redcap_events_metadata` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD FOREIGN KEY (`trigger_surveycomplete_survey_id`) REFERENCES `redcap_surveys` (`survey_id`) ON DELETE CASCADE ON UPDATE CASCADE;
                                                                                                         
ALTER TABLE `redcap_pdf_snapshots_triggered`
ADD FOREIGN KEY (`snapshot_id`) REFERENCES `redcap_pdf_snapshots` (`snapshot_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `redcap_surveys_pdf_archive` 
    ADD `consent_id` INT(10) NULL DEFAULT NULL AFTER `doc_id`, 
    ADD INDEX `consent_id_record` (`consent_id`, `record`),
    ADD FOREIGN KEY (`consent_id`) REFERENCES `redcap_econsent`(`consent_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD `consent_form_id` INT(10) NULL DEFAULT NULL AFTER `consent_id`, 
    ADD INDEX `consent_form_id_record` (`consent_form_id`, `record`),
    ADD FOREIGN KEY (`consent_form_id`) REFERENCES `redcap_econsent_forms`(`consent_form_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD `snapshot_id` INT(10) NULL DEFAULT NULL AFTER `consent_form_id`, 
    ADD INDEX `snapshot_id_record` (`snapshot_id`, `record`),
    ADD FOREIGN KEY (`snapshot_id`) REFERENCES `redcap_pdf_snapshots`(`snapshot_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD `contains_completed_consent` TINYINT(1) NOT NULL DEFAULT '0' AFTER `consent_form_id`;

ALTER TABLE `redcap_projects` 
    ADD `allow_econsent_allow_edit` TINYINT(1) NOT NULL DEFAULT '1' COMMENT 'Set to 0 to prevent users from modifying a completed e-Consent response in the project.' AFTER `ehr_id`;
ALTER TABLE `redcap_projects` 
    ADD `store_in_vault_snapshots_containing_completed_econsent` TINYINT(1) NOT NULL DEFAULT '1' COMMENT 'Regarding non-e-Consent governed snapshots only, store in Vault (if enabled) if snapshot contains a completed e-Consent response?' AFTER `allow_econsent_allow_edit`;

-- Copy eConsent settings from surveys table into eConsent table
replace into redcap_econsent (project_id, survey_id, active, version, type_label, firstname_field, firstname_event_id, 
    lastname_field, lastname_event_id, dob_field, dob_event_id, allow_edit, signature_field1, signature_field2, signature_field3, signature_field4, signature_field5)
select s.project_id, s.survey_id, '1', s.pdf_econsent_version, s.pdf_econsent_type, 
    s.pdf_econsent_firstname_field, (select e.event_id from redcap_events_metadata e where e.event_id = s.pdf_econsent_firstname_event_id) as pdf_econsent_firstname_event_id,
    s.pdf_econsent_lastname_field, (select e.event_id from redcap_events_metadata e where e.event_id = s.pdf_econsent_lastname_event_id) as pdf_econsent_lastname_event_id,
    s.pdf_econsent_dob_field, (select e.event_id from redcap_events_metadata e where e.event_id = s.pdf_econsent_dob_event_id) as pdf_econsent_dob_event_id,
    s.pdf_econsent_allow_edit, s.pdf_econsent_signature_field1, s.pdf_econsent_signature_field2, s.pdf_econsent_signature_field3, s.pdf_econsent_signature_field4, s.pdf_econsent_signature_field5
    from redcap_surveys s where s.pdf_auto_archive = 2 and s.survey_id not in (select survey_id from redcap_econsent) 
    and s.project_id in (select project_id from redcap_projects) 
    order by s.project_id, s.survey_id;

-- Copy eConsent settings, PDF Auto-Archiver settings, and PDF-to-field setting from surveys table to Record Snapshot table
replace into redcap_pdf_snapshots (project_id, trigger_surveycomplete_survey_id, selected_forms_events, active, pdf_save_to_file_repository, 
    pdf_save_to_field, pdf_save_to_event_id, pdf_save_translated, consent_id, custom_filename_prefix)
select s.project_id, s.survey_id, if(s.pdf_auto_archive=1 or s.pdf_save_to_field is not null, concat(':',s.form_name), null), '1', if(s.pdf_auto_archive > 0, 1, 0), 
    s.pdf_save_to_field,
    (select e.event_id from redcap_events_metadata e where e.event_id = s.pdf_save_to_event_id) as pdf_save_to_event_id,
    s.pdf_save_translated,
    (select e.consent_id from redcap_econsent e where e.survey_id = s.survey_id) as consent_id,
    if(s.pdf_auto_archive=0,
        '[instrument-label]', 
        if(
            s.pdf_auto_archive=2, 
            concat(
                if(s.pdf_econsent_firstname_event_id is not null and s.pdf_econsent_firstname_field is not null,'[firstname_event_id-replace]',''), 
                ifnull(concat('[',s.pdf_econsent_firstname_field,']_'),''),
                if(s.pdf_econsent_lastname_event_id is not null and s.pdf_econsent_lastname_field is not null,'[lastname_event_id-replace]',''), 
                ifnull(concat('[',s.pdf_econsent_lastname_field,']_'),''),
                if(s.pdf_econsent_dob_event_id is not null and s.pdf_econsent_dob_field is not null,'[dob_event_id-replace]',''), 
                ifnull(concat('[',s.pdf_econsent_dob_field,']_'),''),
                'pid[project-id]_form[instrument-label]_id[record-name]'
            ),
            'pid[project-id]_form[instrument-label]_id[record-name]'
        )
    ) as custom_filename_prefix
    from redcap_surveys s where (s.pdf_auto_archive > 0 or s.pdf_save_to_field is not null) 
    and s.survey_id not in (select trigger_surveycomplete_survey_id from redcap_pdf_snapshots where trigger_surveycomplete_survey_id is not null) 
    and s.project_id in (select project_id from redcap_projects) 
    order by s.project_id, s.survey_id;

-- Backfill new consent_ids and snapshot_ids into redcap_surveys_pdf_archive
update redcap_surveys_pdf_archive a, redcap_econsent e 
    set a.consent_id = e.consent_id, a.contains_completed_consent = 1
    where e.survey_id = a.survey_id;
update redcap_surveys_pdf_archive a, redcap_pdf_snapshots e
    set a.snapshot_id = e.snapshot_id 
    where a.survey_id = e.trigger_surveycomplete_survey_id;

-- Backfill already-triggered records into redcap_pdf_snapshots_triggered
replace into redcap_pdf_snapshots_triggered (snapshot_id, record)
select distinct s.snapshot_id, a.record from redcap_surveys_pdf_archive a, redcap_pdf_snapshots s
where a.survey_id = s.trigger_surveycomplete_survey_id and s.pdf_save_to_file_repository = 1;

-- Add new DRW setting
ALTER TABLE `redcap_projects` ADD `drw_hide_closed_queries_from_dq_results` TINYINT(1) NOT NULL DEFAULT '1' 
    COMMENT 'Hide closed and verified DRW data queries from Data Quality results' AFTER `field_comment_edit_delete`;
";

print $sql;