-- Add the 'delete_project_day_lag' setting with a default value of 30 if it doesn't exist.
INSERT IGNORE INTO redcap_config (field_name, value) VALUES ('delete_project_day_lag', '30');