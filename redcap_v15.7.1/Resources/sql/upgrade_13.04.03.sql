INSERT INTO `redcap_validation_types` (`validation_name`, `validation_label`, `regex_js`, `regex_php`, `data_type`, `legacy_value`, `visible`) VALUES
('postalcode_uk', 'Postal Code (UK)', '/^(([A-Z]{1,2}\\d{1,2})|([A-Z]{1,2}\\d[A-Z])) \\d[ABD-HJLNP-Z]{2}$/', '/^(([A-Z]{1,2}\\d{1,2})|([A-Z]{1,2}\\d[A-Z])) \\d[ABD-HJLNP-Z]{2}$/', 'postal_code', NULL, 0);

ALTER TABLE `redcap_user_information` ADD `two_factor_auth_enrolled` TINYINT(1) NOT NULL DEFAULT '0' AFTER `two_factor_auth_secret`;