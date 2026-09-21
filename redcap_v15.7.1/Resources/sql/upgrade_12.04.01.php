<?php

$sql = "
-- Add config setting for 2FA
REPLACE INTO redcap_config (field_name, value) VALUES ('two_factor_auth_enforce_table_users_only', '0');
";

print $sql;