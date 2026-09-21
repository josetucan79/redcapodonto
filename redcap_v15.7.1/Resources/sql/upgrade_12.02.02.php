<?php

$sql = "
-- added column for new survey setting 'pdf_save_translated', default value to 0
ALTER TABLE `redcap_surveys` ADD `pdf_save_translated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pdf_save_to_event_id`;
";
print $sql;