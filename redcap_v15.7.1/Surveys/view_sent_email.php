<?php

use REDCap\Context;
use MultiLanguageManagement\MultiLanguage;

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Validate email_recip_id
if (!(isset($_POST['email_recip_id']) && isinteger($_POST['email_recip_id'])) && !(isset($_POST['ssr_id']) && isinteger($_POST['ssr_id']))) exit("0");


// RECURRING ASI'S
if (isset($_POST['ssr_id']))
{
    $sql = "select r.record, ss.survey_id, ss.event_id, r.ssr_id, ss.delivery_type as this_delivery_type, ss.email_content, ss.email_subject,
            DATE_ADD(r.first_send_time, INTERVAL ROUND(r.times_sent * ss.num_recurrence * (if(ss.units_recurrence = 'DAYS', 1440, if(ss.units_recurrence = 'HOURS', 60, 1)))) MINUTE) as send_time,
            ss.email_sender, ss.email_sender_display
            from redcap_surveys_scheduler_recurrence r, redcap_surveys_scheduler ss, redcap_surveys s
            where r.ssr_id = {$_POST['ssr_id']} and s.project_id = $project_id and r.ss_id = ss.ss_id and ss.survey_id = s.survey_id
            limit 1";
    $q = db_query($sql);
    if (!$q || !db_num_rows($q)) exit("0");
    $email = db_fetch_assoc($q);
    // Set up all values needed for display purposes
    $record = $email['record'];
    $survey_id = $email['survey_id'];
    $email['instance'] = $_POST['instance'];
    $email['instrument'] = $Proj->surveys[$email['survey_id']]['form_name'];
    $email['append_survey_link'] = '0';
    $email['participant_email'] = '';
    $email['participant_phone'] = '';
    $time_sent = RCView::span(array('style' => 'color:#777;'),
        RCView::img(array('src' => 'clock_fill.png', 'style' => 'vertical-align:middle;')) .
        $lang['survey_394'] . RCView::SP . DateTimeRC::format_ts_from_ymd(strip_tags($_POST['send_time']))
    );
    $sendMethodText = RCView::span(array('style' => 'color:#444;font-weight:normal;'), $lang['survey_401']) . RCView::SP;
    $instructions = $lang['survey_1508'];
    // Pipe values into subject and email content
    $invitation_msg = trim(Piping::replaceVariablesInLabel(label_decode($email['email_content']), $record, $email['event_id'], $email['instance'], array(), true, $project_id, false,
                      ($Proj->isRepeatingForm($email['event_id'], $email['instrument']) ? $email['instrument'] : ""), 1, false, false, $email['instrument']));
    $subjectEmail = trim(Piping::replaceVariablesInLabel(label_decode($email['email_subject']), $record, $email['event_id'], $email['instance'], array(), true, $project_id, false,
                    ($Proj->isRepeatingForm($email['event_id'], $email['instrument']) ? $email['instrument'] : ""), 1, false, false, $email['instrument']));
    // Set "from" email address
    $username_name =  "";
    $fromEmail = $fromEmailText = $email['email_sender'];
    if (isEmail($fromEmailText) && $email['email_sender_display'] != "") {
        $fromEmailText = "{$email['email_sender_display']} &lt;{$fromEmailText}&gt;";
    }
}


// NORMAL INVITES (non-recurring)
else {
    // Get email info
    $sql = "select e.*, p.participant_id, p.hash, r.delivery_type as this_delivery_type,
            if (r.static_email is null, p.participant_email, r.static_email) as participant_email ,
            if (r.static_phone is null, p.participant_phone, r.static_phone) as participant_phone, e.append_survey_link, s.project_id
            from redcap_surveys s, redcap_surveys_emails e, redcap_surveys_emails_recipients r, redcap_surveys_participants p
            where s.project_id = " . PROJECT_ID . " and s.survey_id = e.survey_id and e.email_id = r.email_id
            and r.email_recip_id = {$_POST['email_recip_id']} and p.participant_id = r.participant_id
            and p.survey_id = s.survey_id limit 1";
    $q = db_query($sql);
    if (!db_num_rows($q)) exit("0");

    // Set values as array
    $email = db_fetch_assoc($q);

    // Set "time sent"
    $sendMethod = 'manual'; // Set default as manually sent from participant list
    $time_sent = "";
    $survey_id = null;
    $email_sender_display = "";
    // Since don't have a "time sent", get it from another table if scheduled or if failed to send
    $sql = "select q.ss_id, q.scheduled_time_to_send, q.status, q.time_sent, q.reminder_num, e.email_sender_display
            from redcap_surveys_scheduler_queue q
            left join redcap_surveys_emails_recipients r on q.email_recip_id = r.email_recip_id
            left join redcap_surveys_emails e on e.email_id = r.email_id
            where q.email_recip_id = {$_POST['email_recip_id']}";
    if ($_POST['ssq_id'] != '') $sql .= " and ssq_id = " . $_POST['ssq_id'];
    $q = db_query($sql);
    if (db_num_rows($q) > 0) {
        $emailScheduleInfo = db_fetch_assoc($q);
        // Set send method to "automatic invites" (i.e. auto)
        if (is_numeric($emailScheduleInfo['ss_id'])) {
            $sendMethod = 'auto';
            if ($emailScheduleInfo['email_sender_display'] != '') {
                $email_sender_display = $emailScheduleInfo['email_sender_display'];
            }
        }
        // Scheduled to send at specific time
        if ($emailScheduleInfo['status'] == 'QUEUED') {
            $time_sent = RCView::span(array('style' => 'color:#777;'),
                RCView::img(array('src' => 'clock_fill.png', 'style' => 'vertical-align:middle;')) .
                $lang['survey_394'] . RCView::SP . DateTimeRC::format_ts_from_ymd($emailScheduleInfo['scheduled_time_to_send'])
            );
        } elseif ($emailScheduleInfo['status'] == 'SENT') {
            $time_sent = RCView::span(array('style' => 'color:green;'),
                DateTimeRC::format_ts_from_ymd($emailScheduleInfo['time_sent'])
            );
        } elseif ($emailScheduleInfo['status'] == 'DID NOT SEND') {
            $time_sent = RCView::span(array('style' => 'color:red;'),
                DateTimeRC::format_ts_from_ymd($emailScheduleInfo['scheduled_time_to_send']) . RCView::SP . RCView::SP . $lang['survey_396']
            );
        }
        // Obtain the survey ID
        $sql = "select p.survey_id from redcap_surveys_emails_recipients r, redcap_surveys_participants p
                where r.email_recip_id = {$_POST['email_recip_id']} and r.participant_id = p.participant_id";
        $q = db_query($sql);
        if (db_num_rows($q)) {
            $survey_id = db_result($q, 0);
        }
    }
    if ($time_sent == "" && $email['email_sent'] != "") {
        $time_sent = RCView::span(array('style' => 'color:green;'), DateTimeRC::format_ts_from_ymd($email['email_sent']));
    }

    // Set text if sent manually via participant list or via automatic invites
    $sendMethodMsg = ($sendMethod == 'manual') ? $lang['survey_1238'] : $lang['survey_401'];
    $sendMethodText = RCView::span(array('style' => 'color:#444;font-weight:normal;'), $sendMethodMsg) . RCView::SP;

    // Set email subject
    $subjectEmail = label_decode($email['email_subject']);
    // If a reminder, then add [Reminder] to subject line
    $reminder_prefix = $lang['survey_732'];
    if($email["lang_id"] != null) {
        $context = Context::Builder()
        ->project_id($email["project_id"])
        ->is_survey()
        ->survey_id($email["survey_id"])
        ->record($email["record"])
        ->lang_id($email["lang_id"])
        ->Build();
        $reminder_prefix = MultiLanguage::getUITranslation($context, "survey_732");
    }
    if (is_numeric($emailScheduleInfo['reminder_num']) && $emailScheduleInfo['reminder_num'] > 0) {
        $subjectEmail = $reminder_prefix . " $subjectEmail";
    }
    // Limit to 240 chars since that's our limit in Message.php
    $subjectEmail = substr($subjectEmail, 0, 240);
    if ($subjectEmail == "") {
        $subjectEmail = RCView::span(array('style' => 'color:#777;font-weight:normal;'), $lang['survey_397']);
    }

    // Set "from" email address
    $username_name = $fromEmail = "";
    if (is_numeric($email['email_sender']) && is_numeric($email['email_account'])) {
        // Get username, name, and email address from the user's account
        $senderInfo = User::getUserInfoByUiid($email['email_sender']);
        // Set the from email address as the user's CURRENT email
        $fromEmail = ($email['email_account'] == '1') ? $senderInfo['user_email'] : $senderInfo['user_email' . $email['email_account']];
        // Set name and username string
        $username_name = "{$senderInfo['username']} &nbsp;(" .
            RCView::a(array('href' => "mailto:$fromEmail", 'style' => 'text-decoration:underline;font-weight:normal;'),
                "{$senderInfo['user_firstname']} {$senderInfo['user_lastname']}"
            ) .
            ")";
        $fromEmailText = "";
    }
    // If static email address was used, then use it instead of user's current email address
    if ($fromEmail == "" && $email['email_static'] != "") {
        $fromEmailText = $email['email_static'];
    }
    // Add display name
    if (isEmail($fromEmailText) && $email_sender_display != "") {
        $fromEmailText = "$email_sender_display &lt;{$fromEmailText}&gt;";
    }

    // See if participant has already created a record. If so, get email/identifier/record name.
    $recordArray = Survey::getRecordFromPartId(array($email['participant_id']));
    $record = (isset($recordArray[$email['participant_id']])) ? $recordArray[$email['participant_id']] : "";

    $invitation_msg = label_decode($email['email_content']);
    $instructions = $lang['survey_921'];

}



$emailIdentArray = ($record == "") ? array() : Survey::getResponsesEmailsIdentifiers(array($record), $survey_id);
$invitation_msg = nl2br($invitation_msg);

// Set "to" email address or phone
if ($email['this_delivery_type'] == 'EMAIL') {
    $toEmail = $email['participant_email'];
    if ($toEmail == "") {
        // Since didn't find email from static address or initial survey pariticpant list, use other methods to obtain it
        $toEmail = $emailIdentArray[$record]['email'];
        if ($toEmail == "") $toEmail = RCView::span(array('style' => 'color:#777;'), $lang['survey_284']);
    }
} else {
    $toEmail = $email['participant_phone'];
    if ($toEmail == "") {
        // Since didn't find email from static address or initial survey pariticpant list, use other methods to obtain it
        $toEmail = formatPhone($emailIdentArray[$record]['phone']);
        if ($toEmail == "") $toEmail = RCView::span(array('style' => 'color:#777;'), $lang['survey_284']);
    } else {
        $toEmail = formatPhone($toEmail);
    }
}

// If email/phone is from Participant List and identifiers are disabled and survey email/phone field is disabled,
// then do NOT display the email/phone if has no identifier.
$surveyEmailInvitationFields = $Proj->getSurveyEmailInvitationFields(true);
if (!$enable_participant_identifiers && $record != "" && isset($emailIdentArray[$record]) && $emailIdentArray[$record]['identifier'] == ""
    && (($email['this_delivery_type'] == 'EMAIL' && $survey_email_participant_field == '' && !isset($surveyEmailInvitationFields[$survey_id]) && $emailIdentArray[$record]['email'] != "")
        || ($email['this_delivery_type'] != 'EMAIL' && $survey_phone_participant_field == '' && $emailIdentArray[$record]['phone'] != ""))) {
    $toEmail = ($email['this_delivery_type'] == 'EMAIL') ? $lang['survey_499'] : $lang['survey_903'];
}

// Invitation message
if ($email['this_delivery_type'] == 'EMAIL') {
    if ($email['append_survey_link']) {
        $invitation_msg .= '<br /><br />
                                ' . $lang['survey_134'] . '<br />
                                <a target="_blank" style="text-decoration:underline;" href="' . APP_PATH_SURVEY_FULL . '?s=' . $email['hash'] . '">' .
            ($Proj->surveys[$email['survey_id']]['title'] == "" ? APP_PATH_SURVEY_FULL . '?s=' . $email['hash'] : $Proj->surveys[$email['survey_id']]['title']) . '</a><br /><br />
                                ' . $lang['survey_135'] . '<br />
                                ' . APP_PATH_SURVEY_FULL . '?s=' . $email['hash'] . '<br /><br />
                                ' . $lang['survey_137'];
    }
} elseif ($email['this_delivery_type'] == 'SMS_INVITE_MAKE_CALL') {
    if ($invitation_msg != '') $invitation_msg .= " -- ";
    $invitation_msg .= $lang['survey_863'] . " " . formatPhone($twilio_from_number);
    $invitation_msg = Messaging::cleanSmsText($invitation_msg);
} elseif ($email['this_delivery_type'] == 'SMS_INVITE_RECEIVE_CALL') {
    if ($invitation_msg != '') $invitation_msg .= " -- ";
    $invitation_msg .= $lang['survey_866'];
    $invitation_msg = Messaging::cleanSmsText($invitation_msg);
} elseif ($email['this_delivery_type'] == 'SMS_INVITE_WEB') {
    // Set survey link
    $this_survey_link = APP_PATH_SURVEY_FULL . '?s=' . $email['hash'];
    // Append the survey link?
    if ($email['append_survey_link']) {
        if ($invitation_msg != '') $invitation_msg .= " -- ";
        $invitation_msg .= $lang['survey_956'] . " " . APP_PATH_SURVEY_FULL . '?s=' . $email['hash'];
    }
    $invitation_msg = Messaging::cleanSmsText($invitation_msg);
} else {
    if ($invitation_msg != '') $invitation_msg .= " -- ";
    $invitation_msg .= " " . $lang['survey_865'];
    $invitation_msg = trim($invitation_msg);
}




// Set dialog content
$content = 	RCView::div(array('style'=>'padding:2px 7px;'),
				RCView::div(array('style'=>'margin-bottom:10px;padding-bottom:15px;border-bottom:1px solid #ddd;'),
                    $instructions
				) .
				RCView::table(array('cellspacing'=>'0','border'=>'0','style'=>'table-layout:fixed;width:100%;'),
					// Time sent
					RCView::tr('',
						RCView::td(array('style'=>'vertical-align:top;width:70px;color:#777;'),
							$lang['survey_395']
						) .
						RCView::td(array('style'=>'vertical-align:top;font-weight:bold;'),
							$time_sent
						)
					) .
					// From
					RCView::tr('',
						RCView::td(array('style'=>'vertical-align:top;width:70px;padding-top:11px;color:#777;'),
							$lang['global_37']
						) .
						RCView::td(array('style'=>'vertical-align:middle;padding-top:10px;font-weight:bold;'),
							$sendMethodText . $username_name . $fromEmailText
						)
					) .
					// To
					RCView::tr('',
						RCView::td(array('style'=>'vertical-align:middle;width:70px;padding-top:10px;color:#777;'),
							$lang['global_38']
						) .
						RCView::td(array('style'=>'vertical-align:middle;padding-top:10px;font-weight:bold;color:#800000;'),
							$toEmail
						)
					) .
					// Subject (for emails only)
					RCView::tr(array('style'=>($email['this_delivery_type'] == 'EMAIL' ? '' : 'display:none;')),
						RCView::td(array('style'=>'vertical-align:top;padding-top:10px;width:70px;color:#777;'),
							$lang['survey_103']
						) .
						RCView::td(array('style'=>'vertical-align:top;padding-top:10px;font-weight:bold;'),
							$subjectEmail
						)
					) .
					// Message
					($email['this_delivery_type'] == 'VOICE_INITIATE' ? '' :
						RCView::tr('',
							RCView::td(array('colspan'=>'2','style'=>'padding-top:15px;vertical-align:top;'),
								RCView::div(array('style'=>($email['this_delivery_type'] == 'EMAIL' ? 'height:200px;' : 'height:60px;').'overflow:auto;padding:10px;border:1px solid #ddd;background-color:#f5f5f5;'),
									$invitation_msg
								)
							)
						)
					)
				)
			);

// Return JSON
print '{"content":"'.js_escape2($content).'","title":"'.js_escape2($lang['survey_393']).'"}';