<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES ('oauth2_azure_ad_name', '');
";

print $sql;