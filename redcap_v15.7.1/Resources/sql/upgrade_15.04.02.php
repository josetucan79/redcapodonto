<?php
// Add new field 'dag_id' to MyCap pages, contacts, links to make them DAG-specific
$sql = "
ALTER TABLE `redcap_mycap_aboutpages`
ADD `dag_id` int(11) DEFAULT NULL COMMENT 'DAG specific page',
ADD FOREIGN KEY (`dag_id`) REFERENCES `redcap_data_access_groups` (`group_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `redcap_mycap_contacts`
ADD `dag_id` int(11) DEFAULT NULL COMMENT 'DAG specific contact',
ADD FOREIGN KEY (`dag_id`) REFERENCES `redcap_data_access_groups` (`group_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `redcap_mycap_links`
ADD `dag_id` int(11) DEFAULT NULL COMMENT 'DAG specific link',
ADD FOREIGN KEY (`dag_id`) REFERENCES `redcap_data_access_groups` (`group_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `redcap_projects`
ADD `form_activation_mycap_support` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Apply Form Display logic at MyCap app-side?';

REPLACE INTO redcap_config (field_name, value) VALUES ('openid_connect_override_scope', '');
";


print $sql;