<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('bulk_record_delete_enable_global', '1');
";

print $sql;
