<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES
('oauth2_azure_ad_username_attribute', 'userPrincipalName');
";


print $sql;