<?php

$sql = "
ALTER TABLE `redcap_data_import` CHANGE `date_format` `date_format` enum('YMD','MDY','DMY') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'YMD';    
";


print $sql;