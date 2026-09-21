-- Fix possibly messed up password-related config settings
set @password_length = (select value from redcap_config where field_name = 'password_length');
REPLACE INTO redcap_config (field_name, value) VALUES ('password_length', if (@password_length is null, '9', trim(@password_length)));
set @password_complexity = (select value from redcap_config where field_name = 'password_complexity');
REPLACE INTO redcap_config (field_name, value) VALUES ('password_complexity', if (@password_complexity is null, '1', trim(@password_complexity)));