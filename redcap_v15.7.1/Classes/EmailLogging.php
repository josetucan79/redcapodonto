<?php

class EmailLogging
{
    private static $categories = ['','SURVEY_NOTIFICATION','SURVEY_CONFIRMATION','SURVEY_INVITE','SURVEY_INVITE_MANUAL','SURVEY_INVITE_ASI','ALERT','SYSTEM'];

    private static $uiStateOptInObject = 'email-logging';
	private static $uiStateOptInKey = 'opt-in';

    private function getSearchCategories()
	{
        global $lang;
        $cats = [];
		foreach (self::$categories as $cat) {
			if ($cat == 'SURVEY_INVITE') {
				$cats[$cat] = $lang['email_users_91'];
			} else if ($cat == 'SURVEY_INVITE_MANUAL') {
				$cats[$cat] = " - ".$lang['email_users_79'];
            } elseif ($cat == 'SURVEY_INVITE_ASI') {
				$cats[$cat] = " - ".$lang['email_users_78'];
            } elseif ($cat == 'SURVEY_NOTIFICATION') {
				$cats[$cat] = $lang['survey_548'];
            } elseif ($cat == 'SURVEY_CONFIRMATION') {
				$cats[$cat] = $lang['email_users_136'];
			} elseif ($cat == 'ALERT') {
				$cats[$cat] = $lang['global_154'];
			} elseif ($cat == '') {
				$cats[$cat] = $lang['email_users_81'];
            }
        }
        return $cats;
    }

    // Return boolean if user opted in to use the Email Logging page
	private function userHasOptedIn()
	{
        return (UIState::getUIStateValue(PROJECT_ID, self::$uiStateOptInObject, self::$uiStateOptInKey) == '1');
	}

	// Save and log the opt-in action for the current user to use the Email Logging page
	public function optInUser()
	{
        // Return error is user already opted in
        if ($this->userHasOptedIn()) return false;
		// Set UIstate
		UIState::saveUIStateValue(PROJECT_ID, self::$uiStateOptInObject, self::$uiStateOptInKey, '1');
        // Log it
		Logging::logEvent("", "redcap_user_information", "MANAGE", USERID, "username = " . USERID, "User opted in to access the Email Logging page");
        // Success
        return true;
	}

	// Re-send an individual email by its hash
	public function resendEmail($hash)
	{
		$emailAttr = Message::getEmailContentByHash($hash);
        $email = new Message($emailAttr['project_id'], $emailAttr['record'], $emailAttr['event_id'], $emailAttr['instrument'], $emailAttr['instance']);
        if ($emailAttr['type'] == 'SENDGRID_TEMPLATE') {
            $api_key = SendGridRC::getAPIKeyByPid($emailAttr['project_id']);
            $from_email = $emailAttr['sender'];
            $to = $emailAttr['recipients'];
            $dynamic_template_id = $emailAttr['email_subject'];
            $cc = $emailAttr['email_cc'];
            $bcc = $emailAttr['email_bcc'];
            $message_data = json_decode($emailAttr['message'], TRUE);
            if (array_key_exists('template_data', $message_data) && array_key_exists('mail_send_configuration', $message_data)) {
                // if mail send config and template data are packaged in the message attribute, use them
                $dynamic_template_data = $message_data['template_data'];
                $mail_send_configuration = $message_data['mail_send_configuration'];
            } else {
                // if not, treat the message attribute as dynamic data
                $dynamic_template_data = $message_data;
                $mail_send_configuration = null;
            }
            $success = SendGridRC::sendDynamicTemplateEmail($api_key, $from_email, $to, $dynamic_template_id, $emailAttr['project_id'], $emailAttr['record'], $emailAttr['event_id'], $emailAttr['instrument'], $emailAttr['instance'], $emailAttr['lang_id'], $emailAttr['category'], $cc, $bcc, null, $dynamic_template_data, $mail_send_configuration);
            if ($success) {
                print '1';
            } else {
                print '0';
            }
        } else {
            $message = ($emailAttr['message_html'] == '') ? $emailAttr['message'] : $emailAttr['message_html'];
            // Determine if we need to enforce protected email mode
            $Proj = new Project($emailAttr['project_id']);
            $enforceProtectedEmail = ($emailAttr['type'] == 'EMAIL' && $Proj->project['protected_email_mode'] && ($Proj->project['protected_email_mode_trigger'] == 'ALL'
                                     || ($Proj->project['protected_email_mode_trigger'] == 'PIPING' && containsIdentifierFields($message, $Proj->project_id))));
            // Set email attributes
            $email->setTo($emailAttr['recipients']);
            $email->setCc($emailAttr['email_cc']);
            $email->setBcc($emailAttr['email_bcc']);
            $email->setFrom($emailAttr['sender']);
            $email->setSubject($emailAttr['email_subject']);
            $email->setBody($message);
            if ($email->send(false, false, $enforceProtectedEmail, $emailAttr['category'])) {
                print '1';
            } else {
                print '0';
            }
        }
	}

	// Render the main search page content
	public function renderPage()
	{
		extract($GLOBALS);
		renderPageTitle("<i class=\"fas fa-mail-bulk\"></i> " . ($Proj->project['twilio_enabled'] ? $lang['email_users_96'] : $lang['email_users_53']));
        loadJS('EmailLogging.js');
        // Display fully functional search page
        if ($this->userHasOptedIn())
        {
            $recordDropDown = Records::renderRecordListAutocompleteDropdown(PROJECT_ID, true, 5000, 'search-record',
                                "x-form-text x-form-field fs14 ms-2", "", "", $lang['esignature_14'], $lang['alerts_205']);
			$categoriesDropDown = RCView::select(['id'=>'search-category', 'class'=>'x-form-text x-form-field fs14 ms-2'], $this->getSearchCategories(), "");
            // Display special message if email logging was enabled in the system (via upgrade to v11.4.0+) after the project was created
            $emailLogginBeginTimeMsg = "";
            if ($GLOBALS['email_logging_install_time'] != '' && $GLOBALS['email_logging_install_time'] > $GLOBALS['creation_time']) {
				$emailLogginBeginTimeMsg = " ".RCView::span(['style'=>'color:#C00000;'],
                        $lang['email_users_90']. " " .DateTimeRC::format_user_datetime($GLOBALS['email_logging_install_time'], 'Y-M-D_24'). $lang['period']
                );
            }
			?>
            <div style="max-width:940px;">
                <p class="my-3" style="max-width:100%;"><?=$lang['email_users_135'].$emailLogginBeginTimeMsg?></p>
                <div style="padding:10px 15px;border:1px solid #d0d0d0;background-color:#f5f5f5;" class="fs14">
                    <div class="boldish"><?=($Proj->project['twilio_enabled'] ? $lang['email_users_98'] : $lang['email_users_55'])?></div>
                    <div class="mt-4 mb-1">
                        <span class="me-1"><?=$lang['email_users_73']?></span>
                        <input type="text" id="search-term" class="x-form-text x-form-field fs14" style="width:350px;" placeholder="<?=js_escape2($lang['system_config_115'])?>">
                        <span class="mx-1"><?=$lang['global_107']?></span>
                        <select id="search-target" class="x-form-text x-form-field fs14" style="max-width:400px;">
                            <option value="all" selected><?=$lang['email_users_57']?></option>
                            <option value="subject_body"><?=$lang['email_users_58']?></option>
                            <option value="subject"><?=$lang['email_users_59']?></option>
                            <option value="body"><?=$lang['email_users_60']?></option>
                            <option value="sender_recipient"><?=$lang['email_users_61']?></option>
                            <option value="sender"><?=$lang['email_users_62']?></option>
                            <option value="recipient"><?=$lang['email_users_63']?></option>
                        </select>
                    </div>
                    <div class="mt-4 mb-1"><?=$lang['api_30']?><?=$categoriesDropDown?></div>
                    <div class="mt-4 mb-1"><?=$lang['email_users_74']?><?=$recordDropDown?></div>
                    <div class="mt-4 mb-1"><?=$lang['email_users_69']?></div>
                    <div>
                        <input id='beginTime' autocomplete='off' type='text' style='width:130px;' class="x-form-text x-form-field fs14" onblur="redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);">
                        <span style='margin:0 5px 0 7px;'><?=$lang['data_access_groups_ajax_14']?></span>
                        <input id='endTime' autocomplete='off' type='text' style='width:130px;' class="x-form-text x-form-field fs14" onblur="redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);">
                    </div>
                    <div class="mt-4 mb-2">
                        <button id='search-btn' type="button" class="btn btn-primaryrc btn-sm fs14"><?=($Proj->project['twilio_enabled'] ? $lang['email_users_97'] : $lang['email_users_56'])?></button>
                    </div>
                </div>
                <div id="search-results" class="mt-4"></div>
            </div>
            <div id="resend-email-dialog" class="simpleDialog"><?=$lang['email_users_93']?></div>
			<?php
        }
        // Display opt-in prompt before allowing user to see the page
        else
        {
            print RCView::p(['class'=>'my-4 fs15'], $lang['email_users_85']);
            print RCView::div(['id'=>'optin-dialog', 'class'=>'simpleDialog', 'title'=>$lang['email_users_86']],
                    RCView::div([], $lang['email_users_87']) .
                    RCView::div(['class'=>'mt-3'], $lang['email_users_89'])
            );
		}
        // Language for JS
		addLangToJS(['global_53', 'bottom_90','email_users_92','email_users_88', 'email_users_94', 'email_users_95']);
	}

	// Perform the search and return the search results
	public function search($term, $target, $record, $beginTime, $endTime, $category)
	{
        global $lang;
        // Sanitize search term passed in query string
		$term = trim(strtolower(html_entity_decode($term, ENT_QUOTES)));
        // Remove any commas or parentheses to allow for better searching
		$term = str_replace([",", ")", "("], ["", "", ""], $term);

        // If search term contains a space, then assume multiple search terms that will be searched for independently
		if (strpos($term, " ") !== false) {
			$search_terms = explode(" ", $term);
		} else {
			$search_terms = array($term);
		}
		$search_terms = array_unique($search_terms);

        // Set the subquery for all search terms used
		$subsqla = array();
		foreach ($search_terms as $key=>$this_term) {
			// Trim and set to lower case
			$search_terms[$key] = $this_term = trim(strtolower($this_term));
            // Set subquery items
			if ($this_term == '' || $this_term == 'or' || $this_term == 'and' || $this_term == 'a') {
				unset($search_terms[$key]);
			} else {
                // ["all", "sender", "sender_recipient", "recipient", "subject", "subject_body", "body"]
                if (in_array($target, ["all", "sender", "sender_recipient"])) {
				    $subsqla[] = "sender like '%".db_escape($this_term)."%'";
                }
				if (in_array($target, ["all", "recipient", "sender_recipient"])) {
                    $subsqla[] = "recipients like '%".db_escape($this_term)."%'";
					$subsqla[] = "email_cc like '%".db_escape($this_term)."%'";
					$subsqla[] = "email_bcc like '%".db_escape($this_term)."%'";
                }
				if (in_array($target, ["all", "subject", "subject_body"])) {
					$subsqla[] = "email_subject like '%".db_escape($this_term)."%'";
				}
				if (in_array($target, ["all", "body", "subject_body"])) {
					$subsqla[] = "message like '%".db_escape($this_term)."%'";
				}
			}
		}
        // Pull together all ORs for subquery
		$subsql = empty($subsqla) ? "" : implode(" or ", $subsqla);
		if ($subsql != "") $subsql = "and ($subsql)";
        // Calculate score on how well the search terms matched
		$score = [];
		$row_data = [];
        $scoreItems = ['sender', 'recipients', 'email_cc', 'email_bcc', 'email_subject', 'message'];
        $sql = "select * from redcap_outgoing_email_sms_log where project_id = ".PROJECT_ID." $subsql";
        if ($beginTime != '') {
            $sql .= " and time_sent >= '".db_escape($beginTime)."'";
		}
		if ($endTime != '') {
			$sql .= " and time_sent <= '".db_escape($endTime)."'";
		}
        if ($record != '') {
			$sql .= " and record = '".db_escape($record)."'";
		}
        if ($category == 'SURVEY_INVITE') {
            $sql .= " and category in ('SURVEY_INVITE_MANUAL', 'SURVEY_INVITE_ASI')";
        } else if ($category != '') {
            $sql .= " and category = '" . db_escape($category) . "'";
        }
        $sql .= " order by email_id desc";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q))
        {
			$key = $row['email_id'];
			// Create the display/preview label
			$display = $row['sender'];
			$display .= "\n" . $row['recipients'];
			if ($row['email_cc'] != '') $display .= "; " . $row['email_cc'];
			if ($row['email_bcc'] != '') $display .= "; " . $row['email_bcc'];
			$display .= "\n" . $row['email_subject'];
			$display .= "\n" . $row['message'];
			// Calculate search match score.
			$score[$key] = 0;
            if ($term != '') {
                // Loop through each search term for this person
                foreach ($search_terms as $this_term) {
                    // Set length of this search string
                    $this_term_len = strlen($this_term);
                    // For partial matches, give +1 point for each letter
                    foreach ($scoreItems as $scoreItem) {
                        if (stripos($row[$scoreItem], $this_term) !== false) {
                            $score[$key] += $this_term_len;
                        }
                    }
                    // Wrap any occurrence of search term in label with bold tags
                    $display = str_ireplace($this_term, RCView::b($this_term), $display);
                }
                // If match EXACTLY, do a +100 on score.
                foreach ($scoreItems as $scoreItem) {
                    // If whole search string matches exactly with from, to, subject, or message
                    if ($term == $row[$scoreItem]) {
                        $score[$key] += 300;
                    }
                    // If just ONE of the search terms matches exactly with from, to, subject, or message
                    foreach ($search_terms as $this_term) {
                        if ($this_term == $row[$scoreItem]) {
                            $score[$key] += 100;
                        }
                    }
                }
            }
            // Add labels to $display
			$displayArray = explode("\n", $display, 4);
            $thisSubject = filter_tags($displayArray[2]);
            $thisMessage = str_replace(["\r\n", "\r", "\n"], [" ", " ", " "], $displayArray[3]);
            // Add to display array
			$row_data[$key] = [
                                RCView::a(array('href'=>'javascript:;', 'onclick'=>"viewEmailByHash('{$row['hash']}');"),
                                    RCView::i(array('class'=>'far fa-envelope-open fs16', 'title'=>$lang['email_users_64']))
                                ),
                                RCView::span(['class'=>'hidden'], $row['time_sent']).DateTimeRC::format_user_datetime($row['time_sent'], 'Y-M-D_24'),
				                RCView::span(['class'=>'wrap'], $row['record']),
                                $lang['global_37']." ".$displayArray[0].", ".$lang['global_38']." ".$displayArray[1] . "<br>". ($row['type'] == "EMAIL" ? $lang['control_center_28']." $thisSubject<br>" : "") . ($row['type'] == "SENDGRID_TEMPLATE" ? $lang['alerts_334']." $thisSubject<br>" : "$thisMessage")
                              ];
		}

        // Sort users by score, then by username
		$limit = 50;
		$count_emails = count($row_data);
		$title = $count_emails." ".$lang['email_users_68'];
		if ($count_emails > 0) {
			// Sort
			if ($term != '') array_multisort($score, SORT_NUMERIC, SORT_DESC, $row_data);
			// Limit only to X emails to return
			if ($count_emails > $limit) {
				$row_data = array_slice($row_data, 0, $limit);
				$title = $lang['email_users_67']." $limit ".$lang['email_users_68'];
			}
		}
        
		// Display note if table is empty
		if (empty($row_data)) {
			$row_data[] = array('', RCView::div(array('style'=>'margin:10px 0;color:#888;'), $lang['email_users_65']), '');
		}
		// Set table headers and attributes
		$headers = array(
			array(30, RCView::span(['class'=>'wrap'], $lang['email_users_72']), 'center'),
			array(120, $lang['email_users_66'], 'center'),
			array(75, $lang['global_49'], 'center'),
			array(665, $lang['email_users_70'].RCView::span(['class'=>'text-secondary fs11 ms-3'], $lang['email_users_71']))
		);
		// Title
		$title = RCView::div(array('style'=>'color:#800000;font-size:16px;padding:5px;'), $title);
		// Build table html
		print renderGrid("email_search_table", $title, 'auto', 'auto', $headers, $row_data, true, true, false);
	}

	// View an individual email
	public function view($hash)
	{
        $emailAttr = Message::getEmailContentByHash($hash);
		$email = new Message($emailAttr['project_id'], $emailAttr['record'], $emailAttr['event_id'], $emailAttr['instrument'], $emailAttr['instance']);
		$email->renderProtectedEmailPage($emailAttr, true);
	}

}