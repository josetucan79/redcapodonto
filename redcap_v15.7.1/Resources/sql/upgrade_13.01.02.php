<?php

$sql = "
-- Add column
ALTER TABLE `redcap_log_view_requests` ADD `is_cron` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Is this the REDCap cron job?' AFTER `is_ajax`;
-- Remove duplicate public report hashes
update redcap_reports b, (select r.hash, min(r.report_id) as orig_hash_report_id from redcap_reports r 
where r.hash is not null group by r.hash having count(*) > 1) a 
set b.hash = null, is_public = 0
where a.orig_hash_report_id != b.report_id and b.hash = a.hash;
-- Add unique key
ALTER TABLE `redcap_reports` ADD UNIQUE(`hash`);
";

print $sql;