<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
// Calculate class
$cp = new Calculate();
// BranchingLogic class
$bl = new BranchingLogic();

// Make sure DRAFT PREVIEW is not enabled
if (Design::isDraftPreview()) {
	exit('0');
}

// Make sure is Post request and also that record exists
if (!isset($_POST['action']) || $_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['record'])) {
	exit('0');
}

// Make sure rid is supplied and valid
$rid = (isset($_POST['rid'])) ? Randomization::getRid($_POST['rid']) : false;
if (!$rid) {
	exit('0');
}

// Output html form
if ($_POST['action'] == 'view')
{
	// Check if all fields involved still exist
	$missingFields = Randomization::randFieldsMissing($rid);
	// Check if allocation table exists
	$allocTableExists = Randomization::allocTableExists($status, $rid);
	// Determine if we can display it. If not, display errors.
	if ($allocTableExists && empty($missingFields)) {
		// Display the form table
		Randomization::randomizeWidgetTable($rid);
	} elseif (!empty($missingFields)) {
		// Give error message that some fields have been deleted that are needed for randomization
		print RCView::p(array(),
			RCView::b($lang['global_01'].$lang['colon']).RCView::br().
			$lang['random_122'] . RCView::SP . RCView::b(implode(", ", $missingFields)) . $lang['period']
		);
	} elseif (!$allocTableExists) {
		// Give error message is an allocation file was never uploaded
		print RCView::p(array(),
			RCView::b($lang['global_01'].$lang['colon']).RCView::br().
			($status > 0 ? $lang['random_71'] : $lang['random_70'])
		);
	}
}


// Randomize the record and return success message
elseif ($_POST['action'] == 'randomize')
{
	// Set originally posted event_id from the form
	$page_event_id = $_POST['event_id'];
	// Get randomization setup values first
	$randAttr = Randomization::getRandomizationAttributes($rid);
	// Set values
	$record = $submitted_record = $_POST['record'];
	$existing_record = ($_POST['existing_record'] == '1' && Records::recordExists(PROJECT_ID, $record));
	// Aggregate the criteria fields and their values into array
	$fields = array();
	if (trim($_POST['fields']) != "") {
		$field_names = explode(",", $_POST['fields']);
		$field_values = explode(",", $_POST['field_values']);
		foreach ($field_names as $key=>$field) {
			$fields[$field] = $field_values[$key];
		}
	}
	// DAG: If grouping by DAG, then get this record's DAG (or get DAG set by/assigned to user)
	$group_id = '';
	if ($randAttr['group_by'] == 'DAG' && $user_rights['group_id'] == '') {
		// If user is NOT in a DAG, assign record to the DAG they designated
		if ($Proj->getGroups($_POST['redcap_data_access_group']) !== false) {
			$group_id = $_POST['redcap_data_access_group'];
		}
	} elseif ($existing_record && $randAttr['group_by'] == 'DAG') {
		// If record has already been assigned to a DAG, get the group_id of the record
		$sql = "select value from ".\Records::getDataTable($project_id)." where project_id = $project_id and record = '".db_escape($record)."'
				and field_name = '__GROUPID__' limit 1";
		$q = db_query($sql);
		if (db_num_rows($q) > 0) {
			$value = db_result($q, 0);
			// Verify that this DAG belongs to this project
			if ($Proj->getGroups($value) !== false) {
				$group_id = $value;
			}
		}
	} elseif (!$existing_record && $randAttr['group_by'] == 'DAG' && $user_rights['group_id'] != '') {
		// If user is in a DAG and creating a new record, assign record to their DAG
		$group_id = $user_rights['group_id'];
	}
	// If record does NOT exist yet AND we're using auto-numbering, get new record name
	if (!$existing_record) {
		$record = REDCap::reserveNewRecordId($project_id, ($auto_inc_set ? DataEntry::getAutoId() : $record));
        // If record already exists, then return an error
        if ($record === false) {
            exit('2');
        }
	}
	// Randomize and return aid key
	$randomizeResult = Randomization::randomizeRecord($rid, $record, $fields, $group_id);
	if ($randomizeResult === false) {
	    // If failed at first (probably due to race condition), then try again
		$randomizeResult= Randomization::randomizeRecord($rid, $record, $fields, $group_id);
		if ($randomizeResult === false) {
			// If failed again, then try one more time before returning an error
			$randomizeResult = Randomization::randomizeRecord($rid, $record, $fields, $group_id);
			if ($randomizeResult === false) {
                // If the record does not exist yet when randomization fails, remove record from redcap_new_record_cache table to free up that record name again
                if (!$existing_record) {
                    $sql = "delete from redcap_new_record_cache where project_id = ? and record = ?";
                    db_query($sql, [$project_id, $record]);
                }
                // Stop
                exit('0');
            }
		}
    }
	
	if (is_string($randomizeResult)) {
        if ($randomizeResult == '0') {
            // NO ASSIGNMENTS AVAILABLE: If returned 0, then cannot allocate
            $failMessage = RCView::b($lang['random_60']). " $table_pk_label \"<b>".RCView::escape($record)."</b>\" {$lang['random_61']}";
        } else {
            $failMessage = RCView::escape($randomizeResult); // custom error message from external module
        }
		?>
		<div class="red" style="margin:20px 0;">
			<table cellspacing=10 width=100%>
				<tr>
					<td style="padding:0 30px 0 40px;">
						<img src="<?php echo APP_PATH_IMAGES ?>cross_big.png">
					</td>
					<td style="font-size:14px;font-family:verdana;line-height:22px;padding-right:30px;">
						<?php print $failMessage; ?>
					</td>
				</tr>
			</table>
		</div>
		<!-- Close button -->
		<div style="text-align:right;padding:5px 20px 10px 0;">
			<button class="jqbutton" onclick="$('#randomizeDialog<?=$rid?>').dialog('close');" style="font-size:15px;">Close</button>
		</div>
		<?php
		exit;
	}
	## SUCCESSFULLY RANDOMIZED!
	// Get the value allocated for the target randomization field
	list ($target_field, $target_field_value) = Randomization::getRandomizedValue($record, $rid);
	// Obtain randomization/criteria fields and group by event_id inside array
	$randomizationCriteriaFields = Randomization::getRandomizationFields($rid, true);
	$fieldNamesEvents = array();
	while (!empty($randomizationCriteriaFields)) {
		$field = array_shift($randomizationCriteriaFields);
		if ($longitudinal) {
			$event_id = array_shift($randomizationCriteriaFields);
			$fieldNamesEvents[$event_id][] = $field;
		} else {
			$fieldNamesEvents[$Proj->firstEventId][] = $field;
		}
	}
	// Loop through all events and save data for each event for this record
	$log_event_ids = array(); // Collect all $log_event_id's in an array
	foreach ($fieldNamesEvents as $event_id=>$fields) {
		// Initialize parameters before performing the Save
		$_GET['event_id'] = $event_id;
		$_POST = array($table_pk=>$record);
		// If user is in a DAG *or* if the user is NOT in a DAG but is assigning the record to a DAG
		// during randomization, then set __GROUPID__ attribute to force the DAG assignment.
		if ($group_id != '') {
			$_POST['__GROUPID__'] = $group_id;
		} elseif ($user_rights['group_id'] != '') {
			$_POST['__GROUPID__'] = $user_rights['group_id'];
		}
		// For each field in this event, add to $_POST
		foreach ($fields as $field) {
			if ($field == $target_field) {
				$_POST[$field] = $target_field_value;
				$target_event = $event_id;
			} else {
				$_POST[$field] = $field_values[array_search($field, $field_names)];
			}
		}
		// Save the record data (in case was added or changed)
		$randomization_form = $_GET['page'] = $Proj->metadata[$randAttr['targetField']]['form_name'];
		list ($record, $nothing2, $log_event_id, $dataValuesModified, $dataValuesModifiedIncludingCalcs) = DataEntry::saveRecord($record, true, false, false, null, true);
		// Add log_event_id of this event to array
		$log_event_ids[] = $log_event_id;
	}

	## DATA QUALITY: real-time execution (for existing records only)
	// Instantiate DQ object
	$dq = new DataQuality();
	// Check for any errors and return array of DQ rule_id's for those rules that were violated
	list ($dq_errors, $dq_errors_excluded) = $dq->checkViolationsSingleRecord($record, $page_event_id, null,
												array_merge(array($randAttr['targetField']), array_keys($randAttr['strata'])));
	if (!empty($dq_errors))
	{
		## DQ violations occur, so undo everything here except saved data of strata fields
		// Remove all logging that was just logged above
		$sql = "delete from ".Logging::getLogEventTable($project_id)." where log_event_id in (".prep_implode($log_event_ids).")";
		db_query($sql);
		// Remove randomization field data from redcap_data (Part 1 of undoing randomization for this record)
		$sql = "delete from ".\Records::getDataTable(PROJECT_ID)." where project_id = ".PROJECT_ID." and record = '".db_escape($record)."'
				and event_id = '".db_escape($randAttr['targetEvent'])."' and field_name = '".db_escape($randAttr['targetField'])."'";
		db_query($sql);
		// Remove $randomizeResult (aid) from randomization_allocation table (Part 2 of undoing randomization for this record)
		$sql = "UPDATE redcap_randomization_allocation SET is_used_by = NULL WHERE aid = ? AND rid = ? AND is_used_by = ?";
		db_query($sql, [intval($randomizeResult), intval($rid), $record]);
		// SAVE & LOG AGAIN (exclude randomization field): Loop through all events and save data for each event for this record
		foreach ($fieldNamesEvents as $event_id=>$fields) {
			// Initialize parameters before performing the Save
			$_GET['event_id'] = $event_id;
			$_POST = array($table_pk=>$record);
			// If user is in a DAG *or* if the user is NOT in a DAG but is assigning the record to a DAG
			// during randomization, then set __GROUPID__ attribute to force the DAG assignment.
			if ($group_id != '') {
				$_POST['__GROUPID__'] = $group_id;
			} elseif ($user_rights['group_id'] != '') {
				$_POST['__GROUPID__'] = $user_rights['group_id'];
			}
			// For each field in this event, add to $_POST
			foreach ($fields as $field) {
				if ($field != $target_field) {
					$_POST[$field] = $field_values[array_search($field, $field_names)];
				}
			}
			// Save the record data (excluding the randomization field)
			list ($record, $nothing1, $nothing2, $nothing3, $nothing4) = DataEntry::saveRecord($record);
		}
		// Display message that record CANNOT BE RANDOMIZED yet
		print 	RCView::div(array('class'=>"red", 'style'=>"margin:10px 0;"),
					RCView::img(array('src'=>'exclamation.png')) .
					"<b>{$lang['global_01']}{$lang['colon']}</b> $table_pk_label \"<b>".RCView::escape($record)."</b>\" {$lang['random_128']}"
				);
		// Display DQ rules pop-up message for Data Entry page
		$dq->displayViolationsSingleRecord($dq_errors, $record, $page_event_id, $_GET['instance']);
		// Display dialog-close button
		print 	RCView::div(array('style'=>'text-align:right;padding:30px 10px 10px;'),
					RCView::button(array('class'=>'jqbutton', 'style'=>'font-family:verdana;font-size:13px;',
						'onclick'=>"$('#randomizeDialog$rid').dialog('close');"), "Close")
				);
		// Stop the script here since we are done displaying the error
		exit;
	}

	// Log that randomization took place right after we saved the record values
	Logging::logEvent("", "redcap_data", "MANAGE", $record, "$table_pk = '$record'\nrandomization_id = $rid", "Randomize record");
	## Give message of success
	// Get the field's label and choice label
	$field_label = trim(Piping::replaceVariablesInLabel(filter_tags(label_decode($Proj->metadata[$target_field]['element_label'])),$record,$page_event_id,$_GET['instance']));
	$field_label = $field_label ? "\"<b>$field_label</b>\"" : "[<b>$target_field</b>]";
    $confirmationMessage = "$table_pk_label \"<b>".RCView::escape($record)."</b>\" {$lang['random_57']} $field_label {$lang['random_58']} ";
    if ($Proj->metadata[$target_field]['element_type'] == 'text') {
        $confirmationMessage .= "\"<b>".RCView::escape($target_field_value)."</b>\"{$lang['period']}"; // e.g. for blinded/concealed just the target value
    } else {
        $choices = parseEnum($Proj->metadata[$target_field]['element_enum']);
        $field_choice_label = filter_tags(label_decode($choices[$target_field_value]));
        $confirmationMessage .= "\"<b>$field_choice_label</b>\" (".RCView::escape($target_field_value)."){$lang['period']}";
    }
	// Return confirmation message in pop-up and data for piping into smart variables
    $isBlinded = ($Proj->metadata[$target_field]['element_type']=='text') ? 1 : 0;
    list ($target_field, $target_field_value, $target_field_alt_value, $rand_time_server, $rand_time_utc) = Randomization::getRandomizedValue($record, $rid);
	?>
	<div class="darkgreen" style="margin:20px 0;">
		<table cellspacing=10 width=100%>
			<tr>
				<?php if (!$isMobileDevice) { ?>
					<td style="padding:0 30px 0 40px;">
						<img src="<?php echo APP_PATH_IMAGES ?>check_big.png">
					</td>
				<?php } ?>
				<td style="font-size:14px;font-family:verdana;line-height:22px;padding-right:30px;">
					<?=$confirmationMessage?>
				</td>
			</tr>
		</table>
	</div>
	<!-- Notification msg if record renamed due to auto-numbering -->
	<?php if ($auto_inc_set && $submitted_record != $record) { ?>
		<div class="yellow" style="margin:0 0 20px;">
			<?php echo RCView::img(array('src'=>'exclamation_orange.png'))
					 . "<b>{$lang['global_03']}{$lang['colon']}</b> {$lang['random_108']} $table_pk_label \"<b>".RCView::escape($record)."</b>\"
						{$lang['random_109']} $table_pk_label \"<b>$submitted_record</b>\" {$lang['random_110']}" ?>
		</div>
	<?php } ?>
	<!-- Close button -->
	<div style="text-align:right;padding:5px 20px 10px 0;">
		<button class="jqbutton" onclick="$('#randomizeDialog<?=$rid?>').dialog('close');" style="font-size:15px;">Close</button>
	</div>
	<!-- Hidden element that will be used to replace Randomize button -->
	<div id="alreadyRandomizedTextWidget<?=$rid?>" class="alreadyRandomizedText" style="display:none;"><?php echo $lang['random_56'] ?></div>
	<!-- Hidden element containing raw value and name of randomization field -->
	<input type="hidden" id="randomizationFieldRawVal<?=$rid?>" value="<?php echo js_escape2(RCView::escape($target_field_value)) ?>">
	<input type="hidden" id="randomizationFieldAltVal<?=$rid?>" value="<?php echo js_escape2(RCView::escape($target_field_alt_value)) ?>">
	<input type="hidden" id="randomizationFieldName<?=$rid?>" value="<?php echo js_escape2($target_field) ?>">
	<input type="hidden" id="randomizationFieldEvent<?=$rid?>" value="<?php echo js_escape2($target_event) ?>">
	<input type="hidden" id="randomizationFieldTimeServer<?=$rid?>" value="<?php echo js_escape2($rand_time_server) ?>">
	<input type="hidden" id="randomizationFieldTimeUTC<?=$rid?>" value="<?php echo js_escape2($rand_time_utc) ?>">
	<?php if ($randAttr['group_by'] == 'DAG') { ?>
		<input type="hidden" id="redcap_data_access_group" value="<?php echo js_escape2($group_id) ?>">
	<?php } ?>
	<input type="hidden" id="record" value="<?php echo js_escape2(RCView::escape($record)) ?>">
    <!-- Set javascript for current page -->
    <?php
    // Build JS array of criteria fields IF they exist on the current event
    $randomizationCriteriaFieldList = array();
    if ($randAttr['targetEvent'] == $page_event_id) $randomizationCriteriaFieldList[] = $randAttr['targetField'];
    foreach ($randAttr['strata'] as $this_strata_field=>$this_strata_event_id) {
        if ($this_strata_event_id == $page_event_id) $randomizationCriteriaFieldList[] = $this_strata_field;
    }
    if (!empty($randomizationCriteriaFieldList))
    {
        ?>
        <script type="text/javascript">
            var randomizationCriteriaFieldList = new Array(<?php echo prep_implode($randomizationCriteriaFieldList) ?>);
            updatePipeReceivers($('#randomizationFieldName<?=$rid?>').val(), $('#randomizationFieldEvent<?=$rid?>').val(), $('#randomizationFieldRawVal<?=$rid?>').val());
            var targetRawVal = '<?=js_escape(RCView::escape($target_field_value))?>';
            var targetAltVal = '<?=js_escape(RCView::escape($target_field_alt_value))?>';
            if (<?=$isBlinded?>) {
                // $('.piping_receiver.piperec-rand-group-<?=$rid?>').text(targetAltVal);
                $('.piping_receiver.piperec-rand-number-<?=$rid?>').text(targetRawVal);
            } else {
                // $('.piping_receiver.piperec-rand-group-<?=$rid?>').text(targetRawVal);
                $('.piping_receiver.piperec-rand-number-<?=$rid?>').text(targetAltVal);
            }
            var randTimeSrv = '<?=js_escape(\DateTimeRC::format_user_datetime($rand_time_server,'Y-M-D_24',null,false,true))?>';
            $('.piping_receiver.piperec-rand-time-<?=$rid?>').text(randTimeSrv);
            var randTimeUtc = '<?=js_escape(\DateTimeRC::format_user_datetime($rand_time_utc,'Y-M-D_24',null,false,true))?>';
            $('.piping_receiver.piperec-rand-utc-time-<?=$rid?>').text(randTimeUtc);
        </script>
        <?php
    }
}
