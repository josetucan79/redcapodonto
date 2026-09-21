<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('field_bank_enabled', '1');
";

print $sql;