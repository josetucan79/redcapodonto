<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('dkim_private_key', '');
";

print $sql;