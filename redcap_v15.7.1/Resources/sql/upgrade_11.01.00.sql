ALTER TABLE  `redcap_alerts` ADD  `alert_order` int(10) DEFAULT NULL;
REPLACE INTO redcap_config (field_name, value) VALUES ('db_binlog_format', '');
REPLACE INTO redcap_config (field_name, value) VALUES ('two_factor_auth_twilio_from_number_voice_alt', '');
ALTER TABLE `redcap_record_list` CHANGE `arm` `arm` INT(2) NOT NULL;
ALTER TABLE `redcap_record_dashboards` CHANGE `arm` `arm` INT(2) NULL DEFAULT NULL;