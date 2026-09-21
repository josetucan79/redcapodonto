<?php

$sql = "
INSERT INTO redcap_config (field_name, value) VALUES ('external_modules_allow_activation_user_request', '1');
";

print $sql;