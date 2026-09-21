<?php

$sql = "
-- new config setting
REPLACE INTO redcap_config (field_name, value) VALUES ('mailgun_api_endpoint', '');

-- field containing categories for which the date range will be applied
ALTER TABLE `redcap_ehr_datamart_revisions` ADD `date_range_categories` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `fields`;
";

print $sql;