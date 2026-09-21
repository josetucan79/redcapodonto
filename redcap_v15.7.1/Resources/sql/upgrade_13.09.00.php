<?php

$sql = "
set @azure_comm_api_key = (select value from redcap_config where field_name = 'azure_comm_api_key');
REPLACE INTO redcap_config (field_name, value) VALUES ('azure_comm_api_key', if (@azure_comm_api_key is null, '', trim(@azure_comm_api_key)));

set @azure_comm_api_endpoint = (select value from redcap_config where field_name = 'azure_comm_api_endpoint');
REPLACE INTO redcap_config (field_name, value) VALUES ('azure_comm_api_endpoint', if (@azure_comm_api_endpoint is null, '', trim(@azure_comm_api_endpoint)));
";

print $sql;