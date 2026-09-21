<?php

/**
 * FormDisplayLogic Class
 * Contains methods used with regard to setup for Project setup::form render skip logic
 */
class FormDisplayLogic
{
	// Display the Conditional Form Activation setup table in HTML table format
	public static function displayFormDisplayLogicTable()
	{
		global $lang, $Proj, $longitudinal;

		// Instructions
		$html = RCView::div(array('style' => 'margin:0 0 5px;'),
			RCView::tt('design_964') . " " . RCView::tt('design_1112')
		);

		// Enable for Survey auto-continue checkbox selection
		$autoContinueOption = '';
		if ($Proj->project['surveys_enabled']) {
            $autoContinueOption .=  RCView::div(array('class' => 'mb-2'),
					RCView::checkbox(array('id' => 'enable_survey_support', 'name' => 'enable_survey_support', 'checked' => '', 'style' => 'position:relative;top:2px;margin-right:3px;')) .
					RCView::label(array('for' => 'enable_survey_support', 'style' => 'color:#A00000;', 'class' => 'boldish me-2 mb-0'), $lang['design_973']) .
					RCView::div(array('class' => 'fs12 ps-4', 'style' => 'margin-top:2px;line-height: 1.1;'), $lang['design_974'])
			);
		}

        // Enable for MyCap tasks checkbox selection
        $myCapOption = '';
        global $mycap_enabled_global;
        if ($mycap_enabled_global == 1 && $Proj->project['mycap_enabled']) {
            $myCapOption .=  RCView::div(array(),
                RCView::checkbox(array('id' => 'enable_mycap_support', 'name' => 'enable_mycap_support', 'checked' => '', 'style' => 'position:relative;top:2px;margin-right:3px;')) .
                RCView::label(array('for' => 'enable_mycap_support', 'style' => 'color:#A00000;', 'class' => 'boldish me-2 mb-0'), RCView::tt('design_1390')) .
                RCView::div(array('class' => 'fs12 ps-4', 'style' => 'margin-top:2px;line-height: 1.1;'), RCView::tt('design_1388')));
        }

		$html .= RCView::div(array('class' => 'data mt-3 mb-4 px-3 pt-1 pb-2', 'style' => 'background:#f8f8f8;'),
					RCView::div(array('class' => 'mb-2'),
						$lang['design_984']
					) .
					// Prevent hiding of filled forms? checkbox selection
					RCView::div(array('class' => 'mb-2'),
						RCView::checkbox(array('id' => 'prevent_hiding_filled_forms', 'name' => 'prevent_hiding_filled_forms', 'checked' => '', 'style' => 'position:relative;top:2px;margin-right:3px;')) .
						RCView::label(array('for' => 'prevent_hiding_filled_forms', 'style' => 'color:#A00000;', 'class' => 'boldish me-2 mb-0'), $lang['design_965']) .
						RCView::div(array('class' => 'fs12 ps-4', 'style' => 'margin-top:2px;line-height: 1.1;'), $lang['design_966'])
					) .
					// Option to hide any disabled forms from the data collection menu and record home page
					RCView::div(array('class' => 'mb-2'),
						RCView::checkbox(array('id' => 'hide_disabled_forms', 'name' => 'hide_disabled_forms', 'checked' => '', 'style' => 'position:relative;top:2px;margin-right:3px;')) .
						RCView::label(array('for' => 'hide_disabled_forms', 'style' => 'color:#A00000;', 'class' => 'boldish me-2 mb-0'), $lang['design_1064']) .
						RCView::div(array('class' => 'fs12 ps-4', 'style' => 'margin-top:2px;line-height: 1.1;'), $lang['design_1065'])
					) .
					$autoContinueOption .
                    $myCapOption
				);

		$html .= RCView::hidden(array('name' => 'control_id', 'id' => 'control_id'));

		// HTML for form-event drop-down list
		$formAllEventDropdownOptions = [];
		$formEventDropdownOptions = [];
		foreach ($Proj->eventsForms as $this_event_id=>$these_forms)
		{
			foreach ($these_forms as $this_form)
			{
				if ($longitudinal) {
					if (!isset($formEventDropdownOptions["$this_form-"])) {
						$formAllEventDropdownOptions[$lang['design_983']]["$this_form-"] = "{$Proj->forms[$this_form]['menu']} ".$lang['design_983'];
					}
					$thisEvent = $Proj->eventInfo[$this_event_id]['name_ext'];
					$formEventDropdownOptions["$thisEvent"]["$this_form-$this_event_id"] = "{$Proj->forms[$this_form]['menu']} ($thisEvent)";
				} else {
					$formEventDropdownOptions["$this_form-$this_event_id"] = "{$Proj->forms[$this_form]['menu']}";
				}
			}
		}

		ob_start();
		?>
		<style type="text/css">
			.code_modal_table{
				width: 100%;
			}
			/***MODAL***/
			.code_modal_table{
				margin: 0 auto;
			}
			.form-control-custom textarea{
				display: block;
				width: 100%;
				height: 32px;
				padding: 4px 8px;
				font-size: 13px;
				line-height: 1.42857143;
				color: #555;
				background-color: #fff;
				background-image: none;
				border: 1px solid #ccc;
				border-radius: 4px;
				-webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
				box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
				-webkit-transition: border-color ease-in-out .15s,-webkit-box-shadow ease-in-out .15s;
				-o-transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
				transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
			}
			.form-control-custom textarea{
				height: 100%;
			}
		</style>
		<form id="FRSLForm" class="repeater">
			<div data-repeater-list="outer-list">
				<div data-repeater-item class="repeater-divs" style="overflow:hidden;color:#A00000;background-color:#f7f7f7;border:1px solid #ddd;margin:10px 0 30px;" >
					<table class="code_modal_table" id="code_modal_table_update">
						<input type="hidden" name="control_id" id="control_id" value="">
						<tr>
							<td class="labelrc" colspan="2" height="30px;" style="border: none; border: 1px solid #bbb;color:#000;background:#d0d0d0;">
								<i class="fas fa-filter"></i> <?php echo $lang['design_975']; ?> <span class="condition-number">1</span>:
								<div style="float: right;">
									<a data-repeater-delete class="survey_auth_field_delete" href="javascript:;" style="margin-left: 5px;">
										<img src="<?php echo APP_PATH_IMAGES;?>cross.png" title="<?php echo $lang['data_entry_369']; ?>">
									</a>
								</div>
							</td>
						</tr>
						 <tr>
							<td class="pb-1 boldish align-top" style="padding: 10px;">
								<div class="mt-1 mb-2 boldish"><?=$lang['design_976']?></div>
								<?=RCView::select(array('name'=>"form-name",'class'=>'x-form-text x-form-field d-inline p-1 select-form-event', 'style'=>'min-width:300px;max-width:450px;height:150px;max-height:400px;',
									'multiple'=>'multiple', 'onchange'=>'checkRepeatSelection(this);'), $formAllEventDropdownOptions+$formEventDropdownOptions, "", 500)?>
								<div class="cc_info">
									<div class="float-start"><?php echo $lang['design_969']; ?></div>
									<div class="float-end me-3"><button class="btn btn-xs btn-link fs11 p-0" onclick="viewSelectedFormDisplayLogicList(this);return false;"><?php echo $lang['design_992']; ?></button></div>
								</div>
							</td>
							 <td class="pb-0 ps-3 align-top" style="padding: 10px;" field="control-condition">
								 <div class="mt-1 mb-2 boldish"><?=$lang['design_970']?></div>
								 <textarea class="x-form-text x-form-field notesbox fs12" type="text" id="control-condition-1" name="control-condition" onfocus="openLogicEditor($(this), false, () => logicValidate($(this), false, 1))" class="external-modules-input-element ms-4" style="max-width:95%;height:100px;width:360px;" onkeydown="logicSuggestSearchTip(this, event);"></textarea>
								 <div class="clearfix" style="font-size:11px;color:#777;font-weight:normal;margin-top:2px;margin-left:2px;margin-right:85px;">
									 <?php
									 echo "<div class='float-start'>" . ($Proj->longitudinal ? 'e.g., [enrollment_arm_1][age] > 30' : 'e.g., [age] > 30 and [sex] = "1"') . "</div>";
									 echo '<div class="float-end"><a href="javascript:;" class="opacity75" style="text-decoration:underline;font-size:11px;font-weight:normal;" onclick="helpPopup(\'5\',\'category_33_question_1_tab_5\')";">'.$lang['form_renderer_33'].'</a></div>';
									 ?>
								 </div>
								 <div id='control-condition_Ok' class='logicValidatorOkay logicEditorDoNotHide fs13'></div>
								 <div id='LSC_id_control-condition' class='fs-item-parent fs-item LSC-element'></div>
							 </td>
						</tr>
					</table>
				</div>
			</div>
			<div class="float-end ms-3">
				<button class="btn btn-xs btn-link text-danger" id="deleteAll" onclick="delete_conditions();return false;">
					<i class="fas fa-times"></i>
					<?php echo $lang['design_977']; ?>
				</button>
			</div>
			<div class="float-end">
				<a data-repeater-create class="add-control-field" href="javascript:;" style="font-weight: normal; color: green; font-size: 12px; text-decoration: underline;">
					<button class="btn btn-xs btn-rcgreen fs14" onclick="return false;"><i class="fas fa-plus"></i> <?php echo $lang['design_968']; ?></button>
				</a>
			</div>
		</form>
		<?php
		$html .= ob_get_clean();

		// Return all html to display
		return $html;
	}

	// Return boolean if enabled in a project
	public static function FormDisplayLogicEnabled($projectId)
	{
		$result = FormDisplayLogic::getFormDisplayLogicTableValues($projectId);
		return !empty($result['controls']);
	}

	// Return JSON string of FDL values to be utilized by the REDCap Mobile App
	public static function outputFormDisplayLogicForMobileApp($project_id)
	{
		$Proj = new Project($project_id);
		$settings = self::getFormDisplayLogicTableValues($project_id);
		$config = [
				'prevent_hiding_filled_forms'=>$settings['prevent_hiding_filled_forms'],
				'hide_disabled_forms'=>$settings['hide_disabled_forms'],
				'conditions'=>[]
		];
		foreach ($settings['controls'] as $attr) {
			$targets = [];
			foreach ($attr['form-name'] as $formEventId) {
				list ($form, $event_id) = explode("-", $formEventId, 2);
				if (!isset($Proj->forms[$form])) continue;
				if (isinteger($event_id) && !isset($Proj->eventInfo[$event_id])) continue;
				if (isinteger($event_id)) {
					$event_name = $Proj->getUniqueEventNames($event_id);
				} else {
					$event_name = '';
				}
				$targets[] = ['form'=>$form, 'event'=>$event_name];
			}
			$config['conditions'][] = ['condition'=>$attr['control-condition'], 'targets'=>$targets];
		}
		return $config;
	}

	// Obtain the values stored in database for project
	private static $formActivationConditions = [];
	public static function getFormDisplayLogicTableValues($projectId = null)
	{
		if (is_null($projectId)) {
			global $Proj;
		} else {
			$Proj = new Project($projectId);
		}
		// Add to cache if not cached yet
		if (!isset(self::$formActivationConditions[$Proj->project_id]))
		{
			// Get initial values
			$output = [ 'prevent_hiding_filled_forms' => ($Proj->project['hide_filled_forms'] ? 0 : 1),
						'hide_disabled_forms' => ($Proj->project['hide_disabled_forms'] ? 1 : 0),
						'enable_survey_support' => $Proj->project['form_activation_survey_autocontinue'],
                        'enable_mycap_support' => $Proj->project['form_activation_mycap_support'] ?? 0,
						'controls' => self::getControlsByProjectId($Proj->project_id) ];
			// Get list of all forms and events targeted
			$forms_targeted = [];
			$events_targeted = [];
			foreach ($output['controls'] as $key=>$attr) {
				foreach ($attr['form-name'] as $thisFormEvent) {
					list ($form, $event_id) = explode("-", $thisFormEvent, 2);
					$forms_targeted[] = $form;
					if (!$Proj->longitudinal) {
						$events_targeted[] = $Proj->firstEventId;
					} elseif ($event_id == '') {
						// Add all events for which this form is designated
						foreach ($Proj->eventsForms as $form_event_id=>$forms) {
							if (in_array($form, $forms)) {
								$events_targeted[] = $form_event_id;
							}
						}
					} else {
						$events_targeted[] = $event_id;
					}
				}
			}
			$output['forms_targeted'] = array_unique($forms_targeted);
			$output['events_targeted'] = array_unique($events_targeted);
			// Add to static var
			self::$formActivationConditions[$Proj->project_id] = $output;
		}
		return self::$formActivationConditions[$Proj->project_id];
	}

	public static function getControlsByProjectId($projectId)
	{
		$controls = $output = [];
		$sql = "SELECT control_id, control_condition FROM redcap_form_display_logic_conditions 
				WHERE project_id = $projectId order by control_id";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			while ($row = db_fetch_assoc($q)) {
				$control_id = $row['control_id'];
				$controls['control_id'] = $control_id;
				$controls['control-condition'] = $row['control_condition'];
				$controls['form-name'] = self::getAllTargetFormsByControlId($control_id, $projectId);
				$output[] = $controls;
			}
		}
		// Return array
		return $output;
	}

	public static function getAllTargetFormsByControlId($control_id, $projectId)
	{
		$Proj = new Project($projectId);
		$forms = [];
		$sql = "SELECT * FROM redcap_form_display_logic_targets WHERE control_id ='".$control_id."'";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			while ($row = db_fetch_assoc($q)) {
				if ($row['event_id'] == '' && !$Proj->longitudinal) {
					$row['event_id'] = $Proj->firstEventId;
				}
				$forms[] = $row['form_name']."-".$row['event_id'];
			}
		}
		return $forms;
	}


	#region Revised FDL Handling

	/**
	 * Checks whether Form Display Logic is enabled in a project
	 * @param string|int|null $project_id 
	 * @return bool
	 * @throws Exception When no valid project context
	 */
	public static function isEnabled($project_id = null) {
		list ($Proj, $projectId) = Project::requireProject($project_id);
		if (array_key_exists($projectId, self::$fdlEnabledCache)) {
			return self::$fdlEnabledCache[$projectId];
		}
		// FDL is enabled when there exists at least one condition
		$sql = "SELECT 1 FROM redcap_form_display_logic_conditions WHERE project_id = ? LIMIT 1";
		$q = db_query($sql, [$project_id]);
		self::$fdlEnabledCache[$projectId] = (db_num_rows($q) > 0);
		return self::$fdlEnabledCache[$projectId];
	}
	/**
	 * Cache for whether FDL is enabled in projects
	 * @var array
	 */
	private static $fdlEnabledCache = [];

	/**
	 * Clears all (revised) Form Display Logic caches
	 * @return void 
	 */
	public static function clearCache() {
		self::$fdlEnabledCache = [];
		self::$utilizedFieldsCache = [];
		self::$dataCache = [];
		self::$formDisplayRulesCache = [];
		self::$andedFormDisplayRulesCache = [];
		self::$eventFormsStateCache = [];
	}

	/**
	 * Loads record data (if not already cached)
	 * @param string|int|null $project_id
	 * @param string|string[] $records 
	 * @return bool Returns false when Form Display Logic is disabled, true otherwise
	 * @throws Exception When no valid project context
	 */
	public static function loadRecordData($project_id = null, $records = []) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		// Enabled?
		if (!self::isEnabled($project_id)) return false;
		// Disable FDL if Draft Preview Mode is enabled
		if (Design::isDraftPreview($project_id)) return false;
		// Get FDL rules - if there aren't any, then there is no point in caching
		$rules = self::getFormDisplayLogicTableValues($project_id);
		if (empty($rules)) return false;
		// Normalize records
		if (is_array($records) && empty($records)) return;
		if (!is_array($records)) $records = [$records];
		// Skip records if already cached
		$records = array_filter($records, function ($record) use ($project_id) {
			return !isset(self::$dataCache[$project_id][$record]);
		});
		// Anything to do?
		if (empty($records)) return;
		$fields = self::getFieldsUtilizedInConditions($project_id);
		// Get data for these fields/records and add to cached data
		$getDataParams = [
			'project_id' => $project_id,
			'records' => $records,
			'fields' => $fields,
			'returnEmptyEvents' => true,
			'decimalCharacter' => '.',
			'returnBlankForGrayFormStatus' => true,
		];
		$data = count($fields) ? Records::getData($getDataParams) : [];
		// Newly created record? If so, $_GET must have pid, id
		// If all checks out, we add an empty record
		if (count($fields) == 0 || (empty($data) && count($records) == 1 && $_GET["pid"] == $project_id && $_GET["id"] == $records[0])) {
			$new_data = array_combine($fields, array_fill(0, count($fields), ''));
			$data[$records[0]] = array_combine(array_keys($Proj->eventInfo), array_fill(0, count($Proj->eventInfo), $new_data));
		}
		foreach ($data as $record => $record_data) {
			self::$dataCache[$project_id][$record] = $record_data;
		}
		return true;
	}
	/**
	 * Cache for loadRecordData() and getRecordData(), getCachedRecordIds()
	 * Cachees all record data for relevant fields being evaluated in the conditions
	 * @var array
	 */
	private static $dataCache = [];

	/**
	 * Gets the (cached) data for a record
	 * @param string|int|null $project_id 
	 * @param string $record 
	 * @return array|null|false Returns record data, or null if record not found or false if FDL is disabled
	 * @throws Exception When no valid project context
	 */
	public static function getRecordData($project_id, $record) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		// Ensure the record is in the cache
		$disabled = self::loadRecordData($project_id, [$record]) === false;
		if ($disabled) return false;
		$record_data = self::$dataCache[$project_id][$record] ?? null;
		return $record_data ? [$record => $record_data] : null;
	}

	/**
	 * Gets the list of cached record ids
	 * @param string|int|null $project_id 
	 * @return string[] 
	 * @throws Exception 
	 */
	public static function getCachedRecordIds($project_id = null) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		return array_keys(self::$dataCache[$project_id] ?? []);
	}

	/**
	 * Returns a list of all fields used in Form Display Logic conditions
	 * @param string|int|null $project_id 
	 * @return string[] 
	 * @throws Exception When no valid project context
	 */
	public static function getFieldsUtilizedInConditions($project_id = null) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		if (!self::isEnabled($project_id)) return [];
		// Serve from cache if possible
		if (array_key_exists($project_id, self::$utilizedFieldsCache)) {
			return self::$utilizedFieldsCache[$project_id];
		}
		$rules = self::getFormDisplayLogicRules($project_id);
		// Concatenate all conditions with newline as a separator (important with regard to comments!)
		$all_conditions = "[".$Proj->table_pk."] \n" . trim(self::getLeafValuesConcatenated($rules, "\n"));
		if ($all_conditions !== '') {
			$fields_utilized = array_unique(array_keys(getBracketedFields("[".$Proj->table_pk."] " . $all_conditions, true, true, true)));
		}
		if (count($fields_utilized) == 1) $fields_utilized = [];
		self::$utilizedFieldsCache[$project_id] = $fields_utilized;
		return $fields_utilized;
	}
	/**
	 * Cache for getFieldsUtilizedInConditions()
	 * @var array
	 */
	private static $utilizedFieldsCache = [];

	/**
	 * Returns a string with all Form Display Logic conditions and'ed together
	 * @param string|int|null $project_id 
	 * @return string 
	 * @throws Exception When not in project context
	 */
	public static function getAndedFormDisplayRules($project_id = null) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		if (!isset(self::$andedFormDisplayRulesCache[$project_id])) {
			// Getting the rules will put this in the cache
			self::getFormDisplayLogicRules($project_id);
		}
		return self::$andedFormDisplayRulesCache[$project_id];
	}

	/**
	 * Concatenates all leaf values in an array into a string
	 * @param array $array 
	 * @param string $separator Default: Space
	 * @return string 
	 */
	private static function getLeafValuesConcatenated($array, $separator = " ") {
		$leafs = [];
		$iterator = new RecursiveIteratorIterator(
			new RecursiveArrayIterator($array),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($iterator as $value) {
			$leafs[] = $value;
		}
		return implode($separator, $leafs);
	}


	/**
	 * Indicates whether filled forms should be kept enabled
	 * @param string|int|null $project_id 
	 * @return bool 
	 * @throws Exception When no valid project context
	 */
	public static function isSetDoKeepFilledFormsEnabled($project_id = null) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		return $Proj->project['hide_filled_forms'] != "1";
	}

	/**
	 * Indicates whether disabled forms should be hidden
	 * @param string|int|null $project_id 
	 * @return bool 
	 * @throws Exception When no valid project context
	 */
	public static function isSetHideDisabledForms($project_id = null) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		return $Proj->project['hide_disabled_forms'] == "1";
	}

	/**
	 * Indicates whether Form Display Logic rules should be applied during survey autocontinue
	 * @param string|int|null $project_id 
	 * @return bool 
	 * @throws Exception When no valid project context
	 */
	public static function isSetApplyToSurveyAutocontinue($project_id = null) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		return $Proj->project['form_activation_survey_autocontinue'] == "1";
	}

	/**
	 * Gets the list of form display logic rules (organized by event/form[control_id => condition])
	 * @param string|int|null $project_id 
	 * @return array [ 
	 * 		event_id => [
	 * 				form => [
	 * 					control_id => condition
	 * 				]
	 * 		]
	 * ] 
	 * @throws Exception When no valid project context
	 */
	public static function getFormDisplayLogicRules($project_id = null) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		// Serve from cache if possible
		if (isset(self::$formDisplayRulesCache[$project_id])) {
			return self::$formDisplayRulesCache[$project_id];
		}
		// Prepare rules array
		$rules = [];
		$logic_anded = [];
		foreach ($Proj->eventsForms as $event_id => $forms) {
			foreach ($forms as $form) {
				$rules[$event_id][$form] = [];
			}
		}
		if (self::isEnabled($project_id)) {
			// Get FDL rules from backend DB
			// This performs at least one order of magnitude better than getFormDisplayLogicTableValues(), 
			// probably more if the number of rules is high
			$sql = "SELECT * FROM redcap_form_display_logic_conditions dlc 
					LEFT JOIN redcap_form_display_logic_targets dlt ON dlc.control_id = dlt.control_id 
					WHERE dlc.project_id = ?";
			$q = db_query($sql, [$project_id]);
			while ($row = db_fetch_assoc($q)) {
				// When event_id is null, apply to all events
				$event_ids = $row['event_id'] == null ? array_keys($rules) : [$row['event_id']];
				foreach ($event_ids as $event_id) {
					// Make sure form exists in event (necessary because of NULL event_id)
					if (isset($rules[$event_id]) && array_key_exists($row['form_name'], $rules[$event_id])) {
						$rules[$event_id][$row['form_name']][$row['control_id']] = $row['control_condition'];
					}
				}
				$logic_anded[] = $row['control_condition'];
			}
			db_free_result($q);
		}
		self::$formDisplayRulesCache[$project_id] = $rules;
		self::$andedFormDisplayRulesCache[$project_id] = "(".implode(") and (", array_unique($logic_anded)).")";
		return $rules;
	}
	/**
	 * Cache for getFormDisplayLogicRules()
	 * @var array
	 */
	private static $formDisplayRulesCache = [];
	/**
	 * Cache for getAndedFormDisplayRules()
	 * @var array
	 */
	private static $andedFormDisplayRulesCache = [];

	/**
	 * Checks if a record has access to a form
	 * @param string|int|null $project_id 
	 * @param string $record 
	 * @param string|int $event_id 
	 * @param string $current_form 
	 * @param int $instance 
	 * @return true|false|string True when the form is enabled, or name of next enabled form, or false when no more enabled forms are present 
	 * @throws Exception When no valid project context
	 */
	public static function checkFormAccess($project_id, $record, $event_id, $current_form, $instance = 1) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		if (!self::isEnabled($project_id)) return true; // No FDL - form is always enabled
		$event_states = self::getEventFormsState($project_id, $record, $event_id, $instance);
		$form_states = $event_states[$record][$event_id];
		$searching = false;
		foreach ($form_states as $form => $state) {
			// Skip until we find the current form
			$searching = $searching || ($form == $current_form);
			// Return true or name of next enabled form
			if ($searching && $state) return $form == $current_form ? true : $form;
		}
		// No more enabled forms
		return false;
	}

	/**
	 * Checks if for record adding a new instance of a repeating form is allowed
	 * @param string|int|null $project_id 
	 * @param string $record 
	 * @param string|int $event_id 
	 * @param string $form 
	 * @return bool 
	 * @throws Exception When not valid project context
	 */
	public static function checkAddNewRepeatingFormInstanceAllowed($project_id, $record, $event_id, $form) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		if (!$Proj->isRepeatingForm($event_id, $form)) return false;
		if (Design::isDraftPreview($Proj->project_id)) return false;
		if (!self::isEnabled($project_id)) return true;

		$new_instance = RepeatInstance::getRepeatFormInstanceMaxCountOnly($record, $event_id, $form, $Proj) + 1;
		$rules = self::getFormDisplayLogicRules($project_id);
        if (!isset($rules[$event_id][$form])) $rules[$event_id][$form] = [];
		$enabled = count($rules[$event_id][$form]) == 0;
		foreach ($rules[$event_id][$form] as $condition) {
			$enabled = $enabled || self::checkFormDisplayLogicRule($Proj, $record, $event_id, $form, $new_instance, $condition, true);
		}
		return $enabled;
	}

	/**
	 * Gets the form display status for the particular record and event (and instance in case of repeating events)
	 * @param string|int|null $project_id 
	 * @param string $record
	 * @param string|int $event_id 
	 * @param int $repeating_event_instance 
	 * @param array $forms_status Optional. If provided, it will be used instead of getting the status from the DB
	 * @return array
	 * @throws Exception When no valid project context
	 */
	public static function getEventFormsState($project_id, $record, $event_id, $repeating_event_instance = 1, $forms_status = null) {
		list ($Proj, $project_id) = Project::requireProject($project_id);
		// Serve from cache if possible
		if (isset(self::$eventFormsStateCache[$project_id][$record][$event_id][$repeating_event_instance])) {
			return self::$eventFormsStateCache[$project_id][$record][$event_id][$repeating_event_instance];
		}
		// Some prep work
		$event_forms = $Proj->eventsForms[$event_id] ?? [];
		$arm = $Proj->eventInfo[$event_id]['arm_num'];
		$is_new_repeating_event_instance = false;
		// Get form status
		if ($forms_status === null || !isset($forms_status[$record][$event_id])) {
			$forms_status = Records::getFormStatus($project_id, [$record], $arm, null, [$event_id => $event_forms]);
		}
		$is_repeating_event = $Proj->isRepeatingEvent($event_id);
		if ($is_repeating_event) {
			// Get all exising instances
			$existing_instances = [];
			foreach ($forms_status[$record][$event_id] as $form => $instances) {
				$existing_instances = array_merge($existing_instances, array_keys($instances));
			}
			$existing_instances = array_unique($existing_instances);
			$is_new_repeating_event_instance = !in_array($repeating_event_instance, $existing_instances, true);
		}
		$state = array_combine($event_forms, array_fill(0, count($event_forms), true));
		$rules = self::getFormDisplayLogicRules($project_id);
		if (self::isEnabled($project_id)) {
			// Set state to false for all forms for which there are rules
			foreach ($rules[$event_id] as $form => $conditions) {
				if (!empty($conditions)) $state[$form] = false;
			}
			// Go through each form and see if there is a rule for it
			foreach ($event_forms as $form) {
				// The check depends on whether the event is a repeating event or not
				if (self::isSetDoKeepFilledFormsEnabled($project_id)) {
					// FDL is set to keep forms with data enabled and the form status is not gray
					if (!$state[$form] && $is_repeating_event) {
						// Repeating event - it depends on the instance
						$state[$form] = ($forms_status[$record][$event_id][$form][$repeating_event_instance] ?? "") != ""; 
					}
					elseif (!$state[$form]) {
						// Otherwise, if at least one entry (0/1/2) is present, set to true
						$state[$form] = join("", $forms_status[$record][$event_id][$form] ?? []) != "";
					}
					if ($state[$form]) continue;
				}
				foreach ($rules[$event_id][$form] as $condition) {
					$state[$form] = $state[$form] || self::checkFormDisplayLogicRule($Proj, $record, $event_id, $form, $repeating_event_instance, $condition, $is_new_repeating_event_instance);
				}
			}
		}
		$state = [$record => [$event_id => $state]];
		self::$eventFormsStateCache[$project_id][$record][$event_id][$repeating_event_instance] = $state;
		return $state;
	}
	/**
	 * Cache for getEventFormsState()
	 * @var array
	 */
	private static $eventFormsStateCache = [];

	/**
	 * Executes the conditional logic for a form display rule for a particular record/event/form/instance
	 * @param Project $Proj 
	 * @param string $record 
	 * @param string|int $event_id 
	 * @param string $form 
	 * @param string|int $instance 
	 * @param string $condition 
	 * @param bool $new_repeating_instance When true, we need to set default data
	 * @return bool|null Returns null when the logic cannot be evaluted or other errors occur
	 */
	private static function checkFormDisplayLogicRule($Proj, $record, $event_id, $form, $instance, $condition, $new_repeating_instance = false) {
		$record_data = self::getRecordData($Proj->project_id, $record);
		if ($new_repeating_instance) {
			// Set default data for new instance
			if ($Proj->isRepeatingEvent($event_id)) {
				// Create default forms on repeating event (it's enough to copy the fields present 
				// in an existing instance)
                if (isset($record_data[$record]["repeat_instances"])) {
					$first_existing_instance = array_key_first($record_data[$record]["repeat_instances"][$event_id][""] ?? []);
					foreach ($record_data[$record]["repeat_instances"][$event_id][""][$first_existing_instance] as $field => $_) {
						$record_data[$record]["repeat_instances"][$event_id][""][$instance][$field] = "";
					}
				}
			}
			else if ($Proj->isRepeatingForm($event_id, $form)) {
				// Create default repeating form instance with all fields set to ""
				$record_data[$record]["repeat_instances"][$event_id][$form][$instance] = array_fill_keys(array_keys($Proj->forms[$form]['fields']), "");
			}
		}
		$repeat_instrument = $Proj->isRepeatingFormOrEvent($event_id, $form) ? $form : "";
		$result = REDCap::evaluateLogic($condition, $Proj->project_id, $record, $event_id, $instance, $repeat_instrument, $form, $record_data, false, false);
		return $result;
	}

	#endregion


	public static function getAccess($location, $record = null, $event_id = null, $instrument = null, $instance = null, $forms_events_array = array(), $projectId = null)
	{
        if (is_null($projectId)) {
            global $Proj;
        } else {
            $Proj = new Project($projectId);
        }

		// Disable FDL if Draft Preview Mode is enabled
		if (Design::isDraftPreview($Proj->project_id)) return;


		// Ensure the record is in the cache
		self::loadRecordData($Proj->project_id, [$record]);

		// Get form-level activation for all records
		$forms_access = self::getFormsAccessMatrix($record, $event_id, $instrument, $instance, $location, $forms_events_array, $Proj->project_id);

		// Set "next step" redirect path when viewing a single record
		if ($record && $event_id && $instrument && $location == 'left_form_menu' && !empty($forms_access))
		{
			$next_step_path = '';
			$instruments = $Proj->eventsForms[$event_id];
			$curr_forms_access = $forms_access[$record][$event_id] ?? [];

			$i = array_search($instrument, $instruments) + 1;
			$len = count($instruments);

			while ($i < $len) {
				if (isset($instruments[$i]) && isset($curr_forms_access[$instruments[$i]]) && $curr_forms_access[$instruments[$i]]) {
					$next_instrument = $instruments[$i];
					break;
				}
				$i++;
			}

			if (isset($next_instrument)) {
				// Path to the next available form in the current event.
				$next_step_path = APP_PATH_WEBROOT . 'DataEntry/index.php?pid=' . $Proj->project_id . '&id=' . removeDDEending($record) . '&event_id=' . $event_id . '&page=' . $next_instrument;

				// If this is a repeating event, maintain the instance
				if ($Proj->hasRepeatingFormsEvents() && $instance) {
					if ($Proj->RepeatingFormsEvents[$event_id] == "WHOLE") {
						$next_step_path .= '&instance=' . $instance;
					}
				}
			}

			// Access denied to the current page
			$arm = $event_id ? $Proj->eventInfo[$event_id]['arm_num'] : ($_GET['arm'] ?? $Proj->firstArmNum);
			if (!$forms_access[$record][$event_id][$instrument]
				// If the project has only one form AND the record doesn't exist yet, the project will try to bypass the Record Home page.
				// Prevent an infinite redirect in this edge case and allow the user to view the data entry form even though the FDL is false.
				&& !(!Records::recordExists($Proj->project_id, $record, $arm) && $instrument == $Proj->firstForm && count($Proj->forms) == 1 && PAGE == 'DataEntry/index.php')
			) {
				if (!$next_step_path) {
					$next_step_path = APP_PATH_WEBROOT . 'DataEntry/record_home.php?pid=' . $Proj->project_id . '&id=' . removeDDEending($record) . '&arm=' . $arm;
				}
				redirect($next_step_path);
			}
		}

		return $forms_access;
	}

	private static $form_activation_access = null;
	public static function getFormsAccessMatrix($record, $event_id, $current_instrument = null, $current_instance = null, $location=null, $forms_events_array = array(), $projectId = null)
	{
        if (is_null($projectId)) {
            global $Proj;
            $arm = getArm();
            $useCache = true;
        } else {
            $Proj = new Project($projectId);
            $arm = getArmByEventId($event_id);
            $useCache = false;
        }
		if ($record == null || $event_id == null) return [];

		// Get setup values
		$result = FormDisplayLogic::getFormDisplayLogicTableValues($Proj->project_id);

		// If not set up, return empty array
		if (empty($result['controls'])) return [];

        if ($useCache == true) {
            // If form activation status is already cached, then return it
            if (self::$form_activation_access !== null) return self::$form_activation_access;
        }

		$check_forms_events_array = (is_array($forms_events_array) && !empty($forms_events_array));

		if ($location == 'left_form_menu') {
			$events = array($event_id);
		} else {
			// Getting events of the current arm.
			$arm = $event_id ? $Proj->eventInfo[$event_id]['arm_num'] : (isset($_GET['arm']) ? $_GET['arm'] : 1);
			$events = isset($Proj->events[$arm]['events']) ? array_keys($Proj->events[$arm]['events']) : [];
		}

		$events_forms = [];
		foreach ($result['controls'] as $_ => $cf) {
			if (empty($cf['form-name'])) {
				continue;
			}
			foreach ($cf['form-name'] as $form_event_pair) {
				$target_events = [];
				list($form_name, $event_id) = explode("-", $form_event_pair);
				if (empty($event_id)) {
					// If no event_id, then add ALL events for this arm
					$target_events = isset($Proj->events[$arm]['events']) ? array_keys($Proj->events[$arm]['events']) : [];
				} else {
					$target_events[] = $event_id;
				}
				foreach($target_events as $event_id) {
					// Skip non-displayed events in a custom Record Status Dashboard
					if ($check_forms_events_array && !isset($forms_events_array[$event_id])) {
						continue;
					}
					// Skip non-displayed forms in a custom Record Status Dashboard
					if ($check_forms_events_array && !in_array($form_name, $forms_events_array[$event_id])) {
						continue;
					}
					if (!isset($events_forms[$event_id][$form_name])) {
						$events_forms[$event_id][$form_name] = [];
					}
					$events_forms[$event_id][$form_name][] = $cf["control-condition"];
				}
			}
		}

		// Get record ids
		$records = self::getCachedRecordIds($Proj->project_id);

		// Get form status for applicable records
		$forms_status = $result['prevent_hiding_filled_forms'] ? Records::getFormStatus($Proj->project_id, $records, $arm) : [];
		$forms_status_empty = empty($forms_status);

		// Building forms access matrix.
		self::$form_activation_access = [];
		foreach ($records as $id)
		{
			self::$form_activation_access[$id] = [];
			$record_data = self::getRecordData($Proj->project_id, $id);
			// Record data should NEVER be false here
			if ($record_data === false) {
				throw new Exception('FDL: Record data not found for record ' . $id . ' in project ' . $Proj->project_id);
			}
			foreach ($events as $event_id) 
			{
				self::$form_activation_access[$id][$event_id] = [];
				$forms = [];
			  
				if ($result['prevent_hiding_filled_forms'] && !$forms_status_empty) {
					if (isset($forms_status[$id][$event_id])) {
						foreach ($forms_status[$id][$event_id] as $form => $instances) {
							if (empty($instances)) {
								$forms[] = $form;
							} else {
								self::$form_activation_access[$id][$event_id][$form] = true;
							}
						}
					}
				} elseif (isset($Proj->eventsForms[$event_id])) {
					$forms = $Proj->eventsForms[$event_id];
				}

				foreach ($forms as $form)
				{
					// By default, all forms should be displayed
					$access = true;
					// But if an event/form has been set up for FRSL, then it defaults to false UNLESS its logic evaluates as true
					if (isset($events_forms[$event_id][$form])) {
						$access = false;
						foreach ($events_forms[$event_id][$form] as $cond) {
							$passedLogicTest = REDCap::evaluateLogic($cond, $Proj->project_id, $id, $event_id, 1, ($Proj->isRepeatingFormOrEvent($event_id, $form) ? $form : ""), $current_instrument, $record_data, false, false);
							if ($passedLogicTest) {
								$access = true;
								break;
							}
						}
					}
					// Set access for record-event-form
					self::$form_activation_access[$id][$event_id][$form] = $access;
				}
			}
		}
		return self::$form_activation_access;
	}


	/**
	 * @param array $post - POST data received from POST request or array that contains data expected from the post request
	 * @param array $forms_list - list names for all forms/events that have form display conditions set on them.
	 * @param array $sql_all - list all SQL queries executed during this method execution, for logging purposes.
	 * @return void
	 * @throws Exception
	 */
	public static function saveConditionsSettings(array &$post, array &$forms_list, array &$sql_all)
	{
		global $Proj, $lang;

		$hide_filled_forms = (isset($post['prevent_hiding_filled_forms']) && in_array($post['prevent_hiding_filled_forms'], ['0','1'])) ? ($post['prevent_hiding_filled_forms'] == '1' ? '0' : '1') : $Proj->project['hide_filled_forms'];
		$hide_disabled_forms = (isset($post['hide_disabled_forms']) && in_array($post['hide_disabled_forms'], ['0','1'])) ? $post['hide_disabled_forms'] : $Proj->project['hide_disabled_forms'];
		$enable_survey_support = (isset($post['enable_survey_support']) && in_array($post['enable_survey_support'], ['0','1'])) ? $post['enable_survey_support'] : $Proj->project['form_activation_survey_autocontinue'];
        $enable_mycap_support = (isset($post['enable_mycap_support']) && in_array($post['enable_mycap_support'], ['0','1'])) ? $post['enable_mycap_support'] : $Proj->project['form_activation_mycap_support'];

		$sql_all[] = $sql = "UPDATE redcap_projects 
						SET hide_filled_forms = '$hide_filled_forms', hide_disabled_forms = '$hide_disabled_forms', form_activation_survey_autocontinue = '$enable_survey_support', form_activation_mycap_support = '$enable_mycap_support'
						WHERE project_id = " . $Proj->getId();
		db_query_throw_on_error($sql);

		$deletedIds = json_decode($post['deleted_ids'] ?? '[]');
		if (!empty($deletedIds)) {
			// Delete Controls
			$sql_all[] = $sql = "DELETE FROM redcap_form_display_logic_conditions WHERE control_id IN (" . prep_implode($deletedIds) . ") and project_id = '" . $Proj->getId() . "'";
			db_query_throw_on_error($sql);
		}

		if (isset($post['outer-list'])) {
			foreach ($post['outer-list'] as $post_controls) {
				$control_id = $post_controls['control_id'];
				if (empty($control_id)) {
					// Insert
					$sql_all[] = $sql = "INSERT INTO redcap_form_display_logic_conditions (project_id, control_condition) VALUES
									 ('" . $Proj->getId() . "', '" . db_escape($post_controls['control-condition']) . "')";
					if (db_query_throw_on_error($sql)) {
						$control_id = db_insert_id();
					}
				} else {
					// Update
					$sql_all[] = $sql = "UPDATE redcap_form_display_logic_conditions SET control_condition = '" . db_escape($post_controls['control-condition']) . "' WHERE control_id ='" . $control_id . "'";
					db_query_throw_on_error($sql);
				}

				if ($control_id > 0) {
					$sql_all[] = $sql = "DELETE FROM redcap_form_display_logic_targets WHERE control_id ='" . $control_id . "'";
					$q = db_query_throw_on_error($sql);
					foreach ($post_controls['form-name'] as $form_event_pair) {
						list($form_name, $event_id) = explode("-", $form_event_pair);
						if ($Proj->longitudinal) {
							$event_name = ($event_id == '') ? $lang['alerts_70'] : $Proj->eventInfo[$event_id]['name_ext'];
							$forms_label = "{$Proj->forms[$form_name]['menu']} ($event_name)";
						} else {
							$forms_label = "{$Proj->forms[$form_name]['menu']}";
						}
						if (!in_array($forms_label, $forms_list)) {
							$forms_list[] = $forms_label;
						}
						// Insert Target Forms
						$sql_all[] = $sql = "INSERT INTO redcap_form_display_logic_targets (control_id, form_name, event_id) VALUES
											('" . db_escape($control_id) . "', '" . db_escape($form_name) . "', " . checkNull($event_id) . ")";
						$q = db_query_throw_on_error($sql);
					}
				}
			}
		} else {
			// Delete Controls
			$sql_all[] = $sql = "DELETE FROM redcap_form_display_logic_conditions WHERE project_id = '" . $Proj->getId() . "'";
			db_query_throw_on_error($sql);
		}
	}

	public static function validateControlConditionLogic($logic, $forceMetadataTable = true) {

		global $status, $draft_mode, $longitudinal, $lang, $Proj;
		// Default response
		$response = '0';

		// Should we show draft mode fields instead of live fields
		$forceMetadataTable = $forceMetadataTable || $status < 1 || ($status > 0 && $draft_mode < 1);

		// Check if calculation is valid
		$logic = Piping::pipeSpecialTags($logic, PROJECT_ID, null, null, null, USERID, true, null, null, false, false, false, true);

		// Obtain array of error fields that are not real fields
		$error_fields = Design::validateBranchingCalc($logic, $forceMetadataTable);

		// If longitudinal, make sure that each field references an event and that the event is valid
		if ($longitudinal) {
			// Gather smart variables to process when parsing
			$specialPipingTagsFormatted = Piping::getSpecialTagsFormatted(false);
			$specialPipingTags = array();
			foreach (Piping::getSpecialTags() as $tag) {
				$tagComp = explode(":", $tag);
				$tag = "[".$tagComp[0]."]";
				$specialPipingTags[] = $tag;
			}
			// Initialize array to capture invalid event names
			$invalid_event_names = array();
			// Set default value for not referencing events with fields
			$eventsNotReferenced = false;
			foreach (array_keys(getBracketedFields(cleanBranchingOrCalc($logic), true, true)) as $eventDotfield) {
				// If lacks a dot, then the event name is missing. Flag it
				if (in_array($eventDotfield, $specialPipingTagsFormatted)) continue;
				if (strpos($eventDotfield, '.')) {
					list ($unique_event, $field) = explode('.', $eventDotfield, 2);
				} else {
					$unique_event = $eventDotfield;
					$field = "";
				}
				if (in_array($field, $specialPipingTagsFormatted)) continue;
				if (strpos($eventDotfield, '.') === false) {
					$eventsNotReferenced = true;
				} else {
					// Validate the unique event name and ignore Smart Variables
					if (!$Proj->uniqueEventNameExists($unique_event) && !in_array("[".$unique_event."]", $specialPipingTags)) {
						// Invalid event name, so place in array
						$invalid_event_names[] = $unique_event;
					}
				}
			}
		}
		
		// Return list of fields that do not exist (i.e. were entered incorrectly), else continue.
		if (!empty($error_fields))
		{
			$response = js_escape2("{$lang['dataqueries_47']}{$lang['colon']} {$lang['dataqueries_45']}")."\n\n".js_escape2($lang['dataqueries_46'])."\n- "
					. implode("\n- ", $error_fields);
		}

		// If longitudinal, then must be referencing events for variable names
		elseif ($longitudinal && !empty($invalid_event_names) && $eventsNotReferenced)
		{
			$response = js_escape2($lang['dataqueries_111'])."\n\n".js_escape2($lang['dataqueries_112'])."\n- "
					. implode("\n- ", $invalid_event_names);
		}

		// Check for any formatting issues or illegal functions used
		else
		{
			// All is good (no errors)
			$response = '1';
			// Check the logic
			$parser = new LogicParser();
			try {
				$parser->parse($logic, null, true, false, false, true);
			}
			catch (LogicException $e) {
				if (count($parser->illegalFunctionsAttempted) === 0) {
					// Contains syntax errors
					$response = $lang['dataqueries_99'];
				}
				else {
					// Contains illegal functions
					$response = js_escape2("{$lang['dataqueries_47']}{$lang['colon']} {$lang['dataqueries_109']}")."\n\n".js_escape2($lang['dataqueries_48'])."\n- "
							. implode("\n- ", $parser->illegalFunctionsAttempted);
				}
			}
		}

		return $response;
	}
}