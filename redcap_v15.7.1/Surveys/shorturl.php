<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

if (Survey::checkSurveyProject($_GET['survey_id']) && isset($_GET['hash']) && $GLOBALS['enable_url_shortener'])
{
	// Retrieve shortened URL from URL shortener service
	$shorturl_status = getREDCapShortUrl(APP_PATH_SURVEY_FULL . '?s=' . $_GET['hash']);
	if (isset($shorturl_status['error'])) exit($shorturl_status['error']);
	if (!isset($shorturl_status['url_short'])) exit("0");
	exit($shorturl_status['url_short']);
}

// If failed
exit("0");
