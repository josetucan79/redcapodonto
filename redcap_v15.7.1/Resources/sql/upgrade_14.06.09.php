<?php

$sql = "
ALTER TABLE `redcap_mycap_projects` CHANGE `baseline_date_field` `baseline_date_field` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'baseline date field_name';

REPLACE INTO redcap_config (field_name, value) VALUES ('shibboleth_set_userinfo', '0');
REPLACE INTO redcap_config (field_name, value) VALUES ('shibboleth_override_userinfo', '0');
REPLACE INTO redcap_config (field_name, value) VALUES ('shibboleth_user_firstname_field', '');
REPLACE INTO redcap_config (field_name, value) VALUES ('shibboleth_user_lastname_field', '');
REPLACE INTO redcap_config (field_name, value) VALUES ('shibboleth_user_email_field', '');
";

print $sql;