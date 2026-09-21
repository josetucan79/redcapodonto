<?php

$sql = "
-- new config value
replace into redcap_config (field_name, value) values ('read_replica_enable', '0');
";

print $sql;