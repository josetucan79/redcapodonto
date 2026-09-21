<?php

include 'config.php';

$record = array(
    'unique_role_name'           => 'U-527D39JXAC',
    'role_label'                 => 'Project Manager',
    'data_access_group'          => '1',
    'data_export_tool'           => '1',
    'mobile_app'                 => '1',
    'mobile_app_download_data'   => '1',
    'lock_records_all_forms'     => '1',
    'lock_records'               => '1',
    'lock_records_customization' => '1',
    'record_delete'              => '1',
    'record_rename'              => '1',
    'record_create'              => '1',
    'api_import'                 => '1',
    'api_export'                 => '1',
    'api_modules'                => '1',
    'data_quality_execute'       => '1',
    'data_quality_create'        => '1',
    'file_repository'            => '1',
    'logging'                    => '1',
    'data_comparison_tool'       => '1',
    'data_import_tool'           => '1',
    'calendar'                   => '1',
    'stats_and_charts'           => '1',
    'reports'                    => '1',
    'user_rights'                => '1',
    'design'                     => '1',
);

$data = json_encode( array( $record ) );

$fields = array(
	'token'   => $GLOBALS['api_token'],
	'content' => 'userRole',
	'format'  => 'json',
	'data'    => $data,
);

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $GLOBALS['api_url']);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
curl_setopt($ch, CURLOPT_VERBOSE, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_AUTOREFERER, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

$output = curl_exec($ch);
print $output;
curl_close($ch);
