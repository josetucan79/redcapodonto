<?php

$sql = "";

// This same code below was added to 15.0.24 LTS. This means that anyone upgrading from 15.0.24 or higher (but less than 15.1.0), should NOT rerun this script.
if (!(Upgrade::getDecVersion($current_version) >= 150024 && Upgrade::getDecVersion($current_version) < 150100))
{
	// SQL to add new field to redcap_pdf_image_cache table
	$sql .= "
ALTER TABLE `redcap_pdf_image_cache` ADD `num_pages` int(5) DEFAULT NULL AFTER `page`;
";
}

$sql .= "
INSERT IGNORE INTO redcap_config (field_name, value) VALUES ('do_not_reply_email', '');
INSERT IGNORE INTO redcap_config (field_name, value) VALUES ('two_factor_auth_esign_once_per_session', '0');
ALTER TABLE `redcap_projects` ADD `two_factor_project_esign_once_per_session` TINYINT(1) DEFAULT NULL AFTER `two_factor_force_project`;
ALTER TABLE `redcap_alerts` ADD `do_not_clear_recurrences` tinyint(1) NOT NULL DEFAULT '0' AFTER `ensure_logic_still_true`;
";


print $sql;