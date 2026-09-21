<?php
// Table changes
$aws_quickstart = (isset($GLOBALS['aws_quickstart']) && $GLOBALS['aws_quickstart'] == '1') ? '1' : '0';
$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('aws_quickstart', '$aws_quickstart');
";

print $sql;