-- This query updates the 'ehr_id' in the 'redcap_ehr_fhir_logs' table where it is currently NULL.
UPDATE redcap_ehr_fhir_logs AS logs
INNER JOIN redcap_projects AS projects ON logs.project_id = projects.project_id
LEFT JOIN (
    SELECT ehr_id
    FROM redcap_ehr_settings
    ORDER BY `order` ASC
    LIMIT 1
) AS settings ON 1 = 1
SET logs.ehr_id = COALESCE(projects.ehr_id, settings.ehr_id)
WHERE logs.ehr_id IS NULL;

-- MyCap-related changes
ALTER TABLE `redcap_mycap_tasks_schedules`
    ADD `active` int(1) NOT NULL DEFAULT '1' COMMENT 'Is it currently active?';
ALTER TABLE `redcap_projects`
    ADD `task_complete_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Task completion status when submitted from app-side. 0 - Incomplete, 1 - Unverified, 2 - Complete';