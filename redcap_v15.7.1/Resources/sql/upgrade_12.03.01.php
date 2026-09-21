<?php

$sql = "
-- Add config setting to disable the e-signature feature
REPLACE INTO redcap_config (field_name, value) VALUES ('esignature_enabled_global', '1');
";

print $sql;