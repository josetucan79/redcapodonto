<?php namespace ExternalModules;
require_once __DIR__ . '/../redcap_connect.php';

foreach([
    'is_development_server' => '1',
    'redcap_base_url' => 'http://127.0.0.1/' // Required for the isManagerPage() test to work
] as $fieldName=>$value){
    ExternalModules::query('
        update redcap_config
        set value = ?
        where field_name = ?
    ', [$value, $fieldName]);
}

ExternalModules::query("
    insert into redcap_external_modules_downloads
    values ('some_module_v1.0.0', 1, now(), now())
", []);

// Required for the testSettingSizeLimit() test
ExternalModules::query("
    SET GLOBAL max_allowed_packet = 67108864;
", []);
