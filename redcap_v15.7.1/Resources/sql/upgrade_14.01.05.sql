-- Extend runtime of a cron job
UPDATE `redcap_crons` SET `cron_max_run_time` = '7200' WHERE cron_name = 'UnicodeFixProjectLevel';