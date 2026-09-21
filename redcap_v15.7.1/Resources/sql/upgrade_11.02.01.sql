-- Added 2 config variables for password complexity settings
INSERT INTO redcap_config (field_name, value) VALUES
('password_length', '9'),
('password_complexity', '1');