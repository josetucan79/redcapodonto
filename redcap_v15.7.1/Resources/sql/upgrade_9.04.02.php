<?php

$sql = "
ALTER TABLE `redcap_reports` ADD `remove_line_breaks_in_values` INT(1) NOT NULL DEFAULT '1' AFTER `output_missing_data_codes`;
";

print $sql;