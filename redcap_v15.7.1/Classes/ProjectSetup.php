<?php



/**
 * ProjectSetup Class
 */
class ProjectSetup
{
	// Render the Project Setup check list from an array
	public static function renderSetupCheckList($checkList=array(),$checkedOff=array())
	{
		global 	$Proj, $lang, $user_rights, $repeatforms, $surveys_enabled, $survey_email_participant_field,
				$twilio_enabled_by_super_users_only, $rewards_enabled, $rewards_enabled_by_super_users_only, $mosio_enabled_by_super_users_only, $longitudinal;
		foreach ($checkList as $value)
		{
			// Boolean if step has been manually marked as done
			$markedByUserAsDone = isset($value['name']) && isset($checkedOff[$value['name']]);
			// Determine icon and status text
			switch ($value['status'])
			{
				// "done"
				case '2':
					$icon = "checkbox_checked.png";
					$status_text = $lang['setup_03'];
					$status_text_color = "green";
					break;
				// "in progress"
				case '1':
					$icon = "checkbox_progress.png";
					$status_text = $lang['setup_101'];
					$status_text_color = "#5897C8";
					break;
				// "not done"
				case '0':
					$icon = "checkbox_cross.png";
					$status_text = $lang['setup_102'];
					$status_text_color = "#DB2A0F";
					break;
				// "optional"
				default:
					$icon = "checkbox_gear.png";
					$status_text = $lang['setup_103'];
					$status_text_color = "#666";
			}

			?>
			<div <?php echo (!isset($value['name']) || $value['name'] == '' ? '' : 'id="setupChklist-'.$value['name'].'"') ?> class="round chklist col-12">
				<table cellspacing="0" width="100%">
					<tr>
						<td valign="top" style="width:70px;text-align:center;">
							<?php if ($icon != "") { ?>
								<!-- Icon -->
								<div <?php echo ($value['status'] == '4' ? 'class="opacity25"' : '') ?>>
									<img id="img-<?php echo isset($value['name']) ? $value['name'] : ''; ?>" src="<?php echo APP_PATH_IMAGES . $icon ?>">
								</div>
							<?php } ?>
							<?php if ($status_text != "") { ?>
								<!-- Colored text below icon -->
								<div id="lbl-<?php echo isset($value['name']) ? $value['name'] : ''; ?>" style='color:<?php echo $status_text_color ?>;'><?php echo $status_text ?></div>
							<?php } ?>
							<!-- "I'm done!" button OR "Not complete?" link -->
							<?php if ($user_rights['design'] && $value['status'] != '2' && isset($value['name']) && !empty($value['name'])) { ?>
								<div class="chklist_comp">
									<button id="btn-<?php echo $value['name'] ?>" class="btn btn-defaultrc btn-xs doneBtn" title="<?php echo $lang['setup_01'] ?>" onclick="doDoneBtn('<?php echo $value['name'] ?>',1);"><?php echo $lang['setup_02'] ?></button>
								</div>
							<?php } elseif ($user_rights['design'] && isset($value['name']) && !empty($value['name']) && isset($checkedOff[$value['name']])) { ?>
								<div class="chklist_comp">
									<a href="javascript:;" style="" onclick="doDoneBtn('<?php echo $value['name'] ?>',0);"><?php echo $lang['setup_04'] ?></a>
								</div>
							<?php } ?>
						</td>
						<td valign="top" style="padding-left:30px;">
							<div class="chklisthdr">
								<span><?php echo $value['header'] ?></span>
							</div>
							<div class="chklisttext">
								<?php echo $value['text'] ?>
							</div>
						</td>
					</tr>
				</table>
			</div>
			<?php
		}
		// Javascript
		?>
		<script type="text/javascript">
		$(function(){
            $(".doneBtn").tooltip2({ tipClass: 'tooltip4sm', position: 'top center' });
            // Admin approving MyCap enable request
            if (super_user_not_impersonator && getParameterByName('enable_mycap') == '1' && $('#setupMyCapBtn').length) {
                $('#setupMyCapBtn').trigger('click');
            }
            // Admin approving Twilio enable request from "ToDoList" page
            if (super_user_not_impersonator && getParameterByName('enable_twilio') == '1' && $('#enableTwilioBtn').length) {
                // Admin is approving the request on behalf of a user
                simpleDialog('<?=RCView::tt_js('setup_207')?>','<?=RCView::tt_js('setup_208')?>','enable-twilio-dialog',null,null,'<?=RCView::tt_js('global_53')?>',"enableTwilioViaUserRequest();",'<?=RCView::tt_js('setup_208')?>');
                $('.ui-dialog-buttonpane button:eq(1)',$('#enable-twilio-dialog').parent()).css('font-weight','bold');
            }
            // Admin approving Mosio enable request from "ToDoList" page
            if (super_user_not_impersonator && getParameterByName('enable_mosio') == '1' && $('#enableMosioBtn').length) {
                // Admin is approving the request on behalf of a user
                simpleDialog('<?=RCView::tt_js('setup_220')?>','<?=RCView::tt_js('setup_214')?>','enable-mosio-dialog',null,null,'<?=RCView::tt_js('global_53')?>',"enableMosioViaUserRequest();",'<?=RCView::tt_js('setup_214')?>');
                $('.ui-dialog-buttonpane button:eq(1)',$('#enable-mosio-dialog').parent()).css('font-weight','bold');
            }
		});

		/**
		 * make a POST request
		 * @return promise
		 */
		function postSetting(pid, setting_name, value) {
			var data = { name: setting_name, value: value };
			return $.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, data);
		}

		// Save project setting (e.g., auto-numbering) when user clicks checkbox in optional modules section
		function saveProjectSetting(ob,name,checkedValue,uncheckedValue,reloadPage,anchor) {
			if (ob.is('button')) {
				// Get value from button attribute
				var value = (ob.attr('checked') == 'checked') ? uncheckedValue : checkedValue;
			} else {
				// Get value on checkbox checked status
				var value = ob.prop('checked') ? checkedValue : uncheckedValue;
			}
			$.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, { name: name, value: value }, function(data){
				if (data != '1') {
					alert(woops);
				} else {
					ob.parent().find('.savedMsg:first').css({'visibility':'visible'});
					if (reloadPage) {
						// For certain settings, append the URL with a msg
						var url_append = "";
						// Determine how to reload the page
						setTimeout(function(){
							if (getParameterByName('msg') == '' && url_append == '') {
								// Reload page
								window.location.reload();
							} else {
								// If msg is in query string, then don't do regular redirect because it'll redisplay msg again
								var url = app_path_webroot + page + "?pid=" + pid + url_append;
								if (anchor != null) url += "&z="+Math.random()+"#"+anchor;
								window.location.href = url;
							}
						},200);
					} else {
						setTimeout(function(){
							ob.parent().find('.savedMsg:first').css({'visibility':'hidden'});
						},2000);
					}
				}
			});
		}

		// When user clicks "I'm Done" button on Setup Checklist page
		function doDoneBtn(name,action,optionalSaveValue) {
			// Ensure that name exists
			if (name == '') return;
			// Set optional save value
			if (optionalSaveValue == null) optionalSaveValue = "";
			// Set action
			action = (action == 1) ? 'add' : 'remove';
			// Change icon and text
			$('#btn-'+name).hide();
			// Save the user-defined value
			$.post(app_path_webroot+'ProjectSetup/checkmark_ajax.php?pid='+pid, { name: name, action: action, optionalSaveValue: optionalSaveValue }, function(data){
				if (data != '1') {
					if (action == 'add') {
						$('#btn-'+name).show();
					}
					alert(woops);
				} else if (action == 'remove') {
					window.location.href = app_path_webroot+page+'?pid='+pid;
				} else if (action == 'add') {
					// Increment the steps completed at top of page
					var stepsCompleted = $('#stepsCompleted').text()*1 + 1;
					var stepsTotal = $('#stepsTotal').text()*1;
					if (stepsCompleted > stepsTotal) stepsCompleted = stepsTotal;
					$('#stepsCompleted').html(stepsCompleted);
					// Success
					if (optionalSaveValue === "") {
						// Change icon
						$('#img-'+name).prop('src', app_path_images+'checkbox_checked.png');
						$('#lbl-'+name).html('<?php echo js_escape($lang['setup_03']) ?>');
						$('#lbl-'+name).css('color','green');
						$('#lbl-'+name).next('.chklist_comp').html('<a href="javascript:;" onclick="doDoneBtn(\''+name+'\',0);"><?php echo js_escape($lang['setup_04']) ?></a>');
						$(".tooltip4sm").css('display','none');
					} else {
						// Change icon and reload page
						$('#lbl-'+name).html('');
						window.location.href = app_path_webroot+page+'?pid='+pid;
					}
				}
			});
		}

		// Prompt user to confirm if they want to turn off longitudinal (because their other arms/events will get orphaned)
		function confirmUndoLongitudinal() {
			simpleDialog(null,null,'longiConfirmDialog',null,null,'<?php echo js_escape($lang['global_53']) ?>',"saveProjectSetting($('#setupLongiBtn'),'repeatforms','1','0',1);",'<?php echo js_escape($lang['control_center_153']) ?>');
		}

		// Prompt user to confirm if they want to turn off survey usage (because their surveys will get orphaned)
		function confirmUndoEnableSurveys() {
			simpleDialog(null,null,'useSurveysConfirmDialog',null,null,'<?php echo js_escape($lang['global_53']) ?>',"saveProjectSetting($('#setupEnableSurveysBtn'),'surveys_enabled','1','0',1);",'<?php echo js_escape($lang['control_center_153']) ?>');
		}

        // Prompt user to confirm if they want to turn off mycap usage
        function confirmUndoEnableMyCap() {
            simpleDialog(null,null,'myCapConfirmDialog',null,null,'<?php echo js_escape($lang['global_53']) ?>',"enableMyCap(0);",'<?php echo js_escape($lang['control_center_153']) ?>');
        }

		/**
		 * create a dialog with a promise
		 * 
		 * @param {string} title
		 * @param {string} body
		 * @param {string} confirmButton
		 * @param {string} closeButton
		 * @return {Promise}
		 */
		function modalDialog(title, body, confirmButton, closeButton) {
			var dfd = jQuery.Deferred();
			var dialog_id = '__REDCAP_DIALOG__';
			var dialogContainer = document.getElementById(dialog_id);
			title = title || 'title';
			body = body || 'Are you sure you want to disable this option?';

			var createDialogNode = function(dialog_id) {
				var container = document.createElement('div');
				container.setAttribute('id', dialog_id);
				document.body.appendChild(container);
				return container;
			}

			var createButtons = function(confirmButton, closeButton) {
				confirmButton = confirmButton || false;
				closeButton = closeButton || 'close';
				var buttons = [
					{
						text: closeButton,
						click: function() {
							dfd.reject({text: 'canceled', confirm: false} );
							$( this ).dialog( "close" );
						}
					}
				];
				if(confirmButton) {
					buttons.push({
						text: confirmButton,
						click: function() {
							dfd.resolve({text: 'confirmed', confirm: true});
							$( this ).dialog( "close" );
						}
					})
				}
				return buttons;
			}

			if(!dialogContainer) {
				dialogContainer = createDialogNode(dialog_id)
			}
			// SET THE BODY
			dialogContainer.innerHTML = body;

			
			// INIT THE DIALOG
			$(dialogContainer).dialog({
				modal: true,
				autoOpen: false,
				close: function( event, ui ) {
					dfd.reject( {
						text: 'closed',
					});
				},
				buttons: createButtons(confirmButton, closeButton),
			});
			// SET THE TITLE
			$(dialogContainer).dialog('option', 'title', title);
			// SHOW THE DIALOG
			$(dialogContainer).dialog('open');

			return dfd.promise();
		}

		/**
		 * toggle a setting:
		 * - if the setting is disabled then enable and reload
		 * - if a setting is enabled then show modal confirmation and 
		 */
		function toggleSetting(event, setting_name) {
			var modal_title = '<?= $lang['project_setup_modal_title'] ?>';
			var modal_body = '<?= $lang['project_setup_modal_body'] ?>';
			var modal_okButton = '<?= $lang['control_center_153'] ?>';
			var element = event.target;
			var is_checked = element.getAttribute('checked')==='checked';

			// save project setting on enable
			var enable_function = function() {
				element.setAttribute('disabled', true);
				return saveProjectSetting($(element), setting_name,'1','0',1)
			};
			// show modal confirm dialog before disabling
			var disable_function = function() {
				return modalDialog(modal_title, modal_body, modal_okButton).done(enable_function)
			};
			try {
				if(is_checked) disable_function();
				else enable_function();
			} catch (error) {
				element.setAttribute('disabled', false);
				console.log(error)
			}
		}

		// Change color of "twilio_enabled" row in dialog to enable Twilio services
		function enableTwilioRowColor(sendRequestTodoList) {
			var ob = $('#TwilioEnableDialog select[name="twilio_enabled"]');
			var enable = ob.val();
			if (enable == '1') {
				ob.parents('tr:first').children().removeClass('red').addClass('darkgreen');
                if (sendRequestTodoList == '1') {
                    var info_html = '<i id="open-twilio-note-box" class="fas fa-info-circle text-secondary" data-bs-toggle="popover" data-trigger="hover" data-placement="left" data-bs-placement="left" data-content="<?php echo $lang['setup_213'] ?>" data-title="<?php echo $lang['survey_105'] ?>" style="padding:15px 10px 0px 0px;"></i>';
                    $('#TwilioEnableDialog').dialog("widget").find(".ui-dialog-buttonpane button").eq(1).after(info_html);
                    $('#TwilioEnableDialog').dialog("widget").find(".ui-dialog-buttonpane button").eq(1).text('<?php echo $lang['setup_212'] ?>');
                }
			} else {
				ob.parents('tr:first').children().removeClass('darkgreen').addClass('red');
                $("#requireApprovalDiv").remove();
                $('#TwilioEnableDialog').dialog("widget").find(".ui-dialog-buttonpane button").eq(1).text('<?php echo $lang['pub_085'] ?>');
                $('#open-twilio-note-box').remove();
			}
		}

		// Change color of "twilio_enabled" row in dialog to enable Mosio SMS services
		function enableMosioRowColor(sendRequestTodoList) {
			var ob = $('#MosioEnableDialog select[name="twilio_enabled"]');
			var enable = ob.val();
			if (enable == '1') {
				ob.parents('tr:first').children().removeClass('red').addClass('darkgreen');
                if (sendRequestTodoList == '1') {
                    var info_html = '<i id="open-mosio-note-box" class="fas fa-info-circle text-secondary" data-bs-toggle="popover" data-trigger="hover" data-placement="left" data-bs-placement="left" data-content="<?php echo $lang['setup_219'] ?>" data-title="<?php echo $lang['survey_105'] ?>" style="padding:15px 10px 0px 0px;"></i>';
                    $('#MosioEnableDialog').dialog("widget").find(".ui-dialog-buttonpane button").eq(1).after(info_html);
                    $('#MosioEnableDialog').dialog("widget").find(".ui-dialog-buttonpane button").eq(1).text('<?php echo $lang['setup_218'] ?>');
                }
			} else {
				ob.parents('tr:first').children().removeClass('darkgreen').addClass('red');
                $("#requireApprovalDiv").remove();
                $('#MosioEnableDialog').dialog("widget").find(".ui-dialog-buttonpane button").eq(1).text('<?php echo $lang['pub_085'] ?>');
                $('#open-mosio-note-box').remove();
			}
		}

		// Change color of "sendgrid_enabled" row in dialog to enable Twilio services
		function enableSendgridRowColor() {
			var ob = $('#SendgridEnableDialog select[name="sendgrid_enabled"]');
			var enable = ob.val();
			if (enable == '1') {
				ob.parents('tr:first').children().removeClass('red').addClass('darkgreen');
			} else {
				ob.parents('tr:first').children().removeClass('darkgreen').addClass('red');
			}
		}

		// Dialog to enable/disable Twilio services
        function dialogTwilioEnable(sendTodoListRequest) {
            if (sendTodoListRequest != 1) sendTodoListRequest = 0;
            $.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, { action: 'view', setting: 'twilio_enabled' }, function(data){
                if (data == '0') {
                    alert(woops);
                    return;
                }
                initDialog('TwilioEnableDialog');
                $('#TwilioEnableDialog')
                    .html(data)
                    .dialog({ bgiframe: true, modal: true, width: 800, title: '<?php echo js_escape($lang['survey_711']) ?>', buttons: {
                            <?php if (SUPER_USER || (in_array($twilio_enabled_by_super_users_only, ['0', '2']) && $user_rights['design'])) { ?>
                            '<?php echo js_escape($lang['global_53']) ?>': function() {
                                $(this).dialog('close');
                            },
                            '<?php echo js_escape($lang['pub_085']) ?>': function() {
                                var enabled = ($('select[name=twilio_enabled]').val() == '1');
                                // Ajax call
                                showProgress(1);
                                if (sendTodoListRequest == '1') {
                                    $.post(app_path_webroot+'ProjectGeneral/notifications.php?pid='+pid+'&type=admin_enable_twilio', $('#twilio_setup_form').serializeObject(), function (data2) {
                                        if (data2 == '1') {
                                            simpleDialog('<?=RCView::tt_js('control_center_4617')?>', '<?=RCView::tt_js('home_12')?>', null, 500, "$('#TwilioEnableDialog').dialog('close');");
                                            $('#enableTwilioBtn').prop('disabled', true);
                                            $('#enableTwilioBtn').prop('onclick','');
                                            // Disable enable mosio button as request is pending for twilio approval
                                            $('#enableMosioBtn').prop('disabled', true);
                                            $('#enableMosioBtn').prop('onclick','');

                                            $('#enableTwilioBtn').parent().append('<div class="twilio-enable-req-pending"><?=RCView::tt_js("setup_144")?></div>');
                                            showProgress(0,0);
                                        } else {
                                            // Display error if any, and do not sent request to admin
                                            simpleDialog(data2,'<?php echo js_escape($lang['global_01']) ?>',null,550,"$('#TwilioEnableDialog').dialog('close');showProgress(1);dialogTwilioEnable(1);showProgress(0,0);");
                                            showProgress(0,0);
                                        }
                                        // If we're in the To-Do List, close the dialog for this request
                                        if (inIframe()) {
                                            window.top.$('.iframe-container').fadeOut(200, function(){
                                                window.top.location.reload();
                                            });
                                        }
                                    });
                                } else {
                                    $.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, $('#twilio_setup_form').serializeObject(), function(data){
                                        showProgress(0,0);
                                        if (data == '0' || data == '') {
                                            alert(woops);
                                        } else if (data == '1') {
                                            $('#TwilioEnableDialog').dialog('close');
                                            setTimeout(function(){
                                                if (enabled && '<?=$GLOBALS['twilio_enabled']?>' != '1') {
                                                    window.location.href = app_path_webroot + page + "?pid=" + pid + "&msg=twilio_enabled&z="+Math.random()+"#setupChklist-modules";
                                                } else {
                                                    window.location.href = app_path_webroot + page + "?pid=" + pid + "&msg=projectmodified";
                                                }
                                            },200);
                                        } else {
                                            simpleDialog(data,'<?php echo js_escape($lang['global_01']) ?>',null,550,"$('#TwilioEnableDialog').dialog('close');showProgress(1);dialogTwilioEnable(0);showProgress(0,0);");
                                        }
                                    });
                                }
                            }
                            <?php } ?>
                        }});
                fitDialog($('#TwilioEnableDialog'));
                $('#TwilioEnableDialog select[name="twilio_enabled"]').focus();
            });
        }

        // Dialog to enable Twilio services via user request - Clicked "Enable" from ToDoList page
        function enableTwilioViaUserRequest() {
            $.post(app_path_webroot + 'ProjectSetup/modify_project_setting_ajax.php?pid=' + pid, { twilio_enabled: 1, request_approval_by_admin: 1, requester: getParameterByName('requester'), request_id: getParameterByName('request_id')}, function (data) {
                if (data == '1') {
                    simpleDialog('<?=RCView::tt_js('setup_209')?>','<?=RCView::tt_js('global_79')?>');
                } else {
                    simpleDialog(data,'<?=RCView::tt_js('global_01')?>');
                }
            });
        }

		// Dialog to enable/disable Mosio SMS services
		function dialogMosioEnable(sendTodoListRequest) {
            if (sendTodoListRequest != 1) sendTodoListRequest = 0;
			$.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, { action: 'view', setting: 'mosio_enabled' }, function(data){
				if (data == '0') {
					alert(woops);
					return;
				}
				initDialog('MosioEnableDialog');
				$('#MosioEnableDialog')
					.html(data)
					.dialog({ bgiframe: true, modal: true, width: 800, title: '<?php echo js_escape($lang['dashboard_131']) ?>', buttons: {
					<?php if (SUPER_USER || (in_array($mosio_enabled_by_super_users_only, ['0', '2']) && $user_rights['design'])) { ?>
						'<?php echo js_escape($lang['global_53']) ?>': function() {
							$(this).dialog('close');
						},
						'<?php echo js_escape($lang['pub_085']) ?>': function() {
							// Ajax call
                            var enabled = ($('select[name=twilio_enabled]').val() == '1');
							showProgress(1);
                            if (sendTodoListRequest == '1') {
                                $.post(app_path_webroot+'ProjectGeneral/notifications.php?pid='+pid+'&type=admin_enable_mosio', $('#mosio_setup_form').serializeObject(), function (data2) {
                                    if (data2 == '1') {
                                        simpleDialog('<?=RCView::tt_js('control_center_4617')?>', '<?=RCView::tt_js('home_12')?>', null, 500, "$('#MosioEnableDialog').dialog('close');");
                                        $('#enableMosioBtn').prop('disabled', true);
                                        $('#enableMosioBtn').prop('onclick','');
                                        // Disable enable twilio button as request is pending for mosio approval
                                        $('#enableTwilioBtn').prop('disabled', true);
                                        $('#enableTwilioBtn').prop('onclick','');
                                        $('#enableMosioBtn').parent().append('<div class="twilio-enable-req-pending"><?=RCView::tt_js("setup_144")?></div>');
                                        showProgress(0,0);
                                    } else {
                                        // Display error if any, and do not sent request to admin
                                        simpleDialog(data2,'<?php echo js_escape($lang['global_01']) ?>',null,550,"$('#MosioEnableDialog').dialog('close');showProgress(1);dialogMosioEnable(1);showProgress(0,0);");
                                        showProgress(0,0);
                                    }
                                    // If we're in the To-Do List, close the dialog for this request
                                    if (inIframe()) {
                                        window.top.$('.iframe-container').fadeOut(200, function(){
                                            window.top.location.reload();
                                        });
                                    }
                                });
                            } else {
                                $.post(app_path_webroot + 'ProjectSetup/modify_project_setting_ajax.php?pid=' + pid, $('#mosio_setup_form').serializeObject(), function (data) {
                                    showProgress(0, 0);
                                    if (data == '0' || data == '') {
                                        alert(woops);
                                    } else if (data == '1') {
                                        $('#MosioEnableDialog').dialog('close');
                                        setTimeout(function () {
                                            if (enabled && '<?=$GLOBALS['twilio_enabled']?>' != '1') {
                                                window.location.href = app_path_webroot + page + "?pid=" + pid + "&msg=mosio_enabled&z=" + Math.random() + "#setupChklist-modules";
                                            } else {
                                                window.location.href = app_path_webroot + page + "?pid=" + pid + "&msg=projectmodified";
                                            }
                                        }, 200);
                                    } else {
                                        simpleDialog(data, '<?php echo js_escape($lang['global_01']) ?>', null, 550, "$('#MosioEnableDialog').dialog('close');showProgress(1);dialogMosioEnable(0);showProgress(0,0);");
                                    }
                                });
                            }
						}
					<?php } ?>
				}});
				fitDialog($('#MosioEnableDialog'));
				$('#MosioEnableDialog select[name="mosio_enabled"]').focus();
			});
		}

        // Dialog to enable Mosio services via user request - Clicked "Enable" from ToDoList page
        function enableMosioViaUserRequest() {
            $.post(app_path_webroot + 'ProjectSetup/modify_project_setting_ajax.php?pid=' + pid, { twilio_enabled: 1, request_mosio_approval_by_admin: 1, requester: getParameterByName('requester'), request_id: getParameterByName('request_id')}, function (data) {
                if (data == '1') {
                    simpleDialog('<?=RCView::tt_js('setup_215')?>','<?=RCView::tt_js('global_79')?>');
                } else {
                    simpleDialog(data,'<?=RCView::tt_js('global_01')?>');
                }
            });
        }

		// Dialog to set up settings for Twilio services
		function dialogTwilioSetup() {
			$.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, { action: 'view', setting: 'twilio_setup' }, function(data){
				if (data == '0') {
					alert(woops);
					return;
				}
				initDialog('TwilioEnableDialog');
				$('#TwilioEnableDialog')
					.html(data)
					.dialog({ bgiframe: true, modal: true, width: 950, title: '<?php echo js_escape($Proj->messaging_provider == Messaging::PROVIDER_TWILIO ? $lang['survey_711'] : $lang['survey_1536']) ?>', buttons: {
					'<?php echo js_escape($lang['global_53']) ?>': function() {
						$(this).dialog('close');
					},
					'<?php echo js_escape($lang['pub_085']) ?>': function() {
						// Make sure at least one checkbox is selected (if using surveys)
						if (($(':input[name="twilio_modules_enabled"]').val() == 'SURVEYS' || $(':input[name="twilio_modules_enabled"]').val() == 'SURVEYS_ALERTS')
                            && $('td#twilio_options_checkboxes input[type="checkbox"]:checked').length < 1) {
							simpleDialog('<?php echo js_escape($lang['survey_904']) ?>');
							return;
						}
						// Make sure the invitation preference selected has been checked as a checkbox (excluding EMAIL)
						var twilio_default_delivery_preference = $('#TwilioEnableDialog select[name="twilio_default_delivery_preference"] option:selected').val();
						if (twilio_default_delivery_preference != 'EMAIL') {
							var twilio_default_delivery_preference2 = 'twilio_option_'+twilio_default_delivery_preference.toLowerCase();
							if (!$('#TwilioEnableDialog input[name="'+twilio_default_delivery_preference2+'"][type="checkbox"]:checked').length) {
								simpleDialog('<?php echo js_escape($lang['survey_926']) ?>');
								return;
							}
						}
						// Ajax call
						showProgress(1);
						$.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, $('#twilio_setup_form').serializeObject(), function(data){
							showProgress(0,0);
							if (data == '0' || data == '') {
								alert(woops);
							} else if (data == '1') {
								$('#TwilioEnableDialog').dialog('close');
								setTimeout(function(){
									window.location.href = app_path_webroot + page + "?pid=" + pid + "&msg=1";
								},200);
							} else {
								simpleDialog(data,'<?php echo js_escape($lang['global_01']) ?>',null,550,"$('#TwilioEnableDialog').dialog('close');showProgress(1);dialogTwilioEnable();showProgress(0,0);");
							}
						});
					}
				}});
				fitDialog($('#TwilioEnableDialog'));
				$('#TwilioEnableDialog select[name="twilio_voice_language"]').focus();
			});
		}
		
		// Dialog to enable/disable SendGrid services
		function dialogSendgridEnable() {
			$.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, { action: 'view', setting: 'sendgrid_enabled' }, function(data){
				if (data == '0') {
					alert(woops);
					return;
				}
				initDialog('SendgridEnableDialog');
				$('#SendgridEnableDialog')
					.html(data)
					.dialog({ bgiframe: true, modal: true, width: 800, title: '<?php echo js_escape($lang['survey_1389']) ?>', buttons: {
					<?php if (SUPER_USER || $user_rights['design']) { ?>
						'<?php echo js_escape($lang['global_53']) ?>': function() {
							$(this).dialog('close');
						},
						'<?php echo js_escape($lang['pub_085']) ?>': function() {
							// Ajax call
							showProgress(1);
							$.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, $('#sendgrid_setup_form').serializeObject(), function(data){
								showProgress(0,0);
								if (data == '0' || data == '') {
									alert(woops);
								} else if (data == '1') {
									$('#SendgridEnableDialog').dialog('close');
									setTimeout(function(){
										window.location.href = app_path_webroot + page + "?pid=" + pid + "&msg=sendgrid_enabled&z="+Math.random()+"#setupChklist-modules";
									},200);
								} else {
									simpleDialog(data,'<?php echo js_escape($lang['global_01']) ?>',null,550,"$('#SendgridEnableDialog').dialog('close');showProgress(1);dialogSendgridEnable();showProgress(0,0);");
								}
							});
						}
					<?php } ?>
				}});
				fitDialog($('#SendgridEnableDialog'));
				$('#SendgridEnableDialog select[name="sendgrid_enabled"]').focus();
			});
		}

		// Dialog to enable/disable survey participant email field
		function dialogSurveyEmailField(enable) {
			if (enable) {
				// Prompt with dialog to enable the field
				$.post(app_path_webroot+'ProjectSetup/modify_project_setting_ajax.php?pid='+pid, { action: 'view', setting: 'survey_email_participant_field' }, function(data){
					if (data == '0') {
						alert(woops);
						return;
					}
					initDialog('surveyEmailFieldEnableDialog');
					$('#surveyEmailFieldEnableDialog')
						.html(data)
						.dialog({ bgiframe: true, modal: true, width: 700, title: '<?php echo js_escape($lang['setup_191']) ?>', buttons: {
						'<?php echo js_escape($lang['global_53']) ?>': function() {
							$(this).dialog('close');
						},
						'<?php echo js_escape($lang['pub_085']) ?>': function() {
							if ( $('#surveyPartEmailFieldName').val().length < 1) {
								simpleDialog('<?php echo js_escape($lang['setup_117']) ?>');
							} else {
								$('#surveyEmailFieldEnableDialog').dialog('close');
								saveProjectSetting($('#enableSurveyPartEmailFieldBtn'),'survey_email_participant_field',$('#surveyPartEmailFieldName').val(),'',1);
							}
						}
					}});
				});
			} else {
				// Prompt with dialog to disable the field
				simpleDialog('<?php echo js_escape($lang['setup_118']) ?>','<?php echo js_escape($lang['setup_119']." \"$survey_email_participant_field\"".$lang['questionmark']) ?>',null,null,null,'<?php echo js_escape($lang['global_53']) ?>',"saveProjectSetting($('#enableSurveyPartEmailFieldBtn'),'survey_email_participant_field','','',1); ",'<?php echo js_escape($lang['setup_120']) ?>');
			}
		}

		// Dialog to open repeating forms/events dialog
		function dialogRepeatingInstance() {
			$.post(app_path_webroot+'index.php?pid='+pid+'&route=RepeatInstanceController:renderSetup', { }, function(data){
				if (data == '0') {
					alert(woops);
					return;
				}
				initDialog('repeatingInstanceEnableDialog');
				$('#repeatingInstanceEnableDialog').html(data);
                $(".whole-option-disable").each(function(){
                    $("option[value*='WHOLE']", this).prop('disabled',true);
                });
                $(".not-repeating-option-disable").each(function(){
                    $("option[value='']", this).prop('disabled',true);
                });
				$('#repeatingInstanceEnableDialog').dialog({ bgiframe: true, modal: true, width: (longitudinal ? 850 : 700), open: function(){ fitDialog(this) }, title: '<?php echo js_escape($longitudinal ? $lang['setup_146'] : $lang['setup_145']) ?>', buttons: {
					'<?php echo js_escape($lang['global_53']) ?>': function() {
						$(this).dialog('close');
					},
					'<?php echo js_escape($lang['pub_085']) ?>': function() {
						// Make sure we don't have any repeating forms with no forms selected
						var errors = 0;
						$('#repeatingInstanceEnableDialog .repeat_select option[value="PARTIAL"]:selected').each(function(){
							var tr = $(this).parents('tr:first');
							if (!$('.repeat_form_chkbox:checked', tr).length) errors++;
						});
						if (errors > 0) {
							simpleDialog('<?php echo js_escape($lang['setup_161']) ?>');
							return;
						}
						// Save via ajax
						$('#repeatingInstanceEnableDialog').dialog('close');
						$.post(app_path_webroot+'index.php?pid='+pid+'&route=RepeatInstanceController:saveSetup',$('#repeat_instance_setup_form').serializeObject(),function(data){
							if (data == '0') {
								alert(woops);
								return;
							}
							$('#repeatingInstanceEnableDialog').remove();
							simpleDialog('<img src="'+app_path_images+'tick.png"> <span style="font-size:14px;color:green;"><?php echo js_escape($lang['setup_157']) ?></span>','<?php echo js_escape($lang['survey_605']) ?>',null,null,function(){ window.location.reload(); },'<?php echo js_escape($lang['calendar_popup_01']) ?>');
							setTimeout(function(){ window.location.reload(); },2500);
						});
					}
				}});
				// Give warning to users in a production project about changing repeating instrument/events settings if some are already checked (may cause data to be orphaned)
                if (status > 0 && $('#repeatingInstanceEnableDialog :input[type=checkbox]:checked').length) {
                    setTimeout(function(){
                        simpleDialog('<div style="color:#C00000;"><i class="fas fa-exclamation-triangle"></i> <?php echo js_escape($lang['edit_project_194']) ?></div>','<?php echo js_escape($lang['global_48']) ?>',null,600);
                    },500);
                }
			});
		}
		
		// Action when selecting repeating event/form option
		function showEventRepeatingForms(ob,event_id) {
			var tr = $(ob).parents('tr:first');
			if ($(ob).val() == 'PARTIAL') {
				$('input', tr).prop('disabled', false);
				$('.repeat_event_form_div_parent', tr).removeClass('text-muted-more');
			} else {
				$('input', tr).prop('disabled', true);
				$('.repeat_event_form_div_parent', tr).addClass('text-muted-more');
				// Check all instruments if selecting Entire Event
				if ($(ob).val() == 'WHOLE') {
					$('input', tr).prop('checked', true);
				}
			}			
			if ($(ob).val() == '') {
				$('img', tr).addClass('hidden');
				$('.text-success-more', tr).addClass('text-danger').removeClass('text-success-more');
				// Uncheck all instruments if selecting Not Repeating
				$('input', tr).prop('checked', false);
			} else {
				$('img', tr).removeClass('hidden');
				$('.text-danger', tr).addClass('text-success-more').removeClass('text-danger');
			}
		}
		
		// Action when clicking checkbox of repeating form option
		function setRepeatingFormsLabel(ob) {
			var tr = $(ob).parents('tr:first');
			if ($(ob).prop('checked')) {
				$('.text-danger', tr).addClass('text-success-more').removeClass('text-danger');
			} else {
				$('.text-success-more', tr).addClass('text-danger').removeClass('text-success-more');
			}
		}

        // Enable myCap js function
        function enableMyCap(isEnable, sendTodoListRequest, adminOverride) {
            var action = isEnable ? 'enable_mycap' : 'disable_mycap';
            if (sendTodoListRequest != 1) sendTodoListRequest = 0;
            if (typeof adminOverride == 'undefined') adminOverride = 0;
            if (!sendTodoListRequest && AUTOMATE_ALL != '1' && adminOverride == 0 && super_user_not_impersonator && getParameterByName('enable_mycap') == '1') {
                // Admin is approving the request on behalf of a user
                simpleDialog('<?=RCView::tt_js('mycap_mobile_app_614')?>','<?=RCView::tt_js('mycap_mobile_app_611')?>','enable-mycap-dialog',null,null,'<?=RCView::tt_js('global_53')?>',"enableMyCap(1,0,1);",'<?=RCView::tt_js('mycap_mobile_app_611')?>');
                $('.ui-dialog-buttonpane button:eq(1)',$('#enable-mycap-dialog').parent()).css('font-weight','bold');
            } else if (!sendTodoListRequest || AUTOMATE_ALL == '1') {
                // Approve the request (no confirmations)
                var request_approval_by_admin = (AUTOMATE_ALL != '1' && adminOverride == 1 && super_user_not_impersonator && getParameterByName('enable_mycap') == '1') ? 1 : 0;
                $.post(app_path_webroot + 'ProjectSetup/modify_project_setting_ajax.php?pid=' + pid, { action: action, setting: 'mycap_setup', request_approval_by_admin: request_approval_by_admin, requester: getParameterByName('requester'), request_id: getParameterByName('request_id')}, function (data) {
                    if (data == '3') {
                        simpleDialog('<?=RCView::tt_js('mycap_mobile_app_621')?>','<?=RCView::tt_js('global_79')?>');
                    } else if (data == '1') {
                        setTimeout(function () {
                            window.location.href = app_path_webroot + 'ProjectSetup/index.php?pid=' + pid + "&msg=mycap_enabled";
                        }, 200);
                    } else if (data == '2') {
                        setTimeout(function () {
                            window.location.href = app_path_webroot + 'ProjectSetup/index.php?pid=' + pid + "&msg=1";
                        }, 200);
                    } else {
                        alert(woops);
                    }
                });
            } else {
                // Send request to admin
                simpleDialog('<?=RCView::tt_js('mycap_mobile_app_612')?>','<?=RCView::tt_js('mycap_mobile_app_611')?>','enable-mycap-dialog',null,null,'<?=RCView::tt_js('global_53')?>',function(){
                    $.get(app_path_webroot+'ProjectGeneral/notifications.php',{pid: pid, type: 'admin_enable_mycap'},function(data2) {
                        simpleDialog('<?=RCView::tt_js('control_center_4617')?>', '<?=RCView::tt_js('home_12')?>');
                        $('#setupMyCapBtn, #setupLongiBtn').prop('disabled', true);
                        $('#setupMyCapBtn, #setupLongiBtn').prop('onclick','');
                        $('#setupMyCapBtn').parent().append('<div class="mycap-enable-req-pending"><?=RCView::tt_js("setup_144")?></div>');
                    });
                },'<?=RCView::tt_js('mycap_mobile_app_613')?>');
                $('.ui-dialog-buttonpane button:eq(1)',$('#enable-mycap-dialog').parent()).css('font-weight','bold');
            }
        }

		// Dialog to enable/disable Rewards service
		function dialogRewardsEnable() {
			// Extract PHP variables
			var dialogTitle = '<?= js_escape($lang['rewards_settings_title']) ?>';
			var closeButtonLabel = '<?= js_escape($lang['global_53']) ?>';
			var saveButtonLabel = '<?= js_escape($lang['pub_085']) ?>';
			var globalErrorLabel = '<?= js_escape($lang['global_01']) ?>';
			var isSuperUser = <?= json_encode(SUPER_USER) ?>;
			var rewardsEnabledBySuperUsersOnly = <?= json_encode($rewards_enabled_by_super_users_only) ?>;
			var userHasDesignRights = <?= json_encode($user_rights['design']) ?>;
			var globalRewardsEnabled = '<?= $GLOBALS['rewards_enabled'] ?>';
			var rewardsEnableType = <?= json_encode($GLOBALS['rewards_enable_type'] ?? 'auto') ?>;
			var errorMessage = 'An error occurred. Please try again later.';

			// Load dialog content
			$.post(app_path_webroot + 'ProjectSetup/modify_project_setting_ajax.php?pid=' + pid, 
				{ action: 'view', setting: 'rewards_enabled' },
				function(data) {
					if (data == '0') {
						alert(errorMessage);
						return;
					}
					initDialog('RewardsEnableDialog');
					$('#RewardsEnableDialog')
						.html(data)
						.dialog({
							bgiframe: true,
							modal: true,
							width: 800,
							title: dialogTitle,
							buttons: getDialogButtons()
						});
					fitDialog($('#RewardsEnableDialog'));
					$('#RewardsEnableDialog select[name="rewards_enabled"]').focus();
				}
			).fail(function() {
				alert(errorMessage);
			});

			function getDialogButtons() {
				var buttons = {};
				buttons[closeButtonLabel] = function () {
					$(this).dialog('close');
				};



				buttons[saveButtonLabel] = function () {
					var enabled = $('select[name=rewards_enabled]').val() === '1';
					var formData = Object.fromEntries(
						$('#rewards_setup_form').serializeArray().map(item => [item.name, item.value])
					);

					showProgress(1);
					if ((isSuperUser == 1) || (rewardsEnableType === 'auto' && userHasDesignRights == 1)) {
						handleAutoRewardsEnable(formData, enabled);
					} else {
						handleManualRewardsRequest(formData);
					}
				};


				return buttons;
			}

			function handleAutoRewardsEnable(formData, enabled) {
				$.post(app_path_webroot + 'ProjectSetup/modify_project_setting_ajax.php?pid=' + pid,
					formData,
					function (data) {
						showProgress(0, 0);
						if (data === '0' || data === '') {
							alert(errorMessage);
						} else if (data === '1') {
							$('#RewardsEnableDialog').dialog('close');
							setTimeout(function () {
								if (enabled && globalRewardsEnabled !== '1') {
									window.location.href = app_path_webroot + page + "?pid=" + pid + "&msg=rewards_enabled&z=" + Math.random() + "#setupChklist-modules";
								} else {
									window.location.href = app_path_webroot + page + "?pid=" + pid + "&msg=projectmodified";
								}
							}, 200);
						} else {
							simpleDialog(data, globalErrorLabel, null, 550, "$('#RewardsEnableDialog').dialog('close');showProgress(1);dialogRewardsEnable();showProgress(0,0);");
						}
					}
				).fail(function () {
					showProgress(0, 0);
					alert(errorMessage);
				});
			}

			function handleManualRewardsRequest(formData) {
				formData['action'] = 'submit_request';
				$.post(app_path_webroot + 'ProjectSetup/modify_project_setting_ajax.php?pid=' + pid,
					formData,
					function (data) {
						showProgress(0, 0);
						$('#RewardsEnableDialog').dialog('close');

						if (data === '1') {
							simpleDialog(
								'<?= js_escape($lang['rewards_request_submitted'] ?? '') ?>',
								'<?= js_escape($lang['global_79']) ?>'
							);
							setTimeout(function () {
								window.location.href = app_path_webroot + page + "?pid=" + pid + "&msg=1&z=" + Math.random() + "#setupChklist-modules";
							}, 1000);
						} else {
							simpleDialog(
								'<?= js_escape($lang['rewards_request_failed'] ?? '') ?>',
								'<?= js_escape($lang['global_01']) ?>'
							);
						}
					}
				).fail(function () {
					showProgress(0, 0);
					alert(errorMessage);
				});
			}
		}

		</script>
		<?php
	}

	// Outputs drop-down of all text/textarea fields (except on first form) to choose Secondary Identifier field
	public static function renderSecondIdDropDown($id="", $name="", $outputToPage=true)
	{
		global $table_pk, $Proj, $secondary_pk, $lang, $surveys_enabled;
		// Set id and name
		$id   = (trim($id)   == "") ? "" : "id='$id'";
		$name = (trim($name) == "") ? "" : "name='$name'";
		// Staring building drop-down
		$html = "<select $id $name class='x-form-text x-form-field' style=''>
                    <option value=''>{$lang['edit_project_60']}</option>";
		// Get list of fields ONLY from follow up forms to add to Select list
		$followUpFldOptions = "";
		$sql = "select field_name, element_label from redcap_metadata where project_id = " . PROJECT_ID . "
                and field_name != concat(form_name,'_complete') and field_name != '$table_pk' 
                and (misc is null or (misc not like '%@CALCTEXT%' and misc not like '%@CALCDATE%'))
                and element_type = 'text' order by field_order";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			$this_field = $row['field_name'];
            // Set field label
			$this_label = "$this_field - " . strip_tags(br2nl(label_decode($row['element_label'], false)));
			// Ensure label is not too long
			if (mb_strlen($this_label) > 57) $this_label = mb_substr($this_label, 0, 40) . "..." . mb_substr($this_label, -15);
			// Add option
			$html .= "<option value='$this_field' " . ($this_field == $secondary_pk ? "selected" : "") . ">$this_label</option>";
		}
		// Finish drop-down
		$html .= "</select>";
		// Render or return
		if ($outputToPage) {
			print $html;
		} else {
			return $html;
		}
	}
}
