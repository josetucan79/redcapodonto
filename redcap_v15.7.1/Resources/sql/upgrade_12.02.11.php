<?php

// Add S3 endpoint custom URL
$sql = "replace into redcap_config (field_name, value) values ('amazon_s3_endpoint_url', '');";

print $sql;