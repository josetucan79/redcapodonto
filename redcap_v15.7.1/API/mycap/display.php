<?php
use Vanderbilt\REDCap\Classes\MyCap\MyCapApi;
use Vanderbilt\REDCap\Classes\MyCap\MyCapConfiguration;
use Vanderbilt\REDCap\Classes\MyCap\Api\DB\Project;

global $format, $post;
defined('NOAUTH') or define('NOAUTH', true);
$returnFormat = 'json';

$mc = new MyCapConfiguration();
$hmacKey = MyCapConfiguration::$hMacKey;
if (isset($post['stu_code'])) {
    try {
        $myProj = new Project();
        $projects = $myProj->loadByCode($post['stu_code']);
        $hmacKey = $projects['hmac_key'];
    } catch (Exception $e) {

    }
}
unset($post['content'], $post['hmac_key']);
// error_log("---- post array in mycap/display.php ----");
// error_log(print_r($post, true));
$myCapApi = new MyCapApi(array('hmacKey' => $hmacKey));
$myCapApi->processRequest($post);