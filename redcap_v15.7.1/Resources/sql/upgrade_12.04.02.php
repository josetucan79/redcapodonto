<?php

$sql = "
delete from redcap_external_module_settings where project_id is not null and project_id not in (select project_id from redcap_projects);
";

print $sql;