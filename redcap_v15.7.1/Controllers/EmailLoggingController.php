<?php

class EmailLoggingController extends Controller
{
	// Render the main search page
	public function index()
	{
		$this->render('HeaderProject.php', $GLOBALS);
		$el = new EmailLogging();
		$el->renderPage();
		$this->render('FooterProject.php');
	}

	// Perform the search and return the search results
	public function search()
	{
		// Set timestamp filter format
		$_POST['beginTime'] = isset($_POST['beginTime']) ? substr($_POST['beginTime'], 0, 16) : "";
		$_POST['endTime'] = isset($_POST['endTime']) ? substr($_POST['endTime'], 0, 16) : "";
		$beginTime_userPref = (isset($_POST['beginTime']) && $_POST['beginTime'] != "") ? str_replace(array("`","="), array("",""), strip_tags(label_decode(urldecode($_POST['beginTime'])))) : '';
		$endTime_userPref   = (isset($_POST['endTime']) && $_POST['endTime'] != "") ? str_replace(array("`","="), array("",""), strip_tags(label_decode(urldecode($_POST['endTime'])))) : '';
		$beginTime_YMDts = DateTimeRC::format_ts_to_ymd($beginTime_userPref);
		if ($beginTime_YMDts != '') $beginTime_YMDts .= ":00";
		$endTime_YMDts = DateTimeRC::format_ts_to_ymd($endTime_userPref);
		if ($endTime_YMDts != '') $endTime_YMDts .= ":00";
		// Search
		$el = new EmailLogging();
		$el->search($_POST['term']??"", $_POST['target']??"", $_POST['record']??"", $beginTime_YMDts, $endTime_YMDts, $_POST['category']??"");
	}

	// View an individual email
	public function view()
	{
		$el = new EmailLogging();
		$el->view($_POST['hash']);
	}

	// User is opting in to view Email Logging page
	public function optin()
	{
		$el = new EmailLogging();
		// Return error is user already opted in
		print $el->optInUser() ? "1" : "0";
	}

	// Re-send an individual email
	public function resend()
	{
		$el = new EmailLogging();
		$el->resendEmail($_POST['hash']);
	}
}