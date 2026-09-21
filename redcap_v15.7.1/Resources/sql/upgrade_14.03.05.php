<?php

$sql = "
ALTER TABLE `redcap_mycap_projects`
	ADD `event_display_format` enum('ID','LABEL','NONE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NONE';
";

print $sql;