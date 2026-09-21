<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('openid_connect_logout', '');
";

print $sql;