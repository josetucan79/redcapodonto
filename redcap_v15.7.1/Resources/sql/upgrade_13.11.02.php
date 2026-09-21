<?php

$sql = "
-- new config setting
REPLACE INTO redcap_config (field_name, value) VALUES ('allow_auto_variable_naming', '1');
";

print $sql;