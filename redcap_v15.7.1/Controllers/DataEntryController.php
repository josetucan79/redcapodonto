<?php

class DataEntryController extends Controller
{
	// Save collapse state of data entry form list (classic only) on left-hand menu when NOT in record context
	public function saveShowInstrumentsToggle()
	{
		// Check collapse value
		if (!isset($_POST['collapse']) || !isset($_POST['targetid']) || !in_array($_POST['collapse'], array('0', '1'))) exit('0');		
		// Add value to UI State (project_id is key and menu ID is subkey)
		if ($_POST['collapse'] == '0') {
			// Add it as collapsed
			UIState::saveUIStateValue(PROJECT_ID, $_POST['object'], $_POST['targetid'], 1);
		} else {
			// Remove it from UI state
			UIState::removeUIStateValue(PROJECT_ID, $_POST['object'], $_POST['targetid']);
		}
	}

	// Check if a single record exists
	public function recordExists()
	{
		if (!isset($_POST['record'])) exit;
		print (Records::recordExists(PROJECT_ID, $_POST['record']) ? '1' : '0');
	}
	
	// Assign a record to a DAG
	public function assignRecordToDag()
	{
		global $Proj, $lang, $table_pk_label, $user_rights;
		// Get params
		$dags = $Proj->getGroups();
		if ($_POST['record']."" === "" || !isset($_POST['group_id']) || empty($dags) || $user_rights['group_id'] != '') exit;
		if ($_POST['group_id'] != '' && !isset($dags[$_POST['group_id']])) exit;
		$record = addDDEending(rawurldecode(urldecode($_POST['record'])));
		// Assign to DAG
		Records::assignRecordToDag($record, $_POST['group_id']);
		// Return successful response
		print "1";
	}	
	
	// Rename a record
	public function renameRecord()
	{
        global $Proj, $lang, $table_pk_label;
        // Get params
        if (!isset($_POST['record']) || !isset($_POST['new_record'])) exit;
        $_POST['record'] = (string)$_POST['record'];
        $_POST['new_record'] = (string)$_POST['new_record'];
        if ($_POST['record'] === "" || $_POST['new_record'] === "") exit;
        $record = strip_tags(nl2br(addDDEending(trim(rawurldecode(urldecode($_POST['record']))))));
        $new_record = strip_tags(nl2br(addDDEending(trim(rawurldecode(urldecode($_POST['new_record']))))));
        $orig_new_record = "".$_POST["new_record"];
        if ($orig_new_record !== $new_record) {
            print RCView::tt("data_entry_614"); // Invalid characters in new record name. Please change it and try again.
            return;
        }
        // Identical?
        if ($record === $new_record) {
            print "2";
            return;
        }
        // Set event_id here so that logging works out correctly
        $arm = getArm();
        $_GET['event_id'] = $Proj->multiple_arms ? $Proj->getFirstEventIdArm($arm) : $Proj->firstEventId;
        // Does record exist?
        if (Records::recordExists(PROJECT_ID, $new_record, ($Proj->multiple_arms ? $arm : null))) {
            // Return message that record already exists
            $msg = "<div style='color:#A00000;font-size:14px;font-weight:bold;'><img src='".APP_PATH_IMAGES."exclamation.png'> " . strip_tags(label_decode($table_pk_label)) . " \"" . removeDDEending($record) . "\" {$lang['data_entry_318']} \"" . removeDDEending($new_record) . "\" {$lang['data_entry_319']}</div>";
            exit($msg);
        }
        // If the record exists in another arm in another case, then conform the new record name to the case of the existing record in the other arm
        if ($Proj->multiple_arms) {
            $sql = "select distinct record from ".\Records::getDataTable(PROJECT_ID)." 
                    where project_id = ".PROJECT_ID." and field_name = '{$Proj->table_pk}'
				    and SHA1(record) != '".db_escape(sha1($new_record))."' and record = '".db_escape($new_record)."' limit 1";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $new_record = $row['record'];
            }
        }
        // Rename record and log this event
        DataEntry::changeRecordId($record, $new_record);
        // Return successful response
        print "1";
	}	
	
	// Delete an entire record
	public function deleteRecord()
	{
		global $Proj, $table_pk, $multiple_arms, $randomization, $status, $allow_delete_record_from_log;
		// Set event_id here so that logging works out correctly
		$_GET['event_id'] = ($multiple_arms && is_numeric($_POST['arm'])) ? $Proj->getFirstEventIdArm($_POST['arm']) : $Proj->firstEventId;
		// Delete record and log this event
		$_POST['record'] = rawurldecode(urldecode($_POST['record']));
		$allow_delete_record_from_log_param = ($allow_delete_record_from_log && isset($_POST['allow_delete_record_from_log'])
												&& $_POST['allow_delete_record_from_log'] == '1');
		Records::deleteRecord(addDDEending($_POST['record']), $table_pk, $multiple_arms, $randomization, $status, false,
							$Proj->getArmIdFromArmNum($_POST['arm']), "", $allow_delete_record_from_log_param);
		// Return successful response
		print "1";
	}	
	
	// Delete this event instance and log this event
	public function deleteEventInstance()
	{
		// Do not allow deletion while in DRAFT PREVIEW mode
		if (Design::isDraftPreview()) {
			print "0";
			return;
		}
		$log_event_id = Records::deleteEventInstanceByProject(PROJECT_ID, addDDEending(rawurldecode(urldecode($_POST['record']))), $_GET['event_id'], $_GET['instance']);
		// Return successful response
		print "1";
	}
	
	// Render the HTML for a record/form/event's instances for a Repeating Form
	public function renderInstancesTable()
	{
		$GLOBALS["draft_preview_enabled"] = Design::isDraftPreview();
		print RepeatInstance::renderRepeatingFormsDataTables($_POST['record'], array(), array(), $_POST['form'], $_POST['event_id'],
				(isset($_POST['force_display_table']) ? $_POST['force_display_table'] : 1), (isset($_POST['display_close_icon']) ? $_POST['display_close_icon'] : 0));
	}
	
	// Serially-running AJAX request for when user opens a survey via data entry form to check if the survey has been modified since the survey was
	// initially opened. This prevents users from closing the survey tab to return to the data entry form, save it, and thus mistakenly
	// overwrite all the survey responses.
	public function openSurveyValuesChanged()
	{
		// Return 1 if values have not changed since the survey was opened, else return 0 so we can warn user
		print Survey::openSurveyValuesChanged($_POST['time_opened'], $_POST['survey_hash']);
	}
	
	// Render the HTML table for a record's calendar events for the next X days
	public function renderUpcomingCalEvents()
	{
		print Calendar::renderUpcomingAgenda($_POST['days'], addDDEending(rawurldecode(urldecode($_POST['record']))), false, false);
	}
	
	// Fetch list of contributors to a response
	public function getResponseContributors()
	{
		global $lang;
		$contributors = Survey::getResponseContributors($_POST['response_id']);
		if (empty($contributors)) $contributors = array("[".$lang['data_entry_166']."]");
		print "<div style='margin-bottom:3px;'>".$lang['survey_1233']."</div>";
		print "<ul><li>".implode("</li><li>", $contributors)."</li></ul>";		
		if ($_POST['is_completed']) {
			$contributors = Survey::getResponseContributors($_POST['response_id'], true);
			if (!empty($contributors)) {
				print "<div style='margin-bottom:3px;border-top:1px dashed #ccc;padding-top:10px;margin-top:15px;'>".$lang['survey_1234']."</div>";
				print "<ul><li>".implode("</li><li>", $contributors)."</li></ul>";
			}
		}
	}

    // Verify username+password
    public function passwordVerify()
    {
        print DataEntry::passwordVerify() ? '1' : '0';
    }

	// Build record list cache
	public function buildRecordListCache()
	{
		Records::buildRecordListCache();
	}

	// Clear the record list cache
	public function clearRecordListCache()
	{
		if (UserRights::isSuperUserNotImpersonator() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			Records::clearRecordListCache();
			print '1';
		} else {
			print '0';
		}
	}


	/**
	 * Gets the data for the instance selector popovers
	 * @return never 
	 */
	public function getRepeatInstances()
	{
		header('Content-Type: application/json');
		$deferred = false;
		$isRhpTable = isset($_POST["isRhpTable"]) && $_POST["isRhpTable"] == "true";
		print json_encode(Form::getInstanceSelectorContent($_POST["record"], $_POST["form"], $_POST["event_id"], $deferred, $isRhpTable));
		exit;
	}

	public function storeRepeatInstancesPageLength()
	{
		$key = $_POST["key"];
		$value = $_POST["value"];
		// Validation
		// Key must match "grid" or "rhp-[form]-[event_id]"
		if (!preg_match('/^grid$|^rhp-([_a-z0-9])+-[0-9]+$/', $key)) {
			return '0';
		}
		// Value must be "all" or an integer
		if (!preg_match('/^all$|^[0-9]+$/', $value)) {
			return '0';
		}
		UIState::saveUIStateValue(PROJECT_ID, "rc-instance-selector-pageLength", $key, $value);
		return '1';
	}

	public function storeRepeatInstancesFilters()
	{
		$key = $_POST["key"];
		$value = $_POST["value"];
		// Validation
		// Key must match "rhp-[form]-[event_id]"
		if (!preg_match('/^rhp-([_a-z0-9])+-[0-9]+$/', $key)) {
			return '0';
		}
		// Value must be a comma-delimited list, limited to the following: 0,1,2,S0,S2, or empty
		$value = explode(",", $value);
		foreach ($value as $v) {
			if (!in_array($v, array('', '0', '1', '2', 'S0', 'S2'))) {
				return '0';
			}
		}
		$value = implode(",", $value);
		UIState::saveUIStateValue(PROJECT_ID, "rc-instance-selector-filters", $key, $value);
		return '1';
	}
}