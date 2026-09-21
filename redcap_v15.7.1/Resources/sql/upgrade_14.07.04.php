<?php
$sql = <<<EOF
ALTER TABLE `redcap_user_information` CHANGE `two_factor_auth_secret` `two_factor_auth_secret` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
EOF;

print $sql;