<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES
('rich_text_attachment_embed_enabled', '1');
    
CREATE TABLE `redcap_docs_attachments` (
`docs_id` int(10) NOT NULL,
PRIMARY KEY (`docs_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `redcap_docs_attachments`
ADD FOREIGN KEY (`docs_id`) REFERENCES `redcap_docs` (`docs_id`) ON DELETE CASCADE ON UPDATE CASCADE;
";

print $sql;