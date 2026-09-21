-- Fix possibly messed up validation type
REPLACE INTO `redcap_validation_types` (`validation_name`, `validation_label`, `regex_js`, `regex_php`, `data_type`, `visible`)
VALUES ('time_hh_mm_ss', 'Time (HH:MM:SS)', '/^(\\d|[01]\\d|(2[0-3]))(:[0-5]\\d){2}$/', '/^(\\d|[01]\\d|(2[0-3]))(:[0-5]\\d){2}$/', 'time', 1);