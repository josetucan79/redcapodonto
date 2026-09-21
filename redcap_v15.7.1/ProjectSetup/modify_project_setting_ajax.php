<?php

use Vanderbilt\REDCap\Classes\MyCap\Participant;
use Vanderbilt\REDCap\Classes\Rewards\Utility\RewardsProjectService;
use Vanderbilt\REDCap\Classes\Rewards\Utility\ActivationApprovalManager as RewardsActivationApprovalManager;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Default response
$response = "0";

## DISPLAY DIALOG PROMPT
if (isset($_POST['setting']) && isset($_POST['action']) && $_POST['action'] == 'view')
{
	## Display different messages for different settings

	// Survey participant email address field
	if ($_POST['setting'] == 'survey_email_participant_field')
	{
		// Collect all email-validated fields and their labels into an array
		$emailFieldsLabels = Form::getFieldDropdownOptions(false, false, false, false, 'email');
		// Set dialog content
		$response = RCView::div(array(),
						RCView::div(array('style'=>'margin-bottom:15px;background-color:#f5f5f5;border:1px solid #ddd;padding:10px;'),
							RCView::b($lang['setup_116']) . RCView::br() .
							RCView::select(array('style'=>'margin-top:5px;max-width:400px;min-width:220px;','id'=>'surveyPartEmailFieldName', 'onchange'=>"fieldUsedInMultiplePlaces($(this));"), $emailFieldsLabels, '', 300)
						) .
						$lang['setup_192'] . "<br><br>" . $lang['setup_122'] . RCView::br() . RCView::br() .
						RCView::span(array('style'=>'color:#C00000;'), $lang['setup_133']) . RCView::br() . RCView::br() .
						RCView::b($lang['global_02'].$lang['colon']) . " " . $lang['setup_115'] . " " . $lang['setup_168'] . RCView::br() . RCView::br() .
						RCView::b($lang['setup_165'].$lang['colon']). " " . $lang['setup_171'] . " " . $lang['setup_169'] . " " . $lang['setup_172']
					);
	}

	// Twilio voice/SMS services
	if ($_POST['setting'] == 'twilio_enabled')
	{
        $sendTwilioRequestTodoList = ((UserRights::isSuperUserNotImpersonator() || $twilio_enabled_by_super_users_only == '0') ? '0' : '1');
        // Do not need approval if already enabled and clicking on "Modify" button
        if ($twilio_enabled && $twilio_account_sid != '')   $sendTwilioRequestTodoList = 0;
		// Set dialog content
		$response = RCView::div(array(),
						// Instructions
						RCView::div(array('style'=>'margin:0 0 10px;line-height:14px;'),
							RCView::div(array('style'=>''),
								$lang['survey_1276'] . " " .
								RCView::a(array('href'=>'javascript:;', 'style'=>'margin:5px 0;text-decoration:underline;', 'onclick'=>"
									$(this).hide();
									$('#twilio_setup_instr1').show('fade',function(){
										fitDialog($('#TwilioEnableDialog'));
										$('#TwilioEnableDialog').dialog('option', 'position', { my: 'center', at: 'center', of: window });
									});
								"),
									$lang['global_58']
								)
							) .
							RCView::div(array('id'=>'twilio_setup_instr1', 'style'=>'display:none;'),
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_1599']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_1282']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_970']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_973'] . " " . $lang['survey_1534']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_972']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_975']) .
								RCView::div(array('style'=>'margin:10px 0;'), $lang['survey_967'])
							) .
							RCView::a(array('href'=>'javascript:;', 'style'=>'margin:5px 0;display:block;text-decoration:underline;', 'onclick'=>"
								$(this).hide();
								$('#twilio_setup_instr2').show('fade',function(){
									fitDialog($('#TwilioEnableDialog'));
									$('#TwilioEnableDialog').dialog('option', 'position', { my: 'center', at: 'center', of: window });
								});
							"),
								$lang['survey_846']
							)
						) .
						RCView::div(array('id'=>'twilio_setup_instr2', 'style'=>'display:none;margin:15px 0 15px;line-height:14px;'),
							RCView::div(array('style'=>'color:#C00000;font-weight:bold;margin-bottom:2px;'), $lang['survey_976']) .
							$lang['survey_1281'] . " " .
							RCView::a(array('href'=>'https://www.twilio.com', 'target'=>'_blank', 'style'=>'font-size:13px;text-decoration:underline;'),
								"www.twilio.com"
							) . $lang['period'] . " " .
							$lang['survey_835']
						) .
						RCView::form(array('id'=>'twilio_setup_form'),
							// Table
							RCView::table(array('cellspacing'=>0, 'class'=>'form_border', 'style'=>'width:100%;'),
								// Enabled?
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc nowrap '.($twilio_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;'),
										RCView::img(array('src'=>'twilio.png')) .
										RCView::b($lang['survey_711']) . RCView::SP . RCView::SP
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data '.($twilio_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_enabled',
											'onchange'=>"enableTwilioRowColor($sendTwilioRequestTodoList);", 'class'=>'x-form-text x-form-field', 'style'=>''),
											array(0=>$lang['global_23'], 1=>$lang['index_30']), $twilio_enabled, 200)
									)
								) .
								// Header
								RCView::tr(array(),
									RCView::td(array('colspan'=>'2', 'class'=>'header', 'style'=>'padding:6px 10px;'),
										$lang['survey_714']
									)
								) .
								// Account SID
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_715'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::input(array('name'=>'twilio_account_sid', 'type'=>'text',
											'class'=>'x-form-text x-form-field', 'style'=>'width:260px;', 'value'=>$twilio_account_sid))
									)
								) .
								// Account Token
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_716'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data nowrap', 'style'=>'padding:6px 10px;'),
										RCView::input(array('type'=>'password', 'name'=>'twilio_auth_token',
											'class'=>'x-form-text x-form-field', 'style'=>'width:170px;', 'value'=>$twilio_auth_token)) .
										RCView::a(array('href'=>'javascript:;', 'class'=>'cclink password-mask-reveal', 'style'=>'text-decoration:underline;font-size:7pt;margin-left:5px;', 'onclick'=>"$(this).remove();showPasswordField('twilio_auth_token');"),
											$lang['survey_720']
										)
									)
								) .
								// "From" Number
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_718'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::text(array('name'=>'twilio_from_number',
											'class'=>'x-form-text x-form-field', 'style'=>'width:120px;',
											'value'=>$twilio_from_number, 'onblur'=>"this.value = this.value.replace(/\D/g,''); redcap_validate(this,'','','soft_typed','integer',1)"))

									)
								) .
								// Alphanumeric Sender ID (optional)
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_1595']) .
                                        RCView::div(['class'=>'mt-1 fs12 font-weight-normal', 'style'=>'line-height:1.1;'],
                                            $lang['survey_1596'] . " " .
                                            RCView::a(['class'=>'fs12 font-weight-normal', 'href'=>'https://www.twilio.com/docs/glossary/what-alphanumeric-sender-id', 'target'=>'_blank'], $lang['alerts_32'])
                                        )
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::text(array('name'=>'twilio_alphanum_sender_id', 'class'=>'x-form-text x-form-field', 'style'=>'width:260px;', 'value'=>$twilio_alphanum_sender_id))
									)
								) .
								// Display note about manually disabling Twilio's Request Inspector
								RCView::tr(array(),
									RCView::td(array('colspan'=>2, 'valign'=>'top', 'class'=>'data', 'style'=>'padding:20px 15px;color:#A00000;'),
										'<i class="fas fa-exclamation-triangle"></i> '.$lang['survey_1269']
									)
								) .
								// FIELD EMBEDDING: Add note that it is not supported in the REDCap Mobile App
								(!Piping::projectHasEmbeddedVariables($project_id) ? '' :
									RCView::tr(array(),
										RCView::td(array('colspan'=>2, 'valign'=>'top', 'class'=>'data yellow p-3'),
											'<span class="fas fa-info-circle" style="text-indent:0;font-size:13px;" aria-hidden="true"></span> ' .
											$lang['design_820']
										)
									)
								)
							)
						)
					);
	}

	// Mosio SMS services
	if ($_POST['setting'] == 'mosio_enabled')
	{
        $sendMosioRequestTodoList = ((UserRights::isSuperUserNotImpersonator() || $mosio_enabled_by_super_users_only == '0') ? '0' : '1');
     	// Set dialog content
		$response = RCView::div(array(),
						// Instructions
						RCView::div(array('style'=>'margin:0 0 20px;line-height:1.2;'),
							RCView::div(array('style'=>'margin-bottom:10px;'),
								$lang['survey_1556']
							) .
                            RCView::div(array('style'=>'margin-bottom:20px;'),
                                RCView::a(array('href'=>'javascript:;', 'style'=>'margin:5px 0;text-decoration:underline;', 'onclick'=>"
									$(this).hide();
									$('#mosio_setup_instr1').show('fade',function(){
										fitDialog($('#MosioEnableDialog'));
										$('#MosioEnableDialog').dialog('option', 'position', { my: 'center', at: 'center', of: window });
									});
								"),
                                    $lang['survey_1557']
                                )
                            ) .
                            RCView::div(array('id'=>'mosio_setup_instr1', 'style'=>'display:none;'),
                                RCView::div(array('style'=>'margin-top:20px;'), $lang['survey_1558'])
							)
						) .
						RCView::form(array('id'=>'mosio_setup_form'),
							// Table
							RCView::table(array('cellspacing'=>0, 'class'=>'form_border', 'style'=>'width:100%;'),
								// Enabled?
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc nowrap '.($twilio_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;'),
										RCView::img(array('style'=>'height:16px;width:16px;', 'src'=>'mosio.png')) .
										RCView::b($lang['dashboard_130']) . RCView::SP . RCView::SP
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data '.($twilio_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_enabled',
											'onchange'=>"enableMosioRowColor($sendMosioRequestTodoList);", 'class'=>'x-form-text x-form-field', 'style'=>''),
											array(0=>$lang['global_23'], 1=>$lang['index_30']), $twilio_enabled, 200)
									)
								) .
								// Header
								RCView::tr(array(),
									RCView::td(array('colspan'=>'2', 'class'=>'header', 'style'=>'padding:6px 10px;'),
										$lang['survey_1527']
									)
								) .
								// API KEY
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_1528'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::input(array('name'=>'mosio_api_key', 'type'=>'text',
											'class'=>'x-form-text x-form-field', 'style'=>'width:260px;', 'value'=>$mosio_api_key))
									)
								) .
								// FIELD EMBEDDING: Add note that it is not supported in the REDCap Mobile App
								(!Piping::projectHasEmbeddedVariables($project_id) ? '' :
									RCView::tr(array(),
										RCView::td(array('colspan'=>2, 'valign'=>'top', 'class'=>'data yellow p-3'),
											'<span class="fas fa-info-circle" style="text-indent:0;font-size:13px;" aria-hidden="true"></span> ' .
											$lang['design_820']
										)
									)
								)
							)
						)
					);
	}

	// Twilio voice/SMS services: Set up project-level settings for Twilio (at this point it is already enabled)
	elseif ($_POST['setting'] == 'twilio_setup' && $user_rights['design'])
	{
		// Set checkbox settings
        $sql = "select 1 from redcap_projects where project_id != $project_id and mosio_api_key = ".checkNull($mosio_api_key)." limit 1";
        $twilio_option_sms_initiate_disabled = db_num_rows(db_query($sql)) ? "disabled" : "";
        $twilio_option_sms_initiate_chk = ($twilio_option_sms_initiate && $twilio_option_sms_initiate_disabled != "disabled") ? "checked" : "";
        $twilio_option_voice_initiate_chk = ($twilio_option_voice_initiate) ? "checked" : "";
		$twilio_option_sms_invite_make_call_chk = ($twilio_option_sms_invite_make_call) ? "checked" : "";
		$twilio_option_sms_invite_receive_call_chk = ($twilio_option_sms_invite_receive_call) ? "checked" : "";
		$twilio_option_sms_invite_web_chk = ($twilio_option_sms_invite_web) ? "checked" : "";
		$twilio_multiple_sms_behavior_disabled_chk = (count($Proj->surveys) > 1 || ($longitudinal && count($Proj->surveys) >= 1)) ? "" : "disabled";
        $twilio_alphanum_sender_id_disabled_chk = ($twilio_alphanum_sender_id == '') ? "" : "disabled";
        $twilio_alphanum_sender_id_disabled_chk = "";
        if ($Proj->messaging_provider == Messaging::PROVIDER_TWILIO && $twilio_alphanum_sender_id != '') {
            // Disable all voice call options and incoming sms option if using Twilio Alphanumeric Sender ID
            $twilio_alphanum_sender_id_disabled_chk = "disabled";
            $twilio_option_sms_initiate_chk = $twilio_option_voice_initiate_chk = $twilio_option_sms_invite_make_call_chk = $twilio_option_sms_invite_receive_call_chk = "";
        }
        // Only allow voice-related stuff for Twilio
        if ($Proj->messaging_provider != Messaging::PROVIDER_TWILIO) {
            $twilio_option_voice_initiate_chk = "disabled";
            $twilio_option_sms_invite_make_call_chk = "disabled";
            $twilio_option_sms_invite_receive_call_chk = "disabled";
        }
		// Gather all phone validation types + integer validation
		$valTypes = getValTypes();
		$valTypesPhoneInteger = array('int');
		foreach ($valTypes as $valName=>$valType) {
			if ($valType['data_type'] == 'phone') {
				$valTypesPhoneInteger[] = $valName;
			}
		}
		$phoneFieldsLabels = Form::getFieldDropdownOptions(true, false, false, false, $valTypesPhoneInteger, true, true, false, null, '--- '.$lang['survey_1316'].' ---');
		$mcFieldsLabels = Form::getFieldDropdownOptions(true, true, false, false, null, true, true, false, null, '--- '.$lang['survey_1317'].' ---');
		$invitePrefChoices = Survey::getDeliveryMethods(false, false, null, true, $project_id, true);
		$invitePrefChoicesText = [];
		foreach ($invitePrefChoices as $key=>$val) {
			$invitePrefChoicesText[] = "<b>$key</b>, $val";
		}
		$invitePrefChoicesText = implode("<br>", $invitePrefChoicesText);
		// Set dialog content
		$response = RCView::div(array(),
						// Instructions
						RCView::div(array('style'=>'margin:0 0 10px;line-height:14px;'),
							$lang['survey_1537']
						) .
						RCView::div(array('style'=>'margin:5px 0 15px;line-height:14px;color:#A00000;'),
							'<i class="far fa-lightbulb"></i> '.$lang['survey_1335']
						) .
						RCView::form(array('id'=>'twilio_setup_form'),
							// Table
							RCView::table(array('cellspacing'=>0, 'class'=>'form_border', 'style'=>'width:100%;'),
								// Header (options)
								RCView::tr(array(),
									RCView::td(array('colspan'=>'2', 'class'=>'header', 'style'=>'padding:6px 10px;'),
										'<i class="fa-solid fa-gear"></i> ' . $lang['survey_1535']
									)
								) .
								// Gender of voice
								RCView::tr(array('style'=>($Proj->messaging_provider == Messaging::PROVIDER_TWILIO ? "" : "display:none;")),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_722']) .
										RCView::div(array('class'=>'cc_info', 'style'=>'line-height:12px;'),
											$lang['survey_1275']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_voice_language', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:400px;'),
											TwilioRC::getDropdownAllLanguages(), $twilio_voice_language)
									)
								) .
								// Modules utilizing the service (i.e. surveys and/or alerts)
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_1538'])
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_modules_enabled', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:400px;'),
											array('SURVEYS'=>$lang['survey_1277'], 'ALERTS'=>$lang['survey_1278'], 'SURVEYS_ALERTS'=>$lang['survey_1279']), $twilio_modules_enabled, 100)
									)
								) .
								// Survey settings
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'colspan'=>'2', 'class'=>'labelrc fs14', 'style'=>'padding:20px 10px 5px;color:#C00000;background-color:#ddd;'),
										$lang['survey_1283']
									)
								) .
								// Delivery options to enable
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_836']) .
										RCView::div(array('class'=>'cc_info'),
											$lang['survey_837']
										) .
										RCView::div(array('style'=>'font-weight:normal;margin-top:15px;font-size:11px;color:#800000;text-indent:-0.7em;margin-left:0.7em;line-height:11px;'),
											"* ".$lang['survey_1534']
										)
									) .
									RCView::td(array('id'=>'twilio_options_checkboxes', 'valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
                                        // Survey as webpage
										RCView::div(array('style'=>'font-weight:bold;'),
											$lang['survey_804']
										) .
										RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'),
											RCView::checkbox(array('name'=>'twilio_option_sms_invite_web', $twilio_option_sms_invite_web_chk=>$twilio_option_sms_invite_web_chk)) . $lang['survey_957']
										) .
                                        // Survey as voice call (Twilio only)
                                        ($Proj->messaging_provider != Messaging::PROVIDER_TWILIO ? "" :
                                            RCView::div(array('style'=>'margin-top:8px;'),
                                                RCView::b($lang['survey_802']) . ($twilio_alphanum_sender_id == '' ? '' : RCView::span(['class'=>'ml-2 text-dangerrc'], $lang['survey_1597']))
                                            ) .
                                            RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'),
                                                RCView::checkbox(array('name'=>'twilio_option_voice_initiate', $twilio_alphanum_sender_id_disabled_chk=>$twilio_alphanum_sender_id_disabled_chk, $twilio_option_voice_initiate_chk=>$twilio_option_voice_initiate_chk)) . $lang['survey_728']
                                            ) .
                                            RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'),
                                                RCView::checkbox(array('name'=>'twilio_option_sms_invite_make_call', $twilio_alphanum_sender_id_disabled_chk=>$twilio_alphanum_sender_id_disabled_chk, $twilio_option_sms_invite_make_call_chk=>$twilio_option_sms_invite_make_call_chk)) . $lang['survey_799']
                                            ) .
                                            RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'),
                                                RCView::checkbox(array('name'=>'twilio_option_sms_invite_receive_call', $twilio_alphanum_sender_id_disabled_chk=>$twilio_alphanum_sender_id_disabled_chk, $twilio_option_sms_invite_receive_call_chk=>$twilio_option_sms_invite_receive_call_chk)) . $lang['survey_800']
                                            )
                                        ) .
                                        // Survey as SMS conversation
										RCView::div(array('style'=>'margin-top:8px;'),
                                            RCView::b($lang['survey_803']) . ($twilio_alphanum_sender_id == '' ? '' : RCView::span(['class'=>'ml-2 text-dangerrc'], $lang['survey_1597']))
										) .
										RCView::div(array('style'=>'text-indent:-1.8em;margin-left:1.8em;'),
											RCView::checkbox(array('name'=>'twilio_option_sms_initiate', $twilio_alphanum_sender_id_disabled_chk=>$twilio_alphanum_sender_id_disabled_chk, $twilio_option_sms_initiate_disabled=>$twilio_option_sms_initiate_disabled, $twilio_option_sms_initiate_chk=>$twilio_option_sms_initiate_chk, 'onclick'=>"
												if (!super_user && $('input[type=\"checkbox\"][name=\"twilio_option_sms_initiate\"]').prop('checked') && '$twilio_option_sms_initiate' == '0') {
													simpleDialog('".js_escape($lang['survey_1534'])."','".js_escape($lang['survey_943'])."',null,500);
													$('input[type=\"checkbox\"][name=\"twilio_option_sms_initiate\"]').prop('checked', false);
												}")) .
											$lang['survey_729'] .
											RCView::span(array('style'=>'margin-left:2px;color:#C00000;font-size:16px;font-weight:bold;'), "*")
										)
									)
								) .
								// Default delivery preference
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::img(array('src'=>'arrow_user.gif')) . RCView::b($lang['survey_925']) .
										RCView::div(array('class'=>'cc_info mt-1', 'style'=>'line-height:12px;'),
											$lang['survey_1006']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_default_delivery_preference', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:90%;'),
											$invitePrefChoices, $twilio_default_delivery_preference) .
										RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'style'=>'margin-left:5px;font-size: 13px;',
											'title'=>$lang['form_renderer_02'], 'onclick'=>"deliveryPrefExplain();"), '?')
									)
								) .
								// Map delivery options to a multiple choice field to automate (optional)
								RCView::tr(array(''),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::img(array('src'=>'arrow_user.gif')) . $lang['survey_1314'] .
										RCView::div(array('class'=>'cc_info'),
											$lang['survey_1315']
										) .
										RCView::div(array('class'=>'cc_info'),
											$lang['survey_1322']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_delivery_preference_field_map',
											'class'=>'x-form-text x-form-field', 'style'=>'max-width:370px;'),
											$mcFieldsLabels, $twilio_delivery_preference_field_map) .
										RCView::div(array('class'=>'mt-4'),
											RCView::div(array('class'=>'mb-1 boldish'), $lang['survey_1320']) .
											RCView::div(array('class'=>'note fs11', 'style'=>'line-height:1.5;'), $invitePrefChoicesText)
										)
									)
								) .
								// Designated phone field
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b('<i class="fas fa-phone-alt"></i> ' . $lang['survey_1318']) .
										RCView::div(array('class'=>'cc_info', 'style'=>'line-height:12px;'),
											$lang['survey_794']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'survey_phone_participant_field',
											'class'=>'x-form-text x-form-field', 'style'=>'max-width:370px;'),
											$phoneFieldsLabels, $survey_phone_participant_field) .
										RCView::div(array('class'=>'note', 'style'=>'line-height:11px;margin-top:10px;'), $lang['survey_1319'])
									)
								) .
								// Option to append response instructions (auto-add "press 1 for...")
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_945']) .
										RCView::div(array('class'=>'cc_info', 'style'=>'line-height:12px;'),
											$lang['survey_946']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_append_response_instructions', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:280px;'),
											array('0' => $lang['survey_947'], '1' => $lang['survey_948']), $twilio_append_response_instructions) .
										RCView::div(array('class'=>'cc_info', 'style'=>'margin-top:10px;line-height:12px;'),
											$lang['survey_949']
										)
									)
								) .
								// SMS multiple invite behavior
								RCView::tr(array(),
									RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;'),
										RCView::b($lang['survey_964']) .
										RCView::div(array('class'=>'cc_info', 'style'=>'line-height:12px;'),
											$lang['survey_980']
										)
									) .
									RCView::td(array('valign'=>'top', 'class'=>'data', 'style'=>'padding:6px 10px;'),
										RCView::select(array('name'=>'twilio_multiple_sms_behavior', $twilio_multiple_sms_behavior_disabled_chk=>$twilio_multiple_sms_behavior_disabled_chk,
											'class'=>'x-form-text x-form-field', 'style'=>'max-width:95%;'),
											array('OVERWRITE'=>$lang['survey_979'], 'FIRST'=>$lang['survey_978'], 'CHOICE'=>$lang['survey_963']), $twilio_multiple_sms_behavior, 200) .
										RCView::div(array('class'=>'note', 'style'=>'line-height:11px;margin-top:10px;'), $lang['survey_981'])
									)
								)
							)
						)
					);
	}

	// SendGrid e-mail services
	if ($_POST['setting'] == 'sendgrid_enabled')
	{
		$Proj = new \Project($project_id);
		// Set dialog content
		$response = RCView::div(array(),
				// Instructions
				RCView::div(array('class'=>'mb-3'),
					RCView::div(array('style'=>''),
						$lang['survey_1388'] . " " .
						RCView::a(array('href'=>'javascript:;', 'style'=>'margin:5px 0;text-decoration:underline;', 'onclick'=>"
							$(this).hide();
							$('#sendgrid_setup_instr1').show('fade',function(){
								fitDialog($('#SendgridEnableDialog'));
							});
						"),
							$lang['global_58']
						)
					) .
					RCView::div(array('id'=>'sendgrid_setup_instr1', 'style'=>'display:none;'),
						RCView::p(array('style'=>''), $lang['survey_1385']) .
						RCView::p(array('style'=>''), $lang['survey_1390']) .
						RCView::p(array('style'=>''), $lang['survey_1386'])
					)
				) .
				RCView::form(array('id'=>'sendgrid_setup_form'),
					// Table
					RCView::table(array('cellspacing'=>0, 'class'=>'form_border', 'style'=>'width:100%;'),
						// Enabled?
						RCView::tr(array(),
							RCView::td(array('valign'=>'top', 'class'=>'labelrc nowrap '.($sendgrid_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;width:375px;'),
								RCView::img(array('src'=>'sendgrid.png', 'style'=>'position:relative;top:-2px;')) .
								RCView::b($lang['survey_1389']) . RCView::SP . RCView::SP
							) .
							RCView::td(array('valign'=>'top', 'class'=>'data '.($sendgrid_enabled ? 'darkgreen' : 'red'), 'style'=>'padding:6px 10px;'),
								RCView::select(array('name'=>'sendgrid_enabled',
									'onchange'=>"enableSendgridRowColor();", 'class'=>'x-form-text x-form-field', 'style'=>''),
									array(0=>$lang['global_23'], 1=>$lang['index_30']), $sendgrid_enabled, 200)
							)
						) .
						// Header
						RCView::tr(array(),
							RCView::td(array('colspan'=>'2', 'class'=>'header', 'style'=>'padding:6px 10px;'),
								$lang['survey_1391']
							)
						) .
						// Account Token
						RCView::tr(array(),
							RCView::td(array('valign'=>'top', 'class'=>'labelrc', 'style'=>'padding:6px 10px;width:375px;'),
								RCView::b($lang['survey_1392'])
							) .
							RCView::td(array('valign'=>'top', 'class'=>'data nowrap', 'style'=>'padding:6px 10px;'),
								RCView::input(array('type'=>'password', 'name'=>'sendgrid_project_api_key', 'autocomplete'=>'new-password',
									'class'=>'x-form-text x-form-field', 'style'=>'width:250px;', 'value'=>decrypt($sendgrid_project_api_key))) .
								RCView::a(array('href'=>'javascript:;', 'class'=>'cclink password-mask-reveal', 'style'=>'text-decoration:underline;font-size:7pt;margin-left:5px;', 'onclick'=>"$(this).remove();showPasswordField('sendgrid_project_api_key');$('input[name=sendgrid_project_api_key]').css('width','100%');"),
									$lang['survey_1373']
								)
							)
						) .
						// Add a note that protected email mode does not apply to sendgrid alerts
						(!$Proj->project['protected_email_mode'] ? '' :
							RCView::tr(array(),
								RCView::td(array('colspan'=>2, 'valign'=>'top', 'class'=>'data yellow p-3'),
									'<span class="fas fa-info-circle" style="text-indent:0;font-size:13px;" aria-hidden="true"></span> ' .
									$lang['design_1004'] . '  ' . $lang['design_1005']
								)
							)
						)
					)
				)
			);
	}
	// Rewards services
	if ($_POST['setting'] == 'rewards_enabled')
	{
		ob_start();
		include(APP_PATH_DOCROOT.'Rewards/partials/enable_rewards.php');
		$response = ob_get_clean();
	}
}
## ENABLE SENDGRID & SAVE SENDGRID CREDENTIALS
elseif (isset($_POST['sendgrid_enabled']))
{
	if (!(SUPER_USER || (!$sendgrid_enabled_by_super_users_only && $user_rights['design']))) exit("ERROR: You must be a super user to perform this action!");
	// Set values to be saved
	$sendgrid_enabled = (isset($_POST['sendgrid_enabled']) && $_POST['sendgrid_enabled'] == '1') ? '1' : '0';
	$sendgrid_project_api_key = $_POST['sendgrid_project_api_key'];

	// Verify API Key is valid and has correct scope
	list($verified, $error_msg) = SendGridRC::verifyAPIKey($sendgrid_project_api_key, $project_id);

	// Allow removal of API Key
	if ($sendgrid_project_api_key == '') {
		$verified = true;
		$error_msg = '';
		// Disable sendgrid if API Key is being removed
		$sendgrid_enabled = '0';
	} else {
		$sendgrid_project_api_key = encrypt($sendgrid_project_api_key);
	}

	if ($verified) {
		// Modify settings in table
		$sql = "update redcap_projects set sendgrid_enabled = $sendgrid_enabled,
				sendgrid_project_api_key = ".checkNull($sendgrid_project_api_key)." where project_id = $project_id";
		if (db_query($sql)) {
			// Set response
			$response = "1";
			// Logging
			Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id","Modify project settings");
		}
	}

	// If there's an error message, then display it
	if ($error_msg != '') {
		// Display error message
		print 	RCView::div(array('class'=>'red'),
					RCView::img(array('src'=>'exclamation.png')) .
					$error_msg
				);
		exit;
	}
}

elseif (isset($_POST['rewards_enabled']))
{
	try {
		// Set values to be saved
		$rewards_enabled = boolval($_POST['rewards_enabled'] ?? false);
		$rewards_confirm_continue = boolval($_POST['rewards_confirm_continue'] ?? false);
	
		if($rewards_enabled && !$rewards_confirm_continue) {
			throw new Exception("Error: You must confirm and continue to enable the Rewards Services", 401);
		}

		if (SUPER_USER || ($rewards_enable_type === 'auto' && $user_rights['design'])) {
			RewardsProjectService::toggleRewards($project_id, $rewards_enabled);
			$response = "1";
		}else {
			$manager = new RewardsActivationApprovalManager($project_id);
			$userid = USERID;
			$manager->createToDo($userid, $project_contact_email, $rewards_enabled);
			$response = "1";
			// exit;
		}
	} catch (Exception $e) {
		$response = "0";
		$message = $e->getMessage();
		$html = <<<HTML
		<div class="alert alert-danger">
			<span>$message</span>
		</div>
		HTML;
		echo $html;
		exit;
	}
}

## ENABLE TWILIO & SAVE TWILIO CREDENTIALS
elseif (isset($_POST['twilio_enabled']) && (isset($_POST['twilio_account_sid']) || isset($_POST['request_approval_by_admin'])))
{
	if (!(SUPER_USER || (in_array($twilio_enabled_by_super_users_only, ['0', '2']) && $user_rights['design']))) exit("ERROR: You must be a super user to perform this action!");

    $adminApprovingRequest = (isset($_POST['twilio_enabled']) && UserRights::isSuperUserNotImpersonator() && $_POST['request_approval_by_admin'] == '1' && isset($_POST['requester']) && isinteger($_POST['requester']));
    // Error msg
    $error_msg = "";
    if ($adminApprovingRequest) {
        $sql = "select * from redcap_twilio_credentials_temp where project_id = ".$_GET['pid']." and request_id = ".checkNull($_POST['request_id']);
        $q = db_query($sql);
        if (db_num_rows($q)) {
            $row = db_fetch_array($q);
            $twilio_enabled = 1;
            $twilio_from_number = $row['twilio_from_number'];
            $twilio_account_sid = $row['twilio_account_sid'];
            $twilio_auth_token = $row['twilio_auth_token'];
            $twilio_alphanum_sender_id = $row['twilio_alphanum_sender_id'];
        } else {
            $error_msg .= RCView::span(array('style'=>''),
                "Invalid Request"
            );
        }
    } else {
        // Set values to be saved
        $twilio_enabled = (isset($_POST['twilio_enabled']) && $_POST['twilio_enabled'] == '1') ? '1' : '0';
        $twilio_from_number = (isset($_POST['twilio_from_number']) && is_numeric($_POST['twilio_from_number'])) ? $_POST['twilio_from_number'] : '';
        $twilio_account_sid = $_POST['twilio_account_sid'];
        $twilio_auth_token = $_POST['twilio_auth_token'];
        $twilio_alphanum_sender_id = $_POST['twilio_alphanum_sender_id'];
        $twilio_request_inspector_check = (isset($_POST['twilio_request_inspector_check']) && $_POST['twilio_request_inspector_check'] == 'on');
    }

	// Make sure that Twilio number is not used by another project
	if ($twilio_from_number != '') {
        $sql1 = "select 1 from redcap_projects where project_id != $project_id and ";
        $condition = '';
        // If this is a U.S. number, try both with and without the country code
        if ((strlen($twilio_from_number) == 10 && isPhoneUS($twilio_from_number)) || (strlen($twilio_from_number) == 11 && substr($twilio_from_number, 0, 1) == '1' && isPhoneUS(right($twilio_from_number, 10)))) {
            $twilio_from_number_no_cc = right($twilio_from_number, 10);
            $condition = "(twilio_from_number = '".db_escape('1'.$twilio_from_number_no_cc)."' or twilio_from_number = '".db_escape($twilio_from_number_no_cc)."')";
        } else {
            $condition = "twilio_from_number = '".db_escape($twilio_from_number)."'";
        }
        $sql1 .= $condition;
        $q1 = db_query($sql1);

        // Check for projects where users have sent twilio details for approvals and request still pending
        $sql2 = "select 1 from redcap_twilio_credentials_temp where project_id != $project_id and ";
        $sql2 .= $condition;
        $q2 = db_query($sql2);

		if (db_num_rows($q1) || db_num_rows($q2)) {
			// ERROR: Another project has this number
			$error_msg .= RCView::span(array('style'=>''),
							$lang['survey_958'] . " " . RCView::b(formatPhone($twilio_from_number)) . " " . $lang['survey_959']
						  );
			$twilio_from_number = '';
		}
	}

	// Modify settings in table
	$sql = "update redcap_projects set twilio_enabled = $twilio_enabled,
			twilio_from_number = ".checkNull($twilio_from_number).", twilio_account_sid = ".checkNull($twilio_account_sid).",
			twilio_auth_token = ".checkNull($twilio_auth_token).", twilio_alphanum_sender_id = ".checkNull($twilio_alphanum_sender_id).", mosio_api_key = null
			where project_id = $project_id";
	if (db_query($sql)) {
		## TWILIO CHECK: Check connection to Twilio and also set the voice/sms URLs to the REDCap survey URL, if not set yet
		// Instantiate a new Twilio Rest Client
		$twilioClient = TwilioRC::client();
		// SET URLS: Loop over the list of numbers and get the sid of the phone number (don't do this if we don't have a value for $twilio_from_number)
		if ($twilio_from_number != '') {
			try {
                $numberBelongsToAcct = false;
                $allNumbers = array();
				foreach ($twilioClient->account->incoming_phone_numbers as $number) {
					// Collect number in array
					$allNumbers[] = $number->phone_number;
					// If number does not match, then skip
					if (substr($number->phone_number, -1 * strlen($twilio_from_number)) != $twilio_from_number) {
						continue;
					}
					// We verified that the number belongs to this Twilio account
					$numberBelongsToAcct = true;
					// Set VoiceUrl and SmsUrl for this number, if not set yet
					if ($number->voice_url != APP_PATH_SURVEY_FULL || $number->sms_url != APP_PATH_SURVEY_FULL) {
						$number->update(array("VoiceUrl" => APP_PATH_SURVEY_FULL, "SmsUrl" => APP_PATH_SURVEY_FULL));
					}
				}
				// If number doesn't belong to account
				if (!$numberBelongsToAcct) {
					// Set error message
					$error_msg .= $lang['survey_920'];
					if (empty($allNumbers)) {
						$error_msg .= RCView::div(array('style' => 'margin-top:10px;font-weight:bold;'), $lang['survey_843']);
					} else {
						$error_msg .= RCView::div(array('style' => 'margin-top:5px;font-weight:bold;'), " &nbsp; " . implode("<br> &nbsp; ", $allNumbers));
					}
				}
			} catch (Exception $e) {
				// Set error message
				$error_msg .= $lang['survey_919'];
				// Make sure Localhost isn't being used as REDCap base URL (not valid for Twilio)
				if (strpos(APP_PATH_SURVEY_FULL, "http://localhost") !== false || strpos(APP_PATH_SURVEY_FULL, "https://localhost") !== false) {
					$error_msg .= "<br><br>" . $lang['survey_841'];
				}
			}
		}
		// If we are missing the phone number or if an error occurred with Twilio, then disable this module
		if ($twilio_enabled && (($twilio_from_number == '' && $twilio_alphanum_sender_id == '') || $error_msg != '')) {
			$sql = "update redcap_projects set twilio_enabled = 0 where project_id = $project_id";
			db_query($sql);
			// If Twilio credentials worked but no phone number was entered, then let them know that the module was NOT enabled
			if ($twilio_from_number == '' && $error_msg == '') {
				$error_msg = $lang['survey_842'];
			}
		}
		// If there's an error message, then display it
		if ($error_msg != '') {
			// Display error message
			print 	RCView::div(array('class'=>'red'),
						RCView::img(array('src'=>'exclamation.png')) .
						$error_msg
					);
			exit;
		} else {
            if ($adminApprovingRequest) {
                // Admin approving user request to enable Twilio
                // Send email to requester
                $userInfo = User::getUserInfoByUiid($_POST['requester']);
                $email = new Message();
                $email->setFrom($project_contact_email);
                $email->setFromName($project_contact_name);
                $email->setTo($userInfo['user_email']);
                $emailContents =   "{$lang['setup_210']} <b>" . strip_tags(html_entity_decode($app_title, ENT_QUOTES)) . "</b> (PID $project_id){$lang['period']}<br><br>";
                $email->setBody($emailContents, true);
                $email->setSubject("[REDCap] {$lang['setup_211']}");
                if ($email->send()) {
                    // Mark to-do list request as completed
                    ToDoList::updateTodoStatus($project_id, 'enable twilio','completed', $userInfo['ui_id'], $_POST['request_id'] ?? null);
                    // Delete twilio credentials stored in temporary table
                    $sql = "delete from redcap_twilio_credentials_temp where request_id = ".checkNull($_POST['request_id'])." and project_id = ".$project_id;
                    db_query($sql);
                }
                $logDescription = "Enable Twilio telephony services (via user request)";
            } else {
                $logDescription = ($twilio_enabled ? "Enable Twilio telephony services" : "Disable Twilio telephony services");
            }
            // Set response
            $response = "1";
            // Logging
            Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id", $logDescription);
        }
	}
}

## ENABLE MOSIO & SAVE MOSIO CREDENTIALS
elseif (isset($_POST['twilio_enabled']) && (isset($_POST['mosio_api_key']) || isset($_POST['request_mosio_approval_by_admin'])))
{
    if (!(SUPER_USER || (in_array($mosio_enabled_by_super_users_only, ['0', '2']) && $user_rights['design']))) exit("ERROR: You must be a super user to perform this action!");

    $adminApprovingRequest = (isset($_POST['twilio_enabled']) && UserRights::isSuperUserNotImpersonator() && $_POST['request_mosio_approval_by_admin'] == '1' && isset($_POST['requester']) && isinteger($_POST['requester']));
    // Error msg
    $error_msg = "";
    if ($adminApprovingRequest) {
        $sql = "select * from redcap_twilio_credentials_temp where project_id = ".$_GET['pid']." and request_id = ".checkNull($_POST['request_id']);
        $q = db_query($sql);
        if (db_num_rows($q)) {
            $row = db_fetch_array($q);
            $twilio_enabled = 1;
            $mosio_api_key = $row['mosio_api_key'];
        } else {
            $error_msg .= RCView::span(array('style'=>''),
                "Invalid Request"
            );
        }
    } else {
        // Set values to be saved
        $twilio_enabled = (isset($_POST['twilio_enabled']) && $_POST['twilio_enabled'] == '1') ? '1' : '0';
        $mosio_api_key = $_POST['mosio_api_key'];
    }

    // Make sure another project does not have this same API key
    $sql = "select 1 from redcap_projects where project_id != $project_id and mosio_api_key = ".checkNull($mosio_api_key)." limit 1";

    // Check for projects where users have sent mosio details for approvals and request still pending
    $sql2 = "select 1 from redcap_twilio_credentials_temp where project_id != $project_id and mosio_api_key = ".checkNull($mosio_api_key)." limit 1";

    if (db_num_rows(db_query($sql)) || db_num_rows(db_query($sql2))) {
        // Display error message
        $error_msg = RCView::tt('survey_1561');
        print 	RCView::div(array('class'=>'red'),
                    RCView::img(array('src'=>'exclamation.png')) .
                    $error_msg
                );
        exit;
    }

	// Modify settings in table
	$sql = "update redcap_projects set twilio_enabled = $twilio_enabled, mosio_api_key = ".checkNull($mosio_api_key).", twilio_account_sid = null
	        where project_id = $project_id";
	if (db_query($sql)) {
        if ($adminApprovingRequest) {
            // If no error, then only send approval email to user and delete to do list entry
            if ($error_msg == '') {
                // Admin approving user request to enable Mosio
                // Send email to requester
                $userInfo = User::getUserInfoByUiid($_POST['requester']);
                $email = new Message();
                $email->setFrom($project_contact_email);
                $email->setFromName($project_contact_name);
                $email->setTo($userInfo['user_email']);
                $emailContents = "{$lang['setup_216']} <b>" . strip_tags(html_entity_decode($app_title, ENT_QUOTES)) . "</b> (PID $project_id){$lang['period']}<br><br>";
                $email->setBody($emailContents, true);
                $email->setSubject("[REDCap] {$lang['setup_217']}");
                if ($email->send()) {
                    // Mark to-do list request as completed
                    ToDoList::updateTodoStatus($project_id, 'enable mosio', 'completed', $userInfo['ui_id'], $_POST['request_id'] ?? null);
                    // Delete twilio credentials stored in temporary table
                    $sql = "delete from redcap_twilio_credentials_temp where request_id = " . checkNull($_POST['request_id']) . " and project_id = " . $project_id;
                    db_query($sql);
                }
                $logDescription = "Enable Mosio SMS services (via user request)";
            }
        } else {
            $logDescription = ($twilio_enabled ? "Enable Mosio SMS services" : "Disable Mosio SMS services");
        }
		// Set response
		$response = "1";
		// Logging
        if (db_affected_rows() > 0) {
            Logging::logEvent($sql, "redcap_projects", "MANAGE", $project_id, "project_id = $project_id", $logDescription);
        }
		## MOSIO CHECK: Check connection to Mosio and also set the voice/sms URLs to the REDCap survey URL, if not set yet
        if ($twilio_enabled) {
            $mosio = new Mosio($project_id);
            list ($success, $errors) = $mosio->verifyAccount();
            if (!$success) {
                $error_msg = $errors;
            }
        }
		// If we are missing the phone number or if an error occurred with Mosio, then disable this module
		if (!$twilio_enabled || ($twilio_enabled && $error_msg != '')) {
			$sql = "update redcap_projects set twilio_enabled = 0 where project_id = $project_id";
			db_query($sql);
		}
		// If there's an error message, then display it
		if ($error_msg != '') {
			// Display error message
			print 	RCView::div(array('class'=>'red'),
						RCView::img(array('src'=>'exclamation.png')) .
						$error_msg
					);
            exit;
		}
	}
}


## SAVE TWILIO SETTINGS
elseif ($twilio_enabled && isset($_POST['survey_phone_participant_field']))
{
	// Get all available Twilio languages
	$allLang = TwilioRC::getAllLanguages();
	// Set values to be saved
	$twilio_append_response_instructions = (isset($_POST['twilio_append_response_instructions']) && $_POST['twilio_append_response_instructions'] == '1') ? '1' : '0';
	$twilio_option_voice_initiate = (isset($_POST['twilio_option_voice_initiate']) && $_POST['twilio_option_voice_initiate'] == 'on') ? '1' : '0';
	$twilio_option_sms_invite_make_call = (isset($_POST['twilio_option_sms_invite_make_call']) && $_POST['twilio_option_sms_invite_make_call'] == 'on') ? '1' : '0';
	$twilio_option_sms_invite_receive_call = (isset($_POST['twilio_option_sms_invite_receive_call']) && $_POST['twilio_option_sms_invite_receive_call'] == 'on') ? '1' : '0';
	$twilio_option_sms_invite_web = (isset($_POST['twilio_option_sms_invite_web']) && $_POST['twilio_option_sms_invite_web'] == 'on') ? '1' : '0';
	$twilio_voice_language = (isset($_POST['twilio_voice_language']) && isset($allLang[$_POST['twilio_voice_language']])) ? $_POST['twilio_voice_language'] : 'en';
	$survey_phone_participant_field = (isset($Proj->metadata[$_POST['survey_phone_participant_field']])) ? $_POST['survey_phone_participant_field'] : "";
	$twilio_default_delivery_preference = (isset($_POST['twilio_default_delivery_preference'])) ? $_POST['twilio_default_delivery_preference'] : 'EMAIL';
	$twilio_delivery_preference_field_map = (isset($_POST['twilio_delivery_preference_field_map']) && isset($Proj->metadata[$_POST['twilio_delivery_preference_field_map']])) ? $_POST['twilio_delivery_preference_field_map'] : '';
	$twilio_multiple_sms_behavior = (isset($_POST['twilio_multiple_sms_behavior']) && $_POST['twilio_multiple_sms_behavior'] == 'OVERWRITE') ? 'OVERWRITE' : ((isset($_POST['twilio_multiple_sms_behavior']) && $_POST['twilio_multiple_sms_behavior'] == 'FIRST') ? 'FIRST' : 'CHOICE');
	$twilio_modules_enabled = $_POST['twilio_modules_enabled'];
	// Only super users can enable SMS conversation
	if ($twilio_option_sms_initiate) {
		// Option is already enabled
		$twilio_option_sms_initiate = (isset($_POST['twilio_option_sms_initiate']) && $_POST['twilio_option_sms_initiate'] == 'on') ? '1' : '0';
	} else {
		// Option not enabled yet (only let super users enable)
		$twilio_option_sms_initiate = (SUPER_USER && isset($_POST['twilio_option_sms_initiate']) && $_POST['twilio_option_sms_initiate'] == 'on') ? '1' : '0';
	}
	// Modify settings in table
	$sql = "update redcap_projects set twilio_option_voice_initiate = $twilio_option_voice_initiate,
			twilio_option_sms_initiate = $twilio_option_sms_initiate, twilio_option_sms_invite_make_call = $twilio_option_sms_invite_make_call,
			twilio_option_sms_invite_receive_call = $twilio_option_sms_invite_receive_call, twilio_option_sms_invite_web = $twilio_option_sms_invite_web,
			twilio_voice_language = '".db_escape($twilio_voice_language)."', survey_phone_participant_field = '".db_escape($survey_phone_participant_field)."',
			twilio_default_delivery_preference = '".db_escape($twilio_default_delivery_preference)."',
			twilio_append_response_instructions = '".db_escape($twilio_append_response_instructions)."',
			twilio_multiple_sms_behavior = '".db_escape($twilio_multiple_sms_behavior)."',
			twilio_modules_enabled = '".db_escape($twilio_modules_enabled)."', 
			twilio_delivery_preference_field_map = ".checkNull($twilio_delivery_preference_field_map)."
			where project_id = $project_id";
	if (db_query($sql)) {
		// Set response
		$response = "1";
		// Logging
		Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id",($Proj->messaging_provider == Messaging::PROVIDER_TWILIO ? "Modify Twilio configuration settings" : "Modify Mosio configuration settings"));
	}
}

elseif (isset($_POST['setting']) && $_POST['setting'] == 'mycap_setup' && $user_rights['design']) {
    $adminApprovingRequest = ($_POST['action'] == 'enable_mycap' && UserRights::isSuperUserNotImpersonator() && $_POST['request_approval_by_admin'] == '1' && isset($_POST['requester']) && isinteger($_POST['requester']));
    if ($_POST['action'] == 'enable_mycap') {
        if ($adminApprovingRequest) {
            // Admin approving user request to enable MyCap
            $mycapInfoUrl = APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/ProjectSetup/index.php?pid=$project_id&msg=mycap_enabled";
            // Send email to requester
            $userInfo = User::getUserInfoByUiid($_POST['requester']);
            $email = new Message();
            $email->setFrom($project_contact_email);
            $email->setFromName($project_contact_name);
            $email->setTo($userInfo['user_email']);
            $emailContents =   "{$lang['mycap_mobile_app_619']} <b>" . strip_tags(html_entity_decode($app_title, ENT_QUOTES)) . "</b> (PID $project_id){$lang['period']}<br><br>
								<a href='$mycapInfoUrl'>{$lang['mycap_mobile_app_620']}</a>";
            $email->setBody($emailContents, true);
            $email->setSubject("[REDCap] {$lang['mycap_mobile_app_618']}");
            if ($email->send()) {
                // Mark to-do list request as completed
                ToDoList::updateTodoStatus($project_id, 'enable mycap','completed', $userInfo['ui_id'], $_POST['request_id'] ?? null);
            }
            $logDescription = "Enable MyCap (via user request)";
        } else {
            // User/admin enabling MyCap on their own
            $response = 1;
            $logDescription = "Enable MyCap";
        }
        // Enable MyCap
        $sql_exists = "SELECT COUNT(*) FROM redcap_mycap_projects WHERE project_id = '".$project_id."'";
        $q = db_query($sql_exists);
        $exists = db_result($q, 0) > 0;

        // Add new date field name="Install Date" with annotation @MC-PARTICIPANT-JOINDATE @HIDDEN
        Form::addMyCapInstallDateField($project_id);
        // Add new date field name="Install Date (UTC)" with annotation @MC-PARTICIPANT-JOINDATE_UTC @HIDDEN and timezone field
        Form::addExtraMyCapInstallDateField($project_id);
        // Add new date field name="Code" with annotation @MC-PARTICIPANT-CODE @HIDDEN
        Form::addMyCapCodeField($project_id);

        if ($exists) {
            $sql = "UPDATE redcap_mycap_projects SET status = '1' WHERE project_id = '".$project_id."'";
            $sql_project = "UPDATE redcap_projects SET mycap_enabled = '1' WHERE project_id = $project_id";
            Participant::fixParticipantList($project_id);
        } else {
            $myCap = new \Vanderbilt\REDCap\Classes\MyCap\MyCap();
            $response = $myCap->initMyCap($project_id);
            $sqlConvertToFlutter = "UPDATE redcap_mycap_projects SET converted_to_flutter = '1', acknowledged_app_link = '1' WHERE project_id = '".$project_id."'";
            db_query($sqlConvertToFlutter);
        }
    } else {
        $sql = "UPDATE redcap_mycap_projects SET status = '0' WHERE project_id = '".$project_id."'";
        $sql_project = "UPDATE redcap_projects SET mycap_enabled = '0' WHERE project_id = $project_id";
        $logDescription = "Disable MyCap";
    }
    if (!empty($sql)) {
        if (db_query($sql)) {
            // Update redcap_projects to set mycap_enabled to 1/0
            db_query($sql_project);
            // Set response
            $response = ($_POST['action'] == 'enable_mycap') ? "1" : "2";
        } else {
            $response = 0;
        }
    }
    $urTableCols = getTableColumns('redcap_user_rights');
    if ($adminApprovingRequest) {
        $response = 3;
        // ALWAYS make sure that the requester has access to Mycap Participants and is NOT in a role
        $userPriv = UserRights::getPrivileges($project_id, $userInfo['username']);
        $sqlArray = $userPriv[$project_id][$userInfo['username']];
        if ($sqlArray['role_id'] == null) {
            // Not in a role
            $sql = "UPDATE redcap_user_rights SET mycap_participants = 1, design = 1, role_id = null
                    WHERE username = '".db_escape($userInfo['username'])."' AND project_id = '".$project_id."'";
        } else {
            // Currently in a role, so extrapolate role rights to the user, remove them from the role, and make sure they can design and manage participants for MyCap
            unset($sqlArray['project_id'], $sqlArray['username'], $sqlArray['api_token'], $sqlArray['expiration'], $sqlArray['group_id'], $sqlArray['external_module_config']);
            $sqlArray['mycap_participants'] = '1';
            $sqlArray['design'] = '1';
            $sqlArray['role_id'] = null;
            $sql = [];
            foreach ($sqlArray as $col=>$val) {
                if (array_key_exists($col, $urTableCols)) $sql[] = "$col = ".checkNull($val);
            }
            $sql = "update redcap_user_rights set ".implode(", ", $sql)."
                    where project_id = $project_id and username = '".db_escape($userInfo['username'])."'";
        }
        db_query($sql);
    } elseif ($response == 1) {
        $sqlArray = $user_rights;
        // ALWAYS make sure that the user doing the ENABLE MYCAP has access to Mycap Participants and is NOT in a role
        if ($sqlArray['role_id'] == null) {
            // Not in a role
            $sql = "UPDATE redcap_user_rights SET mycap_participants = 1, design = 1, role_id = null
                    WHERE username = '".db_escape(USERID)."' AND project_id = '".$project_id."'";
        } else {
            // Currently in a role, so extrapolate role rights to the user, remove them from the role, and make sure they can design and manage participants for MyCap
            unset($sqlArray['project_id'], $sqlArray['username'], $sqlArray['api_token'], $sqlArray['expiration'], $sqlArray['group_id'], $sqlArray['external_module_config']);
            $sqlArray['mycap_participants'] = '1';
            $sqlArray['design'] = '1';
            $sqlArray['role_id'] = null;
            $sql = [];
            foreach ($sqlArray as $col=>$val) {
                if (array_key_exists($col, $urTableCols)) $sql[] = "$col = ".checkNull($val);
            }
            $sql = "update redcap_user_rights set ".implode(", ", $sql)."
                    where project_id = $project_id and username = '".db_escape(USERID)."'";
        }
        db_query($sql);
    }
    if ($response == 1 || $response == 2 || $response == 3) {
        // Logging
        Logging::logEvent($sql ?? "","redcap_mycap_projects","MANAGE",$project_id,"project_id = $project_id",$logDescription);
    }
    print $response;
    exit;
}

elseif (isset($_POST['setting']) && $_POST['setting'] == 'flutter_conversion' && $_POST['action'] == 'convert') {
    global $lang;
    // Set date flutter_conversion_time to NOW
    $sql = "UPDATE redcap_mycap_projects SET converted_to_flutter = '1', flutter_conversion_time = '".NOW."' WHERE project_id = '".$project_id."'";
    if (db_query($sql)) {
        // Convert Alerts Email Body and Survey Completion Text
        list ($alertsCount, $surveysCount) = Participant::convertParticipantAccessHTMLToFlutter($project_id);

        $response = $lang['mycap_mobile_app_757'];
        if ($alertsCount > 0) {
            $response .= "<br>".$alertsCount." ".$lang['mycap_mobile_app_758'];
        }
        if ($surveysCount > 0) {
            $response .= "<br>".$surveysCount." ".$lang['mycap_mobile_app_785'];
        }
        // Logging
        Logging::logEvent($sql,"redcap_mycap_projects","MANAGE",$project_id,"project_id = $project_id","Project transitioned to Flutter App");
    }
}
elseif (isset($_POST['setting']) && $_POST['setting'] == 'new_app_link' && $_POST['action'] == 'acknowledge') {
    global $lang;
    // Set acknowledged_app_link to "1"
    $sql = "UPDATE redcap_mycap_projects SET acknowledged_app_link = '1' WHERE project_id = '".$project_id."'";
    if (db_query($sql)) {
        $response = $lang['mycap_mobile_app_942'];
        // Logging
        Logging::logEvent($sql,"redcap_mycap_projects","MANAGE",$project_id,"project_id = $project_id","Dynamic Link to App link changes acknowledged.");
    }
}
## SAVE PROJECT SETTING VALUE
else
{
	// Make sure the "name" setting is a real one that we can change
	$viableSettingsToChange = array('auto_inc_set', 'scheduling', 'randomization', 'repeatforms', 'surveys_enabled',
									'survey_email_participant_field', 'realtime_webservice_enabled', 'fhir_ddp_enabled',
									'datamart_allow_repeat_revision', 'datamart_allow_create_revision', 'datamart_cron_enabled', 'datamart_cron_end_date', 'break_the_glass_enabled',
                                    'mycap_enabled');
	if (!empty($_POST['name']) && in_array($_POST['name'], $viableSettingsToChange))
	{
	    global $mycap_enabled, $mycap_enabled_global;
        //$requestSent = ToDoList::isMyCapEnableRequestPending(PROJECT_ID);
        //if ($_POST['name'] == 'repeatforms' && ($mycap_enabled || $requestSent) && $mycap_enabled_global) exit;
		// If this is a super-user-only attribute, then make sure the user is a super user before doing anything
		if (($_POST['name'] == 'realtime_webservice_enabled' || $_POST['name'] == 'fhir_ddp_enabled') && !SUPER_USER) exit;
		// Specifically for DDP
		$sqlsub = "";
		$logDescription = "Modify project settings";
		if ($_POST['name'] == 'realtime_webservice_enabled') {
			$sqlsub = ", realtime_webservice_type = 'CUSTOM'";
			$logDescription = ($_POST['value'] == '1') ? "Enable DDP Custom module" : "Disable DDP Custom module";
		} elseif ($_POST['name'] == 'fhir_ddp_enabled') {
			$_POST['name'] = 'realtime_webservice_enabled';
			$sqlsub = ", realtime_webservice_type = 'FHIR'";
			$logDescription = ($_POST['value'] == '1') ? "Enable Clinical Data Pull (CDP) module" : "Disable Clinical Data Pull (CDP) module";
		}

		// Modify setting in table
		// manage null dates for Data Mart cron settings
		if ($_POST['name'] == 'datamart_cron_end_date' && empty($_POST['value'])) {
			$sql = "UPDATE redcap_projects SET {$_POST['name']} = NULL WHERE project_id = $project_id";
		}else {
			$sql = "update redcap_projects set {$_POST['name']} = '" . db_escape(label_decode($_POST['value'])). "' $sqlsub
			where project_id = $project_id";
		}
		// for Data Mart
		if (db_query($sql)) {
			$response = "1";
			// Logging
			Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id",$logDescription);
			// If project is being enabled as longitudinal AND also has randomization already enabled, make sure target_event is not null
			if ($_POST['name'] == 'repeatforms' && !$longitudinal && $randomization && Randomization::setupStatus()) {
				// Make sure the target event is set as the first event_id if it is currently null
				$sql = "update redcap_randomization set target_event = {$Proj->firstEventId}
						where project_id = $project_id and target_event is null";
				db_query($sql);
			}
			// Enabling or disabling longitudinal, make sure we reset the record list cache, just in case there are multiple arms, which might affect the record count.
			if ($_POST['name'] == 'repeatforms') {
				Records::resetRecordCountAndListCache($project_id);
			}
		}
	}
}

// Send response
print $response;
