<?php

$sql = "
ALTER TABLE `redcap_multilanguage_config` CHANGE `value` `value` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE `redcap_multilanguage_config_temp` CHANGE `value` `value` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
";

print $sql;