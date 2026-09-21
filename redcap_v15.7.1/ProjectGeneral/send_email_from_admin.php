<?php
// Must be SUPER USER to use this page
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Make sure request is Post and has all parameters needed
if (!$isAjax || !SUPER_USER || $_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['from']) || !isset($_POST['to']) || !isset($_POST['message'])) {
    exit("0");
}

// Set blank value for subject, if missing
if (!isset($_POST['subject'])) $_POST['subject'] = '';
// Unescape parameters
$_POST['from'] = strtolower(strip_tags(label_decode($_POST['from'])));
$_POST['to'] = strip_tags(label_decode($_POST['to']));
$_POST['subject'] = strip_tags(label_decode($_POST['subject']));
$_POST['message'] = decode_filter_tags($_POST['message']);

// The From address must match a user's emails in their Profile
$allowedFromAddresses = [strtolower($user_email)];
if ($user_email2 != '') $allowedFromAddresses[] = strtolower($user_email2);
if ($user_email3 != '') $allowedFromAddresses[] = strtolower($user_email3);
if (!in_array($_POST['from'], $allowedFromAddresses)) {
    exit("0");
}

// Set up email to be sent
$email = new Message();
$email->setFrom($_POST['from']);
if (isset($_POST['from_display_name'])) $email->setFromName($_POST['from_display_name']);
$email->setTo($_POST['to']);
$email->setSubject($_POST['subject']);
$email->setBody('<html><body style="font-family:arial,helvetica;">'.nl2br($_POST['message']).'</body></html>');
if ($email->send()) {
	Logging::logEvent("","","MANAGE",$_POST['to'],"From: {$_POST['from']}\nTo: {$_POST['to']}\nSubject: {$_POST['subject']}\nMessage:\n{$_POST['message']}\n","Send email to user from admin (recipient: {$_POST['to']})");
    exit("1");
}
exit("0");