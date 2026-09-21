<?php

use Vanderbilt\REDCap\Classes\MyCap\Task;

/**
 * RepeatInstance Class
 */
class RepeatInstance
{	
	// Output HTML for setup table for repeat instances
	public static function renderSetup()
	{
		global $lang, $Proj, $longitudinal, $status, $enable_edit_prod_repeating_setup, $myCapProj;
		
		// Get array of repeating forms/events
		$RepeatingFormsEvents = $Proj->getRepeatingFormsEvents();

        $show_mycap_notice = false;

		// Get content for the setup table
		$row_data = $col_widths_headers = array();
		if ($longitudinal) 
		{
			// LONGITUDINAL
			// Each table row is an event
			foreach ($Proj->eventInfo as $this_event_id=>$attr) {
				// Is event already repeating?
				if (isset($RepeatingFormsEvents[$this_event_id])) {
					$selectedValue = is_array($RepeatingFormsEvents[$this_event_id]) ? 'PARTIAL' : 'WHOLE';
					$repeatFormsClass = ($selectedValue != 'WHOLE') ? '' : 'text-muted-more';
				} else {
					$selectedValue = '';
					$repeatFormsClass = 'text-muted-more';
				}
				// Build box of all forms designated for this event
				$eventForms = "";
				$checkboxDisabled = ($selectedValue == '' || $selectedValue == 'WHOLE') ? 'disabled' : '';
				$tickClass = ($selectedValue == '') ? 'hidden' : '';
				$textClass = ($selectedValue != '') ? 'text-success-more' : 'text-danger';
                $myCapDisableOptionClass = $checkedHiddenElement = '';
				if (isset($Proj->eventsForms[$this_event_id])) {
                    foreach ($Proj->eventsForms[$this_event_id] as $form) {
                        $disableBatteryForm = false;
                        $batteryInstrumentsList = Task::batteryInstrumentsInSeriesPositions();
	                    $isBatteryInstrument = array_key_exists($form, $batteryInstrumentsList);

                        $schedules = Task::getTaskSchedules($myCapProj->tasks[$form]['task_id']??"");
                        $checkboxMyCapDisabled = '';
                        if ($isBatteryInstrument && $batteryInstrumentsList[$form]['batteryPosition'] != '' && $batteryInstrumentsList[$form]['batteryPosition'] != '1') {
                            $firstInstrumentInSeries = $batteryInstrumentsList[$form]['firstInstrument'];

                            $schedules = Task::getTaskSchedules($myCapProj->tasks[$firstInstrumentInSeries]['task_id'], 'all');
                            $scheduledEvents = array_keys($schedules);
                            if (!in_array($this_event_id, $scheduledEvents)) {
                                $disableBatteryForm = true;
                            }
                        }

                        if (!empty($schedules)) {
                            if (!empty($schedules[$this_event_id])) {
                                $checkboxMyCapDisabled = 'disabled';
                                $myCapDisableOptionClass = " whole-option-disable not-repeating-option-disable";
                                $show_mycap_notice = true;
                                $checkedHiddenElement= RCView::hidden(array('name'=>"repeat_form-$this_event_id-$form", 'value'=>"on"));
                            }
                        } else if ($disableBatteryForm) {
                            $checkboxMyCapDisabled = 'disabled';
                            $myCapDisableOptionClass = " whole-option-disable not-repeating-option-disable";
                            $show_mycap_notice = true;
                            $checkedHiddenElement= RCView::hidden(array('name'=>"repeat_form-$this_event_id-$form", 'value'=>"on"));
                        }

						// Is instrument already repeating?
						$checked = (isset($RepeatingFormsEvents[$this_event_id][$form]) && is_array($RepeatingFormsEvents[$this_event_id]));
						$checkboxChecked = ($checked || $selectedValue == 'WHOLE') ? 'checked' : '';
						$customLabel = $checked ? filter_tags($RepeatingFormsEvents[$this_event_id][$form]) : '';
						// Build div for this form
						$eventForms .= RCView::div(array('class' => "clearfix"),
							RCView::div(array('class' => "repeat_event_form_div"),
								RCView::checkbox(array('name' => "repeat_form-$this_event_id-$form", 'class' => 'repeat_form_chkbox', $checkboxDisabled => $checkboxDisabled, $checkboxMyCapDisabled => $checkboxMyCapDisabled, $checkboxChecked => $checkboxChecked)) .
                                $checkedHiddenElement .
								RCView::span(array('style' => 'vertical-align:middle;'), strip_tags($Proj->forms[$form]['menu']))
							) .
							RCView::div(array('class' => "repeat_event_form_custom_label_div"),
								RCView::text(array('name' => "repeat_form_custom_label-$this_event_id-$form", 'value' => $customLabel, $checkboxDisabled => $checkboxDisabled, 'class' => 'x-form-text x-form-field'))
							)
						);
					}
				}
				// Add event to array
				$row_data[] = array(
								RCView::img(array('src'=>'tick.png', 'class'=>$tickClass)),
								RCView::div(array('class'=>"repeat_event_label wrap $textClass"), strip_tags($attr['name_ext'])), 
								RCView::select(array('name'=>"repeat_whole_event-$this_event_id", 'class'=>'x-form-text x-form-field repeat_select'.$myCapDisableOptionClass,
									'onchange'=>"showEventRepeatingForms(this,$this_event_id);"),
									array(''=>" ".$lang['setup_160']." ", 'WHOLE'=>$lang['setup_151'], 'PARTIAL'=>$lang['setup_152']), $selectedValue),
								RCView::div(array('class'=>"repeat_event_form_div_parent $repeatFormsClass"), $eventForms)
							);
			}
			// Set parameters for the setup table
			$col_widths_headers[] = array(20, '', 'center');
			$col_widths_headers[] = array(140, RCView::b($lang['global_10']));
			$col_widths_headers[] = array(200, RCView::div(array('class'=>'wrap', 'style'=>'font-weight:bold;padding: 5px 2px;'), $lang['setup_149']));
			$col_widths_headers[] = array(395, 
				RCView::div(array('class'=>"clearfix"),
					RCView::div(array('class'=>'float-start', 'style'=>'font-weight:bold;margin-top:11px;'),
						RCView::b($lang['design_244']) .
						RCView::div(array('style'=>'color:#888;margin:2px 0 1px;'), $lang['setup_164'])
					) .
					RCView::div(array('class'=>'float-end'),
						RCView::div(array('class'=>'wrap', 'style'=>'margin-top:4px;line-height:10px;'), 
							RCView::b($lang['setup_154'] . RCView::br() . $lang['setup_155']) .
							RCView::SP . $lang['survey_251'] . RCView::SP . 
							RCView::a(array('href'=>'javascript:;', 'title'=>$lang['form_renderer_02'], 'onclick'=>"simpleDialog('".js_escape($lang['setup_156'])."','".js_escape("{$lang['setup_154']} {$lang['setup_155']}")."')"), trim(RCView::img(array('src'=>'help.png'))))
						) . 
						RCView::div(array('style'=>'color:#888;margin:2px 0 1px;'), $lang['system_config_64']." [visit_date], [weight] kg")
					)
				));
			$width = 801;

			$text = $lang['setup_202'];
            $mycap_note = ($show_mycap_notice) ?  RCView::div(array('class'=>'p', 'style'=>'color:#C00000;margin-top:0;'), $text) : "";
		} 
		else 
		{
            global $mycap_enabled, $myCapProj;
            $batteryInstrumentsList = Task::batteryInstrumentsInSeriesPositions();
			// CLASSIC
			// Each table row is an instrument
			foreach ($Proj->forms as $form=>$attr) {
				// Is instrument already repeating?
				$checked = isset($RepeatingFormsEvents[$Proj->firstEventId][$form]);
				$checkboxChecked = $checked ? 'checked' : '';
				$textClass = $checked ? 'text-success-more' : 'text-danger';
				$customLabel = $checked ? filter_tags($RepeatingFormsEvents[$Proj->firstEventId][$form]) : '';
                if ($mycap_enabled && (isset($myCapProj->tasks[$form]['task_id']) || isset($myCapProj->tasks[$batteryInstrumentsList[$form]['firstInstrument']]['task_id']) ) && $checked) {
                    $show_mycap_notice = true;
                    $checkbox = RCView::checkbox(array('name'=>"repeat_form-{$Proj->firstEventId}-$form", 'class'=>'repeat_form_chkbox', $checkboxChecked=>$checkboxChecked, 'disabled'=>'disabled'));
                    $checkbox .= RCView::hidden(array('name'=>"repeat_form-{$Proj->firstEventId}-$form", 'value'=>"on"));
                } else {
                    $checkbox = RCView::checkbox(array('name'=>"repeat_form-{$Proj->firstEventId}-$form", 'onclick'=>"setRepeatingFormsLabel(this)", 'class'=>'repeat_form_chkbox', $checkboxChecked=>$checkboxChecked));
                }

				// Add instrument to array
				$row_data[] = array(
                                $checkbox,
								RCView::div(array('class'=>"repeat_event_label wrap $textClass"), strip_tags($attr['menu'])),
								RCView::text(array('name'=>"repeat_form_custom_label-{$Proj->firstEventId}-$form", 'value'=>$customLabel, 'class'=>'x-form-text x-form-field', 'style'=>'max-width:230px;width:98%;'))
							);
			}			
			// Set parameters for the setup table
			$col_widths_headers[] = array(70, RCView::div(array('class'=>'wrap', 'style'=>'line-height:12px;font-weight:bold;padding: 5px 0;'), $lang['setup_148']), 'center');
			$col_widths_headers[] = array(272, RCView::b($lang['design_244']));
			$col_widths_headers[] = array(273, 
				RCView::div(array('class'=>'wrap', 'style'=>'margin-top:4px;line-height:10px;'), 
					RCView::b($lang['setup_154'] . RCView::br() . $lang['setup_155']) .
					RCView::SP . $lang['survey_251'] . RCView::SP . 
					RCView::a(array('href'=>'javascript:;', 'title'=>$lang['form_renderer_02'], 'onclick'=>"simpleDialog('".js_escape($lang['setup_156'])."','".js_escape("{$lang['setup_154']} {$lang['setup_155']}")."')"), trim(RCView::img(array('src'=>'help.png'))))
				) . 
				RCView::div(array('style'=>'color:#888;margin:2px 0 1px;'), $lang['system_config_64']." [visit_date], [weight] kg")
			);
			$width = 644;
            $mycap_note = ($show_mycap_notice) ?  RCView::div(array('class'=>'p', 'style'=>'color:#C00000;margin-top:0;'), $lang['setup_199']) : "";
		}
		// Build the setup table
		$html = RCView::div(array('class'=>'p', 'style'=>'margin-top:0;'), $lang['setup_163']) .
				RCView::div(array('class'=>'p', 'style'=>'margin-top:0;'), ($longitudinal ? $lang['setup_159'] : $lang['setup_158'])) .
                $mycap_note.
				RCView::div(array('id'=>'repeat_instance_setup_parent'), 
					RCView::form(array('id'=>'repeat_instance_setup_form'),
						renderGrid("repeat_setup", '', $width, 'auto', $col_widths_headers, $row_data, true, false, false)
					)
				);		
		
		// If project is in prod, don't let normal users edit the settings if system-level setting prevents such
		if ($status > 0 && !SUPER_USER && !$enable_edit_prod_repeating_setup) {
			$html .= RCView::div(array('class'=>'p red'), $lang['system_config_582']);
		}
		
		// Output the HTML
		print $html;
	}
	
	// Save settings from setup table for repeat instances
	public static function saveSetup()
	{
		global $Proj, $longitudinal, $status, $enable_edit_prod_repeating_setup;
		// If project is in prod, don't let normal users edit the settings if system-level setting prevents such
		if ($status > 0 && !SUPER_USER && !$enable_edit_prod_repeating_setup) {
			exit('0');
		}
		// First, remove any rows that already exist already
		$sql_all[] = $sql = "delete from redcap_events_repeat where event_id in (".prep_implode(array_keys($Proj->eventInfo)).")";
		db_query($sql);
		// Loop through post data
		if ($longitudinal) {
			## LONGITUDINAL
			foreach ($_POST as $key=>$val) {
				// Make sure it starts with repeat-form
				$pos = strpos($key, "repeat_whole_event-");
				if ($pos !== 0) continue;
				// Get event_id and validate it
				$event_id = substr($key, 19);
				if (!isset($Proj->eventInfo[$event_id])) continue;
				// Make sure we only add the non-blank ones submitted
				if ($val != 'PARTIAL' && $val != 'WHOLE') continue;
				// Determine what to add
				if ($val == 'WHOLE') {
					// Add entire event (with null form_name)
					$sql_all[] = $sql = "insert into redcap_events_repeat (event_id, form_name) values ($event_id, null)";
					db_query($sql);
				} else {
					// Loop through all of this event's forms to see if they were submitted
					foreach ($Proj->eventsForms[$event_id] as $form) {
						// Was this form submitted?
						if (!isset($_POST["repeat_form-$event_id-$form"])) continue;
						// Get custom label, if has one
						$customLabel = (isset($_POST["repeat_form_custom_label-$event_id-$form"])) ? filter_tags($_POST["repeat_form_custom_label-$event_id-$form"]) : '';
						// Add event-form to table
						$sql_all[] = $sql = "insert into redcap_events_repeat (event_id, form_name, custom_repeat_form_label) 
											 values ($event_id, '".db_escape($form)."', ".checkNull($customLabel).")";
						db_query($sql);
					}
				}
			}
		} else {
			## CLASSIC
			foreach ($_POST as $key=>$val) {
				// Make sure it starts with repeat-form
				$pos = strpos($key, "repeat_form-");
				if ($pos !== 0) continue;
				// Get form_name and validate it
				list ($nothing, $event_id, $form) = explode("-", $key, 3);
				if (!isset($Proj->forms[$form])) continue;
				// Get custom label, if has one
				$customLabel = (isset($_POST["repeat_form_custom_label-{$Proj->firstEventId}-$form"])) ? filter_tags($_POST["repeat_form_custom_label-{$Proj->firstEventId}-$form"]) : '';
				// Add event-form to table
				$sql_all[] = $sql = "insert into redcap_events_repeat (event_id, form_name, custom_repeat_form_label) 
									 values ({$Proj->firstEventId}, '".db_escape($form)."', ".checkNull($customLabel).")";
				db_query($sql);
			}
		}
		// Logging
		if (!empty($sql_all)) {
			Logging::logEvent(implode(";\n", $sql_all),"redcap_events_repeat","MANAGE",PROJECT_ID,"","Set up repeating instruments".($longitudinal ? "/events" : ""));
		}
		// Reset the array in $Proj so that it gets regenerated
		$Proj->RepeatingFormsEvents = null;
	}
	
	
	// Return name of form status icon (xx.png) based on form status value (0-4, 3=partial survey, 4=completed survey)
	public static function getStatusIcon($form_status)
	{
		switch ($form_status) {
			case '0':
				$status_icon = 'circle_red.png';
				break;
			case '1':
				$status_icon = 'circle_yellow.png';
				break;
			case '2':
				$status_icon = 'circle_green.png';
				break;
			case '3':
				$status_icon = 'circle_orange_tick.png';
				break;
			case '4':
				$status_icon = 'circle_green_tick.png';
				break;
			default:
				$status_icon = 'circle_gray.png';
		}
		return $status_icon;
	}


	// Return the ALT text of form status icon (xx.png) based on form status value (0-4, 3=partial survey, 4=completed survey)
	public static function getStatusIconAltText($form_status)
	{
		global $lang;
		switch ($form_status) {
			case '0':
				$this_alt = $lang['global_92'];
				break;
			case '1':
				$this_alt = $lang['global_93'];
				break;
			case '2':
				$this_alt = $lang['survey_28'];
				break;
			case '3':
				$this_alt = $lang['global_95'];
				break;
			case '4':
				$this_alt = $lang['global_94'];
				break;
			default:
				$this_alt = $lang['global_92'] . " " . $lang['data_entry_205'];
		}
		return $this_alt;
	}
	
	
	// Display all repeating forms tables for a given record OR just a single one if provide $form_name
	public static function renderRepeatingFormsDataTables($record, $grid_form_status=array(), $locked_forms=array(), 
														  $single_form_name=null, $single_event_id=null, $forceDisplayTable=false, $displayCloseIcon=false)
	{
		global $Proj, $lang, $user_rights, $multiple_arms;
		$forceDisplayTable = ($forceDisplayTable === true || $forceDisplayTable == '1');
		// HTML to return
		$html = "";
		// If project has no repeating forms, then return nothing
		if (!$Proj->hasRepeatingFormsEvents()) return $html;
		// Gather field names of all custom form labels (if any)
		$custom_form_labels_all = "";
        if ($single_event_id != null && $Proj->isRepeatingEvent($single_event_id) && trim($Proj->eventInfo[$single_event_id]['custom_event_label']) != "") {
            $custom_form_labels_all .= " " . $Proj->eventInfo[$single_event_id]['custom_event_label'];
        }
		foreach ($Proj->RepeatingFormsEvents as $event_id=>$forms) {
			if (!is_array($forms)) continue;
			foreach ($forms as $form=>$formLabel) {
				// Get collapsed state of the event grid and set TR class for each row
				$tableId = "repeat_instrument_table-$event_id-$form";
				$tableIsCollapsed = UIState::isTableCollapsed($Proj->project_id, $tableId);
				$tableIds[$tableId] = $tableIsCollapsed;
				if (!$tableIsCollapsed || $forceDisplayTable) $custom_form_labels_all .= " $formLabel"; // Don't try to pull piping data for this repeating instrument if it's collapsed
			}
		}
		// If returning a single repeating event, then replace WHOLE with array of event's forms
		$returnRepeatingEvent = (!empty($single_event_id) && $Proj->isRepeatingEvent($single_event_id));
		$RepeatingFormsEvents = array();
		if ($returnRepeatingEvent) {
			foreach ($Proj->eventsForms[$single_event_id] as $form) {
				$RepeatingFormsEvents[$single_event_id][$form] = "";
			}
		} else {
			$RepeatingFormsEvents = $Proj->RepeatingFormsEvents;
		}
		// Get arm
		if ($multiple_arms) {
			$arm = isset($_GET['arm']) ? $_GET['arm'] : $Proj->eventInfo[$single_event_id]['arm_num'];
		}
		// Loop through all repeating forms and build each as a table
		foreach (array_keys($Proj->eventInfo) as $event_id) 
		{
			if (!isset($RepeatingFormsEvents[$event_id])) continue;
			// If returning only a single form/event_id, if this is not that form, then skip
			if (!empty($single_event_id) && $single_event_id != $event_id) continue;
			// Set forms
			$forms = $RepeatingFormsEvents[$event_id];
			// If this is an entire repeating event, then skip it
			if (!is_array($forms)) continue;		
			// If longitudinal with multiple arms, ignore events on the other arms
			if ($multiple_arms && (!isset($Proj->events[$arm]['events'][$event_id]) ||
				(!empty($single_event_id) && !isset($Proj->events[$arm]['events'][$single_event_id])))) {
				continue;
			}
			// When there are no forms on this event, skip it
			if (!array_key_exists($event_id, $Proj->eventsForms)) continue;
			// Loop through forms
			foreach (array_keys($forms) as $form) 
			{
				// If returning only a single form, if this is not that form, then skip
				if (!empty($single_form_name) && $single_form_name != $form) continue;
				// Do not display this form is the user does not have data entry rights to it
				if (UserRights::hasDataViewingRights($user_rights['forms'][$form], "no-access")) continue;
				// Get FDL state
				$fdl_access = FormDisplayLogic::checkFormAccess($Proj->project_id, $record, $event_id, $form) === true;
				// When disabled by FDL and FDL is set to hide disabled forms, then skip this form
				if (!$fdl_access && FormDisplayLogic::isSetHideDisabledForms($Proj->project_id)) continue;
				$add_new_url = Form::getAddNewFormInstanceUrl($Proj->project_id, $record, $event_id, $form);
				$add_new_enabled = FormDisplayLogic::checkAddNewRepeatingFormInstanceAllowed($Proj->project_id, $record, $event_id, $form);

				// Get collapsed state of the event grid and set TR class for each row
				$tableId = "repeat_instrument_table-$event_id-$form";
				$tableIsCollapsed = $forceDisplayTable ? false : $tableIds[$tableId];
				$tableIsCollapsed ? 'hidden' : '';

				$repeat_data = Form::getInstanceSelectorContent($record, $form, $event_id, $tableIsCollapsed, true);
				$instanceCountDisplay = RCView::span(["class"=>"rc-d-instance-count"],
					count($repeat_data["data"]) ? ("(" . count($repeat_data["data"]) . ")") : "&nbsp;");

				// Not collapsed and no instance data? Then skip
				if (!$tableIsCollapsed && count($repeat_data["data"]) == 0) continue;

				// Render container with head and body template
				$info_event_name = !$Proj->longitudinal ? "" 
					: RCView::div([
							"class" => "rc-d-event-name",
							"data-mlm" => "",
							"data-mlm-name" => $event_id,
							"data-mlm-type" => "event-name"
						], RCView::escape($Proj->eventInfo[$event_id]['name_ext'])
					);
				$containerDiv = RCView::div([
						"id" => "$tableId-container",
						"class" => "rc-rhp-repeat-instrument-container rc-instance-selector"
					],
					RCView::div(["class" => "rc-rhp-repeat-instrument-container-head"], 
						RCView::div(["class" => "rc-rhp-repeat-instrument-container-info"], 
							RCView::span([
								"data-mlm" => "",
								"data-mlm-name" => $form,
								"data-mlm-type" => "form-name",
							], RCView::escape($Proj->forms[$form]['menu'])) .
							$info_event_name .
							$instanceCountDisplay
						) .
						RCView::div(["class" => "rc-rhp-repeat-instrument-container-buttons nowrap"],
							RCView::button([
								"type" => "button",
								"class" => "btn btn-xs btn-defaultrc rc-rhp-plus-btn" . ($add_new_enabled ? "" : " rc-form-menu-fdl-disabled"),
								"onclick" => "window.location.href='" . $add_new_url . "';",
							],
								RCView::tt("grid_64")
							) .
							RCView::button([
									"type" => "button",
									"class" => "btn btn-xs".($tableIsCollapsed ? " btn-primaryrc" : " btn-defaultrc"),
									"data-rc-collapse" => "$tableId-body",
									"data-rc-collapsed" => $tableIsCollapsed ? "1" : "0",
									"title" => "Collapse/uncollapse table"
								], 
								RCIcon::CollapseUp("rc-collapse") .
								RCIcon::UncollapseDown("rc-uncollapse")
							)
						)
					) .
					RCView::div([
							"id" => "$tableId-body",
							"class" => "rc-rhp-repeat-instrument-container-body",
							"style" => $tableIsCollapsed ? "display: none;" : ""
						], $repeat_data["body"])
				);

				// Add data to container
				$containerDiv .= RCView::script("$('#$tableId-container').data('response', " . json_encode($repeat_data) . ");");

				$html .= $containerDiv;
			}
		}
		// Return single form html
		if (!empty($single_form_name)) return $html;
		// Add div wrapper
		$title = '';
		if ($html != '') {
			$title = RCView::div(array('id'=>'repeating_forms_table_parent_title'), 
						RCView::tt("grid_45") .
						RCView::help(RCView::tt("grid_45"), RCView::tt("data_entry_681")) . 
						RCView::a(array('id'=>'recordhome-uncollapse-all', 'class'=>'nowrap opacity65', 'href'=>'javascript:;'), $lang['grid_50'])
					);
		}
		$html = $title .
				RCView::div(array('id'=>'repeating_forms_table_parent', 'class'=>'rc-rhp-repeat-insturments-container'), 
					$html
				);
		// Call JS to setup form instance tables
		$html .= RCView::script("setupFormInstanceTables();");
		// Return html
		return $html;
	}
	
	
	// Retrieve the Custom Repeating Form Labels (for repeating instruments) with data piped in for one or more records on specified event/form.
	// Return array with record name as key, instance # as sub-array key with piped data as sub-array value.
	// If Custom Repeating Form Labels do not exist for this form, then return empty array.
	public static function getPipedCustomRepeatingFormLabels($records, $event_id, $form_name)
	{
		global $Proj;
		$pipedFormLabels = array();
		// If not a repeating form, then return empty array
		if (!$Proj->isRepeatingForm($event_id, $form_name)) return array();
		// Return empty array if there's nothing to pipe
		if (trim($Proj->RepeatingFormsEvents[$event_id][$form_name]) == "") return array();
		// Gather field names of all custom form labels (if any)
		$pre_piped_label = $Proj->RepeatingFormsEvents[$event_id][$form_name];
		$custom_form_label_fields = array_keys(getBracketedFields($pre_piped_label, true, false, true));
		// Get piping data for this record
		$piping_data = Records::getData('array', $records, $custom_form_label_fields, array_keys($Proj->RepeatingFormsEvents));
		// Loop through records/instances and add as piped to $pipedFormLabels
		foreach ($piping_data as $record=>&$attr) {
			// Add first instance
			if (isset($attr[$event_id])) {
				$pipedFormLabels[$record][1] = trim(Piping::replaceVariablesInLabel($pre_piped_label, $record, $event_id, 1, $piping_data, false, null, false, $form_name, 1, false, false, $form_name));
			}
			// Add other instance
			if (isset($attr['repeat_instances'][$event_id][$form_name])) {
				// Loop through instances
				foreach (array_keys($attr['repeat_instances'][$event_id][$form_name]) as $instance) {
					$pipedLabel = trim(Piping::replaceVariablesInLabel($pre_piped_label, $record, $event_id, $instance, $piping_data, false, null, false, $form_name, 1, false, false, $form_name));
					// Only add piped string if non-blank
					if ($pipedLabel != "") $pipedFormLabels[$record][$instance] = $pipedLabel;
				}
			}
		}
		// Return the array containing the piped repeating form labels
		return $pipedFormLabels;		
	}


	// Return the next projected repeating instance number for a record/event/form (if form is blank, assume it's a repeating event)
	public static function getNextRepeatingInstance($project_id, $record, $event_id, $form)
	{
		$sql = "select max(ifnull(instance, 1))
				from ".\Records::getDataTable($project_id)." where project_id = $project_id
				and record = '" . db_escape($record) . "' and event_id = $event_id";
		if ($form != "") $sql .=" and field_name = '{$form}_complete'";
		$q = db_query($sql);
		$max_instance = db_result($q, 0);
		if ($max_instance == "") $max_instance = 0;
		return ($max_instance+1);
	}


    // Return only the MAX INSTANCE NUMBER for saved "instances" for a given record-event-form
    public static function getRepeatFormInstanceMaxCountOnly($record, $event_id, $form, $Proj)
    {
        if ($event_id == '' || $form == '') return 0;
        $sql = "select ifnull(max(ifnull(d.instance, 1)),0)
				from ".\Records::getDataTable($Proj->project_id)." d where d.project_id = ".$Proj->project_id."
				and d.record = '" . db_escape($record) . "' and d.event_id = $event_id 
				and d.field_name = '{$form}_complete'";
        $q = db_query($sql);
       return $q ? db_result($q, 0) : 0;
    }


	// Return both the max instance number and the total count of saved "instances" for a given record-event-form.
	// Note: The max and total might be different.
	// Returns array(TOTAL INSTANCES, MAX INSTANCE NUMBER)
	public static function getRepeatFormInstanceMaxCount($record, $event_id, $form, $Proj)
	{
		$instanceList = self::getRepeatFormInstanceList($record, $event_id, $form, $Proj, false);
        $instanceListCount = count($instanceList);
        $instanceListMax = $instanceListCount > 0 ? max(array_keys($instanceList)) : 0;
		return array($instanceListCount, $instanceListMax);
	}


	// Return array of "instance" numbers from the data table for a given record-event-form
	public static function getRepeatFormInstanceList($record, $event_id, $form, $Proj, $checkSurveys=true)
	{
		// First, retrieve form status values
		$instances = array();
		$sql = "select ifnull(d.instance, 1) as instance, d.value 
				from ".\Records::getDataTable($Proj->project_id)." d where d.project_id = ".$Proj->project_id."
				and d.record = '" . db_escape($record) . "' and d.event_id = $event_id 
				and d.field_name = '{$form}_complete'";
		$q = db_query($sql);

        while ($row = db_fetch_assoc($q)) {
			$instances[$row['instance']] = $row['value'];
		}
		// Surveys only: Query to get unique instance numbers from response table
		if ($checkSurveys && isset($Proj->forms[$form]['survey_id']))
		{
			// Since this is a survey, additionally retrieve survey completion status
			$sql = "select r.instance, max(r.first_submit_time) as first_submit_time, max(r.completion_time) as completion_time 
					from redcap_surveys_participants p, redcap_surveys_response r
					where p.survey_id = {$Proj->forms[$form]['survey_id']} and p.event_id = $event_id
					and r.record = '" . db_escape($record) . "' and r.participant_id = p.participant_id
					group by r.instance";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// Partial survey response = 3
				if ($row['first_submit_time'] != '' && $row['completion_time'] == '') {
					$instances[$row['instance']] = '3';
				}
				// Completed survey response = 4
				elseif ($row['completion_time'] != '') {
					$instances[$row['instance']] = '4';
				}
			}
		}
		// Sort array by instance
		ksort($instances);
		// Return array
		return $instances;
	}

	/**
	 * Check if the given repeating form instance exists
	 * @param string|int|null $project_id 
	 * @param string $record 
	 * @param string|int $event_id 
	 * @param string $form 
	 * @param int $instance 
	 * @return bool 
	 * @throws Exception 
	 */
	public static function checkRepeatFormInstanceExists($project_id, $record, $event_id, $form, $instance) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		$datatable = \Records::getDataTable($project_id);
		$instance = max(intval($instance), 1);
		$instance_clause = ($instance == 1) ? "instance IS NULL" : "instance = $instance";
		$sql = "SELECT 1 FROM $datatable WHERE project_id = ? AND record = ? AND event_id = ? AND field_name = ? AND $instance_clause";
		$q = db_query($sql, [$project_id, $record, $event_id, "{$form}_complete"]);
		return db_num_rows($q) > 0;
	}

	// Return array of "instance" numbers from the data table for a given record-event
	public static function getRepeatEventInstanceList($record, $event_id, $Proj)
	{
		$sql = "select distinct if (instance is null, 1, instance) as instance
				from ".\Records::getDataTable($Proj->project_id)." where project_id = ".$Proj->project_id." and event_id = ".$event_id."
				and record = '" . db_escape($record) . "'";
		$q = db_query($sql);
		$instances = array();
		while ($row = db_fetch_assoc($q)) {
			$instances[$row['instance']] = '1';
		}
		// Sort array by instance
		ksort($instances);
		// Return array
		return $instances;
	}


	// Return array of "instance" numbers from the data table for a given record-field, in which the array key is
	// the event_id and the sub-array values are the instance numbers (NOTE: This will NOT distinguish between repeating forms vs events.)
	public static function getRepeatInstanceEventsForField($project_id, $record, $field)
	{
		$sql = "select if (instance is null, 1, instance) as instance, event_id 
				from ".\Records::getDataTable($project_id)." where project_id = ".$project_id."
				and record = '" . db_escape($record) . "' and field_name = '" . db_escape($field) . "'";
		$q = db_query($sql);
		$instances = array();
		while ($row = db_fetch_assoc($q)) {
			$instances[$row['event_id']][] = $row['instance'];
		}
		// Return array
		return $instances;
	}
	
}