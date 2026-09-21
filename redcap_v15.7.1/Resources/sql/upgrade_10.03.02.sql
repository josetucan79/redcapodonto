-- Update Data Mart cron Job frequency to be run once a day
UPDATE `redcap_crons` SET `cron_frequency`='86400' WHERE `cron_name`='ClinicalDataMartDataFetch';
