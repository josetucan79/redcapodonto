<?php

class SurveyController extends Controller
{
	// Change a participant's Link Expiration time (time limit)
	public function changeLinkExpiration()
	{
		if ($_POST['action'] == 'save') {
			Survey::changeLinkExpiration();
		} else {
			Survey::changeLinkExpirationRenderDialog();
		}
	}
	
	// Render the HTML table for a record's scheduled survey invitations to be sent in the next X days
	public function renderUpcomingScheduledInvites()
	{
		global $lang;
		$SurveyScheduler = new SurveyScheduler();
		print RCView::div(array('style'=>'margin-bottom:10px;'), $lang['survey_1134'] . " ". RCView::b((int)$_POST['days'] . " " . $lang['scheduling_25']) . $lang['period']);
		print $SurveyScheduler->renderSurveyInvitationLog(rawurldecode(urldecode($_POST['record'])), false, $_POST['days']);
	}

    // Enable/disable Google reCaptcha
    public function enableCaptcha()
    {
        $enable = (isset($_POST['enable']) && (int)$_POST['enable'] === 1) ? '1' : '0';
        print Survey::enableCaptcha($enable) ? '1' : '0';
    }

	// Re-evaluate ASIs
	public function reevalAutoInvites()
	{
		global $lang;
		$surveyScheduler = new SurveyScheduler(PROJECT_ID);
		if ($_GET['action'] == 'save') {
            $is_dry_run = $_POST['is_dry_run'] === '1';
			$surveysEvents = array();
			foreach (explode(",", $_POST['surveysEvents']) as $this_se) {
				list ($survey_id, $event_id) = explode("-", $this_se, 2);
				if (!isinteger($survey_id) || !isinteger($event_id)) continue;
				$surveysEvents[$survey_id][$event_id] = true;
			}
            $numInvitationsScheduled = 0;
            $numInvitationsDeleted = 0;
            $numRecordsAffected = 0;
            if ($is_dry_run) {
                db_query("SET AUTOCOMMIT=0");
                db_query("BEGIN");
                try {
                    $recordsAffected = $surveyScheduler->triggerASIs(PROJECT_ID, $surveysEvents, $is_dry_run);
                } catch (\Exception $e) {
                    // safety catch block to prevent exit without explicit rollback
                }
                db_query("ROLLBACK");
                db_query("SET AUTOCOMMIT=1");
                // now throw exception that would have been thrown
                if (isset($e)) {
                    throw $e;
                }
            } else {
                $recordsAffected = $surveyScheduler->triggerASIs(PROJECT_ID, $surveysEvents, $is_dry_run);
            }
            $csvArray = [];
            $key = 0;
            foreach ($recordsAffected as $record => $recordData) {
                if (isset($recordsAffected[$record]['sent_or_scheduled']) || isset($recordsAffected[$record]['removed'])) {
                    $numRecordsAffected += 1;
                    if (isset($recordsAffected[$record]['sent_or_scheduled'])) {
                        $numInvitationsScheduled += 1;
                        $csvArray[$key++]['sent_or_scheduled'] = $record;
                    } else {
                        $numInvitationsDeleted += 1;
                        $csvArray[$key++]['unscheduled'] = $record;
                    }
                }
            }
            $attr = $is_dry_run ? array('style' => 'color:#9c2626b3') : array('class'=>'text-success');
			if ($numRecordsAffected > 0) {
                $msg = RCView::div($attr,
                            (!$is_dry_run ? RCView::b('<i class="fas fa-check"></i> '.$lang['global_79']) : RCView::b('<i class="fa-solid fa-circle-info"></i> '.$lang['alerts_410'])) . "<br>$numInvitationsScheduled " . $lang['asi_034'] .
							" $numInvitationsDeleted " . $lang['asi_035'] . RCView::b(" $numRecordsAffected " . $lang['data_entry_173']) . $lang['period']
						);
				$msglog = "$numInvitationsScheduled " . $lang['asi_034'] . " $numInvitationsDeleted " . $lang['asi_035'] . " $numRecordsAffected " . $lang['data_entry_173'] . $lang['period'];
			} else {
				$msg = RCView::div($attr,
							'<i class="fas fa-check"></i> '.$lang['asi_033']
						);
				$msglog = $lang['alerts_257'];
			}
            // Add CSV download of affected record names
            if ($numRecordsAffected > 0)
            {
                // Store CSV file of record names in edocs with 60 minute expiration (we don't want to keep in temp)
                $csvFilename = APP_PATH_TEMP . date('YmdHis') . "_pid_".PROJECT_ID."_asi_reeval_" . substr(sha1(rand()), 0, 6) . ".csv";
                file_put_contents($csvFilename, arrayToCsv($csvArray, true, User::getCsvDelimiter()));
                $doc_id = REDCap::storeFile($csvFilename, PROJECT_ID);
                unlink($csvFilename);
                // Set file to auto-delete in 60 minutes
                $sql = "update redcap_edocs_metadata set delete_date = ? where doc_id = ?";
                db_query($sql, [date("YmdHis", mktime(date("H")+1,date("i"),date("s"),date("m"),date("d"),date("Y"))), $doc_id]);
                // Create download link
                $msg .= RCView::button(['class'=>'btn btn-defaultrc btn-xs fs14 mt-3', 'onclick'=>"window.location.href='".Files::getDownloadLink($doc_id, PROJECT_ID)."';"],
                    RCView::img(['src'=>'xls.gif', 'style'=>'position:relative;top:3px;vertical-align:initial;']) . " " . RCView::tt('alerts_409')
                );
            }
            // Logging 
            elseif (!$is_dry_run) {
                Logging::logEvent("", "redcap_surveys_scheduler_queue", "MANAGE", PROJECT_ID, "Re-evaluate automated survey invitations:\n" . strip_tags($msglog), "Re-evaluate automated survey invitations");
            }
            // Output message
            print RCView::h1([], $lang['alerts_404']). $msg;
		} elseif ($_GET['action'] == 'view') {
			print $surveyScheduler->displayAutoInviteSurveyEventCheckboxList(PROJECT_ID);
		} else {
			print '0';
		}
	}
}