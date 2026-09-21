<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('two_factor_auth_esign_pin', '0');
";

print $sql;