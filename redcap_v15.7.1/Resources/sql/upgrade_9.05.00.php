<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('enable_url_shortener_redcap', '0');
";

print $sql;