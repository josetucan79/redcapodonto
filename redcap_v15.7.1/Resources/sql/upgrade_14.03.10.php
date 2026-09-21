<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('mtb_experimental_enabled', '0');
";


print $sql;