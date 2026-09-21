<?php

$sql = "
ALTER TABLE `redcap_alerts` ADD `sendgrid_mail_send_configuration` TEXT NULL DEFAULT NULL AFTER `sendgrid_template_data`;
";

print $sql;
