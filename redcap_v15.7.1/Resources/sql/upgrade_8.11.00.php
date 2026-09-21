<?php
// Table changes
$azure_quickstart = (isset($GLOBALS['azure_quickstart']) && $GLOBALS['azure_quickstart'] == '1') ? '1' : '0';
$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('azure_quickstart', '$azure_quickstart');
";

print $sql;