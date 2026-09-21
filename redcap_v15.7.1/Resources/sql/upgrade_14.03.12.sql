-- New config settings
REPLACE INTO redcap_config (field_name, value) VALUES ('total_cron_instances_max', '20');
REPLACE INTO redcap_config (field_name, value) VALUES ('disable_strict_transport_security_header', '0');
-- New validation type
REPLACE INTO `redcap_validation_types` (`validation_name`, `validation_label`, `regex_js`, `regex_php`, `data_type`, `legacy_value`, `visible`) VALUES
('phone_france', 'Phone (France) (xx xx xx xx xx)(+33 x xx xx xx xx)',
'/^(?:(?:\\+|00)(?:33|262|508|590|594|596|687)[\\s.-]{0,3}(?:\\(0\\)[\\s.-]{0,3})?|0)[1-9](?:(?:[\\s.-]?\\d{2}){4}|\\d{2}(?:[\\s.-]?\\d{3}){2})$/',
'/^(?:(?:\\+|00)(?:33|262|508|590|594|596|687)[\\s.-]{0,3}(?:\\(0\\)[\\s.-]{0,3})?|0)[1-9](?:(?:[\\s.-]?\\d{2}){4}|\\d{2}(?:[\\s.-]?\\d{3}){2})$/', 'phone', NULL, '0');