<?php

$sql = "
ALTER TABLE `redcap_multilanguage_metadata` CHANGE `hash` `hash` char(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `redcap_multilanguage_metadata_temp` CHANGE `hash` `hash` char(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `redcap_multilanguage_ui` CHANGE `hash` `hash` char(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `redcap_multilanguage_ui_temp` CHANGE `hash` `hash` char(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
";


print $sql;