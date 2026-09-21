<?php

$sql = <<<EOF
INSERT INTO redcap_config (field_name, value) VALUES
('pdf_econsent_filesystem_container', ''),
('record_locking_pdf_vault_filesystem_container', ''),
('file_upload_vault_filesystem_container', '');
EOF;


print $sql;