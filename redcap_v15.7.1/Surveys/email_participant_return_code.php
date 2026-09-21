<?php


// Check if coming from survey or authenticated form

use MultiLanguageManagement\MultiLanguage;
use REDCap\Context;

if (isset($_GET['s']) && !empty($_GET['s']))
{
	// Call config_functions before config file in this case since we need some setup before calling config
	require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
	// Validate and clean the survey hash, while also returning if a legacy hash
	$hash = $_GET['s'] = Survey::checkSurveyHash();
	// Set all survey attributes as global variables
	Survey::setSurveyVals($hash);
	// Now set $_GET['pid'] before calling config
	$_GET['pid'] = $project_id;
	// Set flag for no authentication for survey pages
	defined("NOAUTH") or define("NOAUTH", true);
} else {
    exit("0");
}

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id']))
{
	$_GET['survey_id'] = Survey::getSurveyId();
}

// Ensure the survey_id belongs to this project and that the participant_id was given
if (!Survey::checkSurveyProject($_GET['survey_id'])
	|| !isset($_GET['participant_id']) || !isinteger($_GET['participant_id'])
	|| !isset($_GET['event_id']) || !isinteger($_GET['event_id'])
)
{
	exit("0");
}

// Obtain current arm_id
$_GET['arm_id'] = getArmId();

// Retrieve survey info
$sql = "select s.*, p.*, r.record, r.instance from redcap_surveys s, redcap_surveys_participants p 
		left join redcap_surveys_response r on p.participant_id = r.participant_id
		where s.survey_id = {$_GET['survey_id']} and p.hash = '".db_escape($hash)."'
		and s.survey_id = p.survey_id and p.event_id = {$_GET['event_id']}
		order by p.participant_email desc limit 1";
$q = db_query($sql);
foreach (db_fetch_assoc($q) as $key => $value)
{
	$$key = trim(html_entity_decode($value??"", ENT_QUOTES));
	if ($key == 'participant_email') {
		// Determine if a public survey (using public survey link, aka participant_email=null), as opposed to a participant list response or an existing record)
		$public_survey = ($value === null);
	}
}

//If this is an emailed public survey, retrieve participant's email address from URL
if ($participant_email == "" && isset($_GET['email'])) {
	if ($_GET['email'] != "") {
		$participant_email = urldecode(trim($_GET['email']));
	} elseif (!$public_survey) {
		// If this is a follow-up survey where we don't have an email address, so return nothing (no error)
        $records = Survey::getRecordFromPartId(array($participant_id));
        $record = $records[$participant_id];
        $part_list = Survey::getResponsesEmailsIdentifiers([$record], $_GET['survey_id']);
		$participant_email = $part_list[$record]['email'] ?? "";
		if ($participant_email == "") exit("2");
	} else {
		// Return error since we can't find an email
		exit("0");
	}
}

// Find a suitable FROM address (rather than using the global project_contact_name).
// Obtain the email address from which this record last received a survey invitation.
$sql = "select sender from redcap_outgoing_email_sms_log where project_id = $project_id and record = '".db_escape($record)."'
		and category in ('SURVEY_INVITE_MANUAL', 'SURVEY_INVITE_ASI') AND type='EMAIL' order by email_id desc limit 1";
$q = db_query($sql);
if (db_num_rows($q)) {
	$fromEmail = db_result($q, 0);
	$fromName = "";
} else {
	$fromEmail = $GLOBALS['project_contact_email'];
	$fromName = $GLOBALS['project_contact_name'];
}

// Set survey link
$survey_link = APP_PATH_SURVEY_FULL.'?s='.$_GET['s'];
if ($public_survey) $survey_link .= '&__return=1';

$email_vals = array(
	"title" => $title,
	"survey_135" => $lang["survey_135"],
	"survey_141" => $lang["survey_141"],
	"survey_143" => $lang["survey_143"],
	"survey_144" => $lang["survey_144"],
	"survey_495" => $lang["survey_495"],
	"survey_584" => $lang["survey_584"],
	"survey_1360" => $lang["survey_1360"],
	"survey_1514" => $lang["survey_1514"],
);

// Translate the email - in case multilanguage is set up, this will
// substitute values in $email_vals with the appropriate translations
$context = Context::Builder()
	->is_survey()
	->project_id($project_id)
	->event_id($event_id)
	->instance($instance)
	->instrument($form_name)
	->record($record)
	->lang_id($_COOKIE[MultiLanguage::SURVEY_COOKIE] ?? null) // Set current cookie lang, as otherwise designated field may override
	->Build();
$lang_id = MultiLanguage::translateSurveyLinkEmail($context, $email_vals);

// Set email body
$emailContents = '
	<html><body style="font-family:arial,helvetica;font-size:10pt;">
	'.strip_tags($email_vals["survey_141"]).'<br><br>
	'.strip_tags($email_vals["title"] == "" ? $email_vals['survey_1514'] : RCView::interpolateLanguageString($email_vals["survey_1360"], array($email_vals["title"]))).'
	'.(Survey::surveyLoginEnabled() 
		? $email_vals["survey_584"]
		: ($save_and_return_code_bypass == '1' ? $email_vals["survey_584"] : ($email_vals["survey_143"].($public_survey ? "" : " ".$email_vals["survey_495"])))
	).'<br><br>
	<a href="'.$survey_link.'">'.($email_vals["title"] == "" ? $survey_link : strip_tags($email_vals["title"])).'</a><br><br>
	'.$email_vals["survey_135"]
	// If not a public survey, let participant know they can contact the survey admin to retrieve their return code.
	.'<br>'.$survey_link.'
	</body></html>';
//Send email
$email = new Message($project_id, $record, $event_id, $form_name, $instance);
$email->setTo($participant_email);
$email->setFrom($fromEmail);
$email->setFromName($fromName);
$email->setSubject(strip_tags($email_vals["survey_144"]));
$email->setBody($emailContents);
// Return "0" for failure or email if successful
print ($email->send() ? strip_tags($participant_email) : "0");
