<?php

$sql = "
ALTER TABLE `redcap_multilanguage_config` CHANGE `lang_id` `lang_id` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL;
ALTER TABLE `redcap_multilanguage_config_temp` CHANGE `lang_id` `lang_id` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL;
ALTER TABLE `redcap_multilanguage_metadata` CHANGE `lang_id` `lang_id` varchar(50) COLLATE utf8mb4_bin NOT NULL;
ALTER TABLE `redcap_multilanguage_metadata_temp` CHANGE `lang_id` `lang_id` varchar(50) COLLATE utf8mb4_bin NOT NULL;
ALTER TABLE `redcap_multilanguage_ui` CHANGE `lang_id` `lang_id` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL;
ALTER TABLE `redcap_multilanguage_ui_temp` CHANGE `lang_id` `lang_id` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL;
";


print $sql;