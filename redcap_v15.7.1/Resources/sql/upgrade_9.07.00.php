<?php

$sql = "
-- Enable the REDCap URL Shortener
UPDATE redcap_config SET value = '1' WHERE field_name = 'enable_url_shortener_redcap';
";


print $sql;