<?php

use ExternalModules\ExternalModules;
use function PHPUnit\Framework\isNull;

/**
 * RANDOMIZATION
 */
class Randomization
{
	// Set max number of source fields possible for randomization (determined by table structure)
	const MAXSOURCEFIELDS = 15;

	// Render the tabs at top of Randomization page based upon user rights
	static function renderTabs($rid)
	{
		global $user_rights;
		$tabs = array();
        $summaryPage = (PAGE=='Randomization/view_allocation_table.php') ? 'Randomization/index.php' : PAGE;
        $tabs[$summaryPage] = RCView::tt('random_154'); // Summary (nb. index.php or dashboard.php to handle users with dash-only rights)
        if ($rid!==false) {
            $sequenceBadge = RCView::span(array('class'=>'badge bg-secondary ml-1'), self::getSequenceFromRid($rid));
            if ($user_rights['random_setup']) {
                $tabs["Randomization/index.php?rid=$rid"] = RCView::tt('random_48').$sequenceBadge;
            }
            if ($rid>0 && $user_rights['random_dashboard']) {
                $tabs["Randomization/dashboard.php?rid=$rid"] = RCView::tt('rights_143').$sequenceBadge;
                // $tabs['Randomization/dashboard_all.php'] = $lang['random_26'];
            }
            if ($rid>0 && PAGE=='Randomization/view_allocation_table.php') {
                $stratum = (isset($_GET['stratum'])) ? htmlspecialchars($_GET['stratum'], ENT_QUOTES) : '';
                $tabs["Randomization/view_allocation_table.php?rid=$rid&stratum=$stratum"] = RCView::tt('random_166').$sequenceBadge; 
            }
        }
		RCView::renderTabs($tabs);
	}

	// Returns status (T/F) if randomization has been set up yet for this project
	static function setupStatus($project_id=null, $rid=null)
	{
		if ($project_id == null) $project_id = PROJECT_ID;
        $rid = self::getRid($rid, $project_id, true);
		return ($rid > 0);
	}

	// Removes the entire randomization setup (removes all from tables) for this project/randomization
	static function eraseRandomizationSetup($rid)
	{
		global $status;
		// Make sure we're in dev or a super user, else fail
		if (intval($rid)>0 && ($status < 1 || SUPER_USER)) {
			// Delete from table
            $rid = self::getRid($rid);
			$sql = "delete from redcap_randomization where project_id = " . PROJECT_ID . " and rid = " . intval($rid). " limit 1";
			return db_query($sql);
		}
		return false;
	}

	// Return number of records containing data for a given field
	static function getFieldDataCount($field)
	{
		$sql = "select count(distinct(record)) from ".\Records::getDataTable(PROJECT_ID)." where project_id = " . PROJECT_ID . "
				and field_name = '".db_escape($field)."' and value != ''";
		$q = db_query($sql);
		if (!$q) return '';
		return db_result($q, 0);
	}

	// When saving randomization model, delete all data for all records containing data for the randomization field/event selected
	static function deleteSingleFieldData($field, $event=null)
	{
		// Get global vars
		global $table_pk;
		// Get original $_POST and $_GET so we can resurrect it in the end here
		$originalPost = $_POST;
		$originalGet = $_GET;
		// Get all records/events with data for that field[/event]
        $eventSql = (empty($event)) ? '' : 'and event_id = '.db_escape($event);
		$recordsWithRandFieldData = array();
		$sql = "select distinct record, event_id from ".\Records::getDataTable(PROJECT_ID)." where project_id = " . PROJECT_ID . "
				and field_name = '".db_escape($field)."' $eventSql and value != '' order by record, event_id";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			while ($row = db_fetch_assoc($q)) {
				$recordsWithRandFieldData[$row['record']][] = $row['event_id'];
			}
			// Loop through each record/event and delete each formally (with logging)
			foreach ($recordsWithRandFieldData as $record=>$events)
			{
				foreach ($events as $event_id)
				{
					// Simulate new Post submission (as if submitted via data entry form)
					$_POST = array($table_pk=>$record, $field=>'');
					// Need event_id in query string for saving properly
					$_GET['event_id'] = $event_id;
					// Delete randomization field values and log it
					DataEntry::saveRecord($record);
				}
			}
		}
		// Resurrect values
		$_POST = $originalPost;
		$_GET = $originalGet;
	}

	// Returns allocation table id of given record if randomized (given the current project status)
    // Returns false if record is not randomized
    // If specific randomization is not specified then returns aid of the lowest numbered of the project's randomizations where the record is randomized
	static function wasRecordRandomized($record='', $rid = null, $project_id = null)
	{
        if ($project_id == null) {
            $project_id = PROJECT_ID;
            global $status;
        } else {
            $Proj = new Project($project_id);
            $status = $Proj->project['status'];
        }

		if (!isset($status) || (isset($status) && !is_numeric($status))) {
			// Get project's status
			$sql = "select status from redcap_projects where project_id = " . $project_id;
			$q = db_query($sql);
			$status = db_result($q, 0);
		}
        if (!is_null($rid) && self::getRid($rid)) {
            $ridlimit = " and r.rid = ".intval($rid);
        }
		$sql = "select a.aid from redcap_randomization_allocation a, redcap_randomization r
				where r.project_id = " . $project_id . " and a.project_status = $status
				and r.rid = a.rid $ridlimit and a.is_used_by = '".db_escape($record)."' limit 1";
		$q = db_query($sql);
        if (db_num_rows($q) > 0) {
			$row = db_fetch_assoc($q);
			return $row['aid'];
        }
        return false;
	}

	// Returns boolean of whether a given record has been randomized for the project (given the current project status) in any randomization that uses DAG
	static function wasRecordRandomizedByDAG($record='', $project_id = null)
	{
        if ($project_id == null) $project_id = PROJECT_ID;
        $randByDAG = false;
        $allAttrs = self::getAllRandomizationAttributes($project_id);
        foreach ($allAttrs as $rid => $attrs) {
            if ($attrs['group_by']=='DAG') {
                $randByDAG = self::wasRecordRandomized($record, $rid, $project_id);
            }
            if ($randByDAG) break;
        }
		return $randByDAG;
	}

	// Returns randomization field name and value as array (or false on failure) for the record's target randomization field's value that was allocated
	static function getRandomizedValue($record, $rid=null)
	{
		global $status;
        $rid = self::getRid($rid, PROJECT_ID, !isinteger($rid));
		$sql = "select r.target_field, a.target_field as target_field_value, a.target_field_alt as target_field_alt_value
				from redcap_randomization_allocation a, redcap_randomization r
				where r.project_id = " . PROJECT_ID . " and a.project_status = $status
				and r.rid = a.rid and r.rid = $rid and a.is_used_by = '".db_escape($record)."' order by aid desc limit 1";
		$q = db_query($sql);
		if ($q && db_num_rows($q) > 0) {
			$ret = db_fetch_assoc($q);
            $seq = self::getSequenceFromRid($rid);
            $tsSrv = self::getSmartVariableData('rand-time',$record,$seq,true);
            $tsUTC = self::getSmartVariableData('rand-utc-time',$record,$seq,true);

			return array($ret['target_field'], $ret['target_field_value'], $ret['target_field_alt_value'], $tsSrv, $tsUTC);
		} else {
			return false;
		}
	}

	// Delete the allocation table for given project status. If no allocations are left after this deletion, then remove field designations too.
	static function deleteAllocFile($rid, $this_status)
	{
		global $status, $longitudinal, $Proj, $table_pk;
		// First, get list of records already randomized and delete their assigned value IF project is on the
		// same status (dev or prod) as the status of the alloc file we're deleting.
		$rid = self::getRid($rid);
		if ($status == $this_status)
		{
			// Obtain all allocated values and put into array
			$sql = "select a.is_used_by as record, r.target_field, r.target_event
					from redcap_randomization_allocation a, redcap_randomization r
					where r.project_id = " . PROJECT_ID . " and a.project_status = $this_status
					and r.rid = $rid and r.rid = a.rid and a.is_used_by is not null
					order by abs(a.is_used_by), a.is_used_by";
			$q = db_query($sql);
			$valuesToDelete = array();
			while ($row = db_fetch_assoc($q))
			{
				// Put record info into array to delete later
				if (!isset($event_id)) {
					$event_id = (is_numeric($row['target_event'])) ? $row['target_event'] : $Proj->firstEventId;
				}
				$valuesToDelete[$row['record']][$event_id] = $row['target_field'];
			}
			// Delete ALL values for the ranomdization field from data table
			if (!empty($valuesToDelete))
			{
				// Formally delete the values so that they get logged properly (use hack to simulate data entry form submission)
				foreach ($valuesToDelete as $record=>$attr) {
					foreach ($attr as $event_id=>$field)
					{
						// Simulate new Post submission (as if submitted via data entry form)
						$_POST = array($table_pk=>$record, $field=>'');
						// Need event_id in query string for saving properly
						$_GET['event_id'] = $event_id;
						// Delete randomization field values and log it
						DataEntry::saveRecord($record);
					}
				}
			}
		}
		// Now delete all allocations for this status
		$sql = "delete from redcap_randomization_allocation where rid = $rid and project_status = $this_status";
		// Return successful/fail response
		return db_query($sql);
	}

	// Determine if dev or prod allocation files exist for this project's randomizations. Option to check for specific project status, and/or for a specific randomization (checks for all if rid not specified).
	static function allocTableExists($status=null, $rid=null)
	{
        if (!(is_null($status) || $status==1 | $status==0)) return false;

        $tableLoaded = false;
        if (is_null($rid)) {
            $all = self::getAllRandomizationAttributes();
            foreach(array_keys($all) as $thisRid) {
                if (!self::allocTableExists($status, $thisRid)) return false;
            }
            $tableLoaded = true;
        } else {
            $rid = self::getRid($rid);
            if ($rid===false) return false;

            $params = array(intval(PROJECT_ID), $rid);
            $sql = "select 1 from redcap_randomization_allocation a, redcap_randomization r
                    where r.project_id = ? and r.rid = a.rid and r.rid = ? ";
            if ($status == '0' || $status == '1') {
                $sql .= " and a.project_status = ?";
                $params[] = intval($status);
            }
    		$sql .= " limit 1";
	    	$q = db_query($sql, $params);
		    $tableLoaded = ($q && db_num_rows($q) > 0);
        }
        return $tableLoaded;
	}

	// Check if all fields used in randomization still exist in metadata
	static function randFieldsMissing($rid)
	{
		global $Proj;
		// Collect missing fields in array
		$missingFields = array();
		// Get rand attributes
		$randAttr = self::getRandomizationAttributes($rid);
		// Check target field first
		if (!isset($Proj->metadata[$randAttr['targetField']])) {
			$missingFields[] = $randAttr['targetField'];
		}
		// Check strata
		foreach (array_keys($randAttr['strata']) as $field) {
			if (!isset($Proj->metadata[$field])) {
				$missingFields[] = $field;
			}
		}
		// Return array
		return $missingFields;
	}

	// Obtain/validate rid key value (if exists - false if not) for this project 
	static function getRid($ridVal=null, $project_id=null, $getFirstRid=false)
	{
		// getRid() is called 10 times(!) just to show the Randomize Record dialog!?
		// Validate project_id
		$project_id = intval($project_id ?? (defined("PROJECT_ID") ? PROJECT_ID : null));
		if ($project_id == 0) return false;
		// Validate ridVal (and getFirstRid)
		$ridVal = intval($ridVal ?? 0);
        if ($ridVal == 0 && !$getFirstRid) return false;
		// Check if the randomization module is enabled for the project
		$Proj = new \Project($project_id);
		if ($Proj->project['randomization'] != 1) return false;
		// Serve from cache if available
		if ($ridVal > 0 && isset(static::$getRidCache[$project_id][$ridVal])) {
			return $ridVal;	
		}
		// Build query (all params are ensured to be ints > 0)
        $sql = "SELECT rid FROM redcap_randomization WHERE project_id = $project_id";
        if ($getFirstRid) {
            // return first rid for project or false if there isn't one
            $sql .= " ORDER BY rid LIMIT 1";
        } else {
            // check the supplied value is valid for project - false if not
            $sql .= " AND rid = $ridVal";
        }
        $q = db_query($sql);
		if ($q && db_num_rows($q)) {
			// Cache result when getRid() was called with a valid rid
			if ($ridVal > 0 && !isset(static::$getRidCache[$project_id])) static::$getRidCache[$project_id] = [];
			static::$getRidCache[$project_id][$ridVal] = true;
			return intval(db_result($q, 0));
		}
		return false;
    }
	private static $getRidCache = [];

    // Obtain ordinal sequence of randomization based on rid
	static function getSequenceFromRid($ridVal, $project_id=null)
	{
		if ($project_id == null) $project_id = PROJECT_ID;
        $sequenceNumber = 0; $i = 1;
        $sql = "select rid from redcap_randomization where project_id=? order by rid";
        $q = db_query($sql,[intval($project_id)]);
		while ($row = db_fetch_assoc($q)) {
            $sequenceNumber = ($row['rid']==$ridVal) ? $i : $sequenceNumber;
            $i++;
        }
		return $sequenceNumber;
    }

    // Obtain rid of randomization based on ordinal sequence
	static function getRidFromSequence($sequenceNumber=1, $project_id=null)
	{
		if ($project_id == null) $project_id = PROJECT_ID;
        $rid = 0;
        $sql = "select rid from redcap_randomization where project_id=? order by rid limit 1 offset ?";
        $q = db_query($sql,[intval($project_id), intval($sequenceNumber)-1]);
		while ($row = db_fetch_assoc($q)) {
            $rid = $row['rid'];
        }
		return $rid;
    }

	// Marks record as being randomized in allocation table
	static function randomizeRecord($rid, $record='', $fields=array(), $group_id='')
	{
		global $project_id, $status;
		$draft_preview_enabled = ($GLOBALS['draft_preview_enabled'] ?? false);
		if ($draft_preview_enabled) {
			throw new Exception("Randomization is disabled while DRAFT PREVIEW is enabled.");
		}
        $rid = self::getRid($rid);
        if (!$rid) return false; // invalid rid

        // Ensure that fields have all correct criteria fields. If not, return false to throw AJAX error msg.
		$criteriaFields = self::getRandomizationFields($rid,false,true,false);
		if (count($fields) != count($criteriaFields)) return false;
		foreach (array_keys($fields) as $field) {
			if (!in_array($field, $criteriaFields)) return false;
		}

        ## HOOK
        $hookResult = ExternalModules::callHook('redcap_module_randomize_record', [$project_id, $rid, $record, $fields, (isinteger($group_id) ? $group_id : null)]);
        /* hook results
        - null     : continue with lookup and assignment of next allocation (default)
        - false    : whoops an error occurred - will retry twice then fail
        - '0'      : standard "no available allocations" message
        - 'message': a custom failure message
        - integer  : module has performed an allocation of this allocation table entry (validate that it is valid for rid/record and fail if not) */
        if (!is_null($hookResult)) {
            if (isinteger($hookResult) && $hookResult > 0) return self::validateAllocationTableId($hookResult, $status, $rid, $record); // check external module is returning a valid aid & record allocation
            if (is_string($hookResult) || $hookResult === 0) return (string)$hookResult;
            return false; 
        }

        $aid = self::getNextAllocation($rid, $fields, $group_id);
        if ($aid === '0') return $aid; // no allocations available
        
        $result = self::updateAllocationTable($aid, 'is_used_by', $record, null, true);
        return ($aid && $result) ? $aid : false;
	}

	// Build table as content of randomization widget dialog
	static function randomizeWidgetTable($rid)
	{
		global $table_pk, $table_pk_label, $Proj, $longitudinal, $user_rights, $status;

		// Get randomization setup values first
		$randAttr = Randomization::getRandomizationAttributes($rid);

		// Get randomization fields (and their corresponding events)
		$fields = self::getRandomizationFields($rid, true);

		// Get unique list of all forms, then build ALL form elements for those forms, THEN remove ALL but the fields we need (easiest way)
		$distinctForms = array();
		$criteriaFields = array();
		$criteriaEvents = array();
		foreach ($fields as $field_desig=>$field) {
			if ($field == '') continue;
			if ($field_desig != 'target_field' && strpos($field_desig, '_event') === false) {
				// Criteria field
				$form = isset($Proj->metadata[$field]) ? $Proj->metadata[$field]['form_name'] : "";
				$distinctForms[$form] = true;
				$criteriaFields[] = $field;
				if (!$longitudinal) {
					// Criteria event
					$criteriaEvents[] = $Proj->firstEventId;
				}
			} elseif ($longitudinal && $field_desig != 'target_event' && strpos($field_desig, '_event') !== false) {
				// Criteria event
				$criteriaEvents[] = $field;
			}
		}

		// Build form elements
		$elements = array();
		foreach (array_keys($distinctForms) as $form) {
			list ($elements1, $calc_fields_this_form, $branch_fields_this_form, $chkbox_flds) = DataEntry::buildFormData($form);
			$elements = array_merge($elements, $elements1);
		}

		// Loop through all form elements and remove ALL but our criteria fields
		foreach ($elements as $key=>$attr) {
			// Remove the field if not a randomization field
			if (!isset($attr['field']) || (isset($attr['field']) && ($attr['field'] == $fields['target_field'] || !in_array($attr['field'], $fields)))) {
				unset($elements[$key]);
				continue;
			}
			// If the user has no edit form-level access to this field, then disable it
			if (UserRights::hasDataViewingRights($user_rights['forms'][$Proj->metadata[$attr['field']]['form_name']], "no-access")) {
				// Disable it
				$elements[$key]['disabled'] = 'disabled';
			}

            // If the field in the current event is a randomization target field, then disable it
            if (self::getFieldRandomizationIds($attr['field'], $randAttr['targetEvent'], null, false, true)) {
                $elements[$key]['disabled'] = 'disabled';
            }

            // If the field in the current event is already used in a completed randomization (as target or criterion), then disable it
            if (self::wasRecordRandomizedUsingField($_POST['record'], $attr['field'], $randAttr['targetEvent'])) {
                $elements[$key]['disabled'] = 'disabled';
            }
		}

		// Add context message header to table
		if (!empty($criteriaFields) || ($randAttr['group_by'] == 'DAG' && $user_rights['group_id'] == ''))
		{
			$context_msg = RCView::div(array('class'=>'blue'),
                RCView::tt('random_54') . " " . RCView::escape($table_pk_label) . RCView::SP .
                RCView::b(RCView::escape($_POST['record'])) . RCView::tt('random_55')
            );
			$context_msg_array = array(0=>array('rr_type'=>'header', 'css_element_class'=>'context_msg','value'=>$context_msg));
			$elements = array_merge($context_msg_array, $elements);
		}

		// DAG: If grouping by DAGS and user is NOT in a DAG, show drop-down of DAGS to choose from.
		if ($randAttr['group_by'] == 'DAG' && $user_rights['group_id'] == '')
		{
			// Format enum
			$dagEnum = array();
			foreach ($Proj->getGroups() as $code=>$label) {
				$dagEnum[] = "$code, $label";
			}
			// Get group_id for this record
			$group_id = '';
			$sql = "select value from ".\Records::getDataTable(PROJECT_ID)." where project_id = ".PROJECT_ID." and record = '".db_escape($_POST['record'])."'
					and field_name = '__GROUPID__' limit 1";
			$q = db_query($sql);
			if (db_num_rows($q) > 0) {
				$value = db_result($q, 0);
				// Verify that this DAG belongs to this project
				if ($Proj->getGroups($value) !== false) {
					$group_id = $value;
				}
			}
			// Set DAG element as drop-down
			$dagDropDown = array('rr_type'=>'select','value'=>$group_id,'field'=>'redcap_data_access_group','name'=>'redcap_data_access_group',
								 'label'=>RCView::tt('random_107','span',array('style'=>'color:#800000;')), 'custom_alignment'=>'', 'enum'=>implode(" \\n ", $dagEnum));

            // If DAG is already used in a completed randomization for this record, then disable the dropdown
            if (Randomization::wasRecordRandomizedByDAG($_POST['record'])) {
                $dagDropDown['label'] = RCView::tt('global_78').' <span class="text-muted font-weight-normal">('.RCView::tt('random_56').')</span>'; // "Data Access Group (Already randomized)"
                $dagDropDown['disabled'] = 'disabled';
            }

            // Add to elements
			$elements = array_merge($elements, array($dagDropDown));
		}


		// Get field/event pair data from data table
		$datasql_sub = array();
		foreach ($fields as $field_desig=>$field) {
			if (strpos($field_desig, '_event') === false) {
				if ($field_desig == 'target_field') {
					// Randomization field
					$event_id = isset($fields['target_event']) ? $fields['target_event'] : $Proj->firstEventId;
					$datasql_sub[] = "(field_name = '$field' and event_id = $event_id)";
				} else {
					// Criteria field
					list ($nothing, $field_num) = explode("source_field", $field_desig);
					$event_field = "source_event" . $field_num;
					$event_id = isset($fields[$event_field]) ? $fields[$event_field] : $Proj->firstEventId;
					$datasql_sub[] = "(field_name = '$field' and event_id = $event_id)";
				}
			}
		}
		$datasql = "select field_name, value from ".\Records::getDataTable(PROJECT_ID)." where	project_id = ".PROJECT_ID."
					and record = '".db_escape($_POST['record'])."' and (" . implode(" or ", $datasql_sub) . ")";
		//Execute query and put any existing data into an array to display on form
		$q = db_query($datasql);
		$element_data = array();
		while ($row_data = db_fetch_array($q)) {
			//Checkbox: Add data as array
			if ($Proj->metadata[$row_data['field_name']]['element_type'] == 'checkbox') {
				$element_data[$row_data['field_name']][] = $row_data['value'];
			//Non-checkbox fields: Add data as string
			} else {
				$element_data[$row_data['field_name']] = $row_data['value'];
			}
		}

		## HTML Output
		print RCView::p(array('style'=>'margin:10px 0 20px;font-family:verdana;font-size:13px;'),
            RCView::tt('random_52') . " ".RCView::escape($table_pk_label)." \"" . RCView::b(RCView::escape($_POST['record'])) . "\" " .
            RCView::tt('random_53') . " " . RCView::b($Proj->metadata[$fields['target_field']]['element_label']) .
            " (<i>" . $fields['target_field'] . "</i>)" . RCView::tt('period') . " " .
            (empty($criteriaFields) ? "" : RCView::tt('random_62'))
        );
		// Piping: If any fields have labels where data needs to be piped, then pipe in the data
		foreach ($elements as $key=>$attr) {
			// Loop through each attribute and REPLACE
			$this_form = (isset($attr['field']) && isset($Proj->metadata[$attr['field']])) ? $Proj->metadata[$attr['field']]['form_name'] : "";
			foreach (array_keys($attr) as $this_attr_type) {
				if ($this_attr_type != 'label' && $this_attr_type != 'note' && $this_attr_type != 'element_enum') continue;
				$elements[$key][$this_attr_type] = Piping::replaceVariablesInLabel($elements[$key][$this_attr_type], $_POST['record'], (isset($fields['target_event']) ? $fields['target_event'] : $Proj->firstEventId),
														1, array(), true, null, true, "", 1, false, false, $this_form);
			}
		}
		// Render field table inside the popup
		DataEntry::renderForm($elements, $element_data);
		// Append a hidden input fields containing the variable names and events of the criteria field s
		// (to be used by javascript for value checks before randomizing)
		print RCView::hidden(array('id'=>'randomCriteriaFields'.$rid, 'value'=>implode(",", $criteriaFields)));
		print RCView::hidden(array('id'=>'randomCriteriaEvents'.$rid, 'value'=>implode(",", $criteriaEvents)));
		print RCView::div(array('class'=>'space','style'=>'padding:1px;'), " ");
		// If in development, display reminder that real subjects should not be randomized yet
		if ($status < 1) {
			print RCView::p(array('class'=>'yellow'), RCView::tt('random_123'));
		}
	}

	// Returns count of how many criteria fields are utilized for an already-saved allocation table
	static function countCriteriaFields($rid)
	{
        $rid = self::getRid($rid);
		$sql = "select * from redcap_randomization where project_id = " . PROJECT_ID. " and rid = $rid";
		$q = db_query($sql);
		$fields = db_fetch_assoc($q);
		for ($k = 1; $k <= self::MAXSOURCEFIELDS; $k++) {
			if ($fields['source_field'.$k] == "") break;
		}
		return ($k-1);
	}

	// Returns attributes about saved randomization setup(s)
    private static $randAttr = [];
	static function getRandomizationAttributes($rid = null, $project_id = null)
	{
        // Build cache array from table
        if ($project_id == null) {
            global $Proj;
            if (!isset($Proj->project_id)) return [];
            $project_id = $Proj->project_id;
        } else {
            $Proj = new Project($project_id);
        }
        $rid = self::getRid($rid, $project_id, !isinteger($rid));
        // Get cached array of attributes, if exists
        if (isset(self::$randAttr[$project_id][$rid]) && is_array(self::$randAttr[$project_id][$rid]) && !empty(self::$randAttr[$project_id][$rid])) {
            return self::$randAttr[$project_id][$rid];
        }
		// First get randomization and strata
		list ($targetField, $targetEvent, $criteriaFields) = self::getRandomizationFieldsParsed($rid, $project_id);
		// Get other attributes
		$sql = "select stratified, group_by, trigger_option, trigger_instrument, trigger_event_id, trigger_logic 
                from redcap_randomization where project_id = $project_id and rid = $rid";
		$q = db_query($sql);
		if (!($q && db_num_rows($q))) return false;
		$stratified = db_result($q, 0, 'stratified');
		$group_by = db_result($q, 0, 'group_by');
        $isBlinded = ($Proj->metadata[$targetField]['element_type']=='text');
		$trigger_option = db_result($q, 0, 'trigger_option');
		$trigger_instrument = db_result($q, 0, 'trigger_instrument');
		$trigger_event_id = db_result($q, 0, 'trigger_event_id');
		$trigger_logic = db_result($q, 0, 'trigger_logic');
		// Cache attributes
        self::$randAttr[$project_id][$rid] = [
            'stratified'=>$stratified, 'group_by'=>$group_by, 'targetField'=>$targetField,
            'targetEvent'=>$targetEvent, 'strata'=>$criteriaFields, 'randomization_id'=>$rid,
            'isBlinded'=>$isBlinded, 'triggerOption'=>$trigger_option, 'triggerForm'=>$trigger_instrument,
            'triggerEvent'=>$trigger_event_id, 'triggerLogic'=>$trigger_logic
        ];
        // Return array of attributes
        return self::$randAttr[$project_id][$rid];
	}

	// Returns array of attributes about all saved randomization setup(s): key is rid
	static function getAllRandomizationAttributes($project_id = null)
	{
        if ($project_id == null) {
            if (defined("PROJECT_ID")) {
                $project_id = PROJECT_ID;
            } else {
                return [];
            }
        }
        $allRandomizationAttributes = [];
        $sql = "select rid from redcap_randomization where project_id=".intval($project_id)." order by rid";
        $q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
            $thisRandAttr = self::getRandomizationAttributes($row['rid'], $project_id);
            if ($thisRandAttr!==false) $allRandomizationAttributes[$row['rid']] = $thisRandAttr;
        }
        return $allRandomizationAttributes;
	}

	// Returns (inside array) target field, target event_id, and array of criteria fields (with variable as key and event_id as value)
	static function getRandomizationFieldsParsed($rid, $project_id = null)
	{
        if ($project_id == null) {
            $project_id = PROJECT_ID;
            global $longitudinal, $Proj;
        } else {
            $Proj = new Project($project_id);
            $longitudinal = $Proj->longitudinal;
        }
		// Place critera fields into array
		$criteriaFields = array();
		// Get all fields/events
		$randomizationCriteriaFields = self::getRandomizationFields($rid, true, true, true, $project_id);
		// Separate randomization field from criteria fields
		$targetField = is_array($randomizationCriteriaFields) ? array_shift($randomizationCriteriaFields) : null;
		$targetEvent = ($longitudinal && is_array($randomizationCriteriaFields)) ? array_shift($randomizationCriteriaFields) : $Proj->firstEventId;
		while (!empty($randomizationCriteriaFields)) {
			$field = array_shift($randomizationCriteriaFields);
			if ($field == '') continue;
			if ($longitudinal) {
				$event_id = array_shift($randomizationCriteriaFields);
				$criteriaFields[$field] = $event_id;
			} else {
				$criteriaFields[$field] = $Proj->firstEventId;
			}
		}
		// Return elements
		return array($targetField, $targetEvent, $criteriaFields);
	}

	// Returns array of randomization fields (target + all criteria fields)
	static function getRandomizationFields($rid, $returnEventIds=false,$returnCriteriaFields=true,$returnTargetField=true,$project_id=null)
	{
		if ($project_id == null) {
			$project_id = PROJECT_ID;
			global $longitudinal;
		} else {
            $proj = new \Project($project_id);
            $longitudinal = $proj->longitudinal;
		}
        $rid = self::getRid($rid);
		$sql = "select * from redcap_randomization where project_id = $project_id and rid = $rid";
		$q = db_query($sql);
        if (!db_num_rows($q)) return [];
		$fields = db_fetch_assoc($q);
		unset($fields['rid'], $fields['project_id'], $fields['stratified'], $fields['group_by'], $fields['trigger_option'], $fields['trigger_instrument'], $fields['trigger_event_id'], $fields['trigger_logic']);
		if (!$returnTargetField) {
			unset($fields['target_field']);
		}
		if (!$longitudinal || !$returnEventIds || !$returnTargetField) {
			unset($fields['target_event']);
		}
		for ($k = 1; $k <= self::MAXSOURCEFIELDS; $k++) {
			if (!$returnCriteriaFields || !isset($fields['source_field'.$k]) || $fields['source_field'.$k] == "") {
				unset($fields['source_field'.$k]);
			}
			if (!$returnCriteriaFields || (!($longitudinal && $returnEventIds && isset($fields['source_event'.$k]) && $fields['source_event'.$k] != ""))) {
				unset($fields['source_event'.$k]);
			}
		}
		return $fields;
	}

    // Returns array of randomization fields from all project randomizations (target + all criteria fields): no duplicates
	static function getAllRandomizationFields($returnEventIds=false,$returnCriteriaFields=true,$returnTargetFields=true,$project_id=null)
	{
		if ($project_id == null) {
			$project_id = PROJECT_ID;
        }
        $fields = array();
        $sql = "select rid from redcap_randomization where project_id=".intval($project_id)." order by rid ";
        $q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
            $fields[$row['rid']] = self::getRandomizationFields($row['rid'],$returnEventIds,$returnCriteriaFields,$returnTargetFields,$project_id);
        }
        return $fields;
    }

	// Render the allocation dashboard (all subjects/combinations)
	static function renderDashboardSubjects($rid)
	{
		global $longitudinal, $status, $Proj, $table_pk, $table_pk_label;
		$html = '';

		// Check if it's setup yet. If not, then there's nothing to show.
		if (!self::setupStatus($Proj->project_id, $rid))
		{
			print RCView::tt('random_50');
			return;
		}

        // show error if invalid rid supplied
        if (!$rid) {
            print RCView::div(array('class'=>'red'),RCView::tt('random_155'));
            return;
        }

        // Instructions
		$html .= RCView::p(array(),
            RCView::tt('random_45') . " " . ($status ? RCView::tt('random_36') : RCView::tt('random_35')) . RCView::tt('period') .
			" " . RCView::tt('random_41') . " " . RCView::tt('random_80')
		);

		// Get randomization setup values and make sure allocation table exists for current project status
		$randAttr = self::getRandomizationAttributes($rid);
		if ($randAttr === false || !self::allocTableExists($status, $rid))
		{
			print RCView::p(array('class'=>'yellow'),
				RCView::img(array('src'=>'exclamation_orange.png')) .
				RCView::tt('random_72') . RCView::SP . ($status > 0 ? RCView::tt('random_36') : RCView::tt('random_35')) . RCView::tt('period') . RCView::SP .
				RCView::tt('random_73') . RCView::SP . ($status > 0 ? RCView::tt('random_36') : RCView::tt('random_35')) . RCView::tt('period')
			);
			return;
		}

		// Get the form and event that the randomization field is on
		$randomForm  = $Proj->metadata[$randAttr['targetField']]['form_name'];
		$randomEvent = $randAttr['targetEvent'];

		// Get allocation table as array and parse into rows to display as table
		$allocTableRows = explode("\n", self::getAllocFileContents($rid,$status,true));

		// Shift off header fields into their own array
		$hrdFields = array_shift($allocTableRows);
		$hrdFields = explode(",", $hrdFields);

		// Get the enum choices for all randomization fields
		$randomizationEnums = array();
		foreach ($hrdFields as $rfld) {
			if ($rfld == $table_pk) continue;
			if ($rfld == 'redcap_data_access_group') {
				$randomizationEnums['redcap_data_access_group'] = $Proj->getGroups();
			} else {
				$randomizationEnums[$rfld] = parseEnum($Proj->metadata[$rfld]['element_enum']);
			}
		}

		// Display allocated values as a table
		$tableData = array();
		foreach ($allocTableRows as $delimVals)
		{
			if ($delimVals == "") continue;
			// Convert delimited values into array
			$row = explode(",", $delimVals);
			// Get allocated record, if any
			$record = array_shift($row);
			// Set completion icon for this row
			if ($record != "") {
				$completed = "<span style='display:none;'>1</span>".RCView::img(array('src'=>'accept.png','style'=>'vertical-align:middle;'));
				$recordLink = "<a style='font-size:11px;text-decoration:underline;' href='".APP_PATH_WEBROOT."DataEntry/index.php?pid=".PROJECT_ID."&page=$randomForm&event_id=$randomEvent&id=$record'>$record</a>";
			} else {
				$completed = "<span style='display:none;'>0</span>".RCView::img(array('src'=>'stop_gray.png','class'=>'opacity50','style'=>'vertical-align:middle;'));
				$recordLink = "";
			}
			// Set data values for each column and prepend with enum label
			$vals = array();
			$fldnum = 1;
			foreach ($row as $keynum=>$val) {
				$field_name = $hrdFields[$fldnum];
				$vals[] = RCView::div(array('class'=>'gridwrap'),
							(isset($randomizationEnums[$field_name][$val]) ? $randomizationEnums[$field_name][$val] : "???")
							. RCView::span(array('style'=>'color:#777;'), " ($val)")
						  );
				$fldnum++;
			}
			// Add values to data array
			$tableData[] = array_merge(array($completed, $recordLink), $vals);
		}
		// print_array($tableData);
		// exit;
		// Set width of each field column
		$colWidth = 150;
		// Set up the table headers
		$tableHeaders = array(
			array(30, "", "center"),
			array(100, RCView::div(array('class'=>'gridwrap','style'=>'color:#800000;'),
				RCView::b($table_pk_label) . RCView::SP . RCView::SP . "($table_pk)")
			)
		);
		// Loop through each column
		foreach ($hrdFields as $field)
		{
			// Skip PK field (already added)
			if ($field == $table_pk) continue;
			// Customize field label for display
			if ($field == 'redcap_data_access_group') {
				$headerText = RCView::b(RCView::tt('global_78')) . RCView::SP . RCView::SP . RCView::span(array('class'=>'badge badge-secondary RandSummaryBadge'),'redcap_data_access_group');
			} else {
				$label = $Proj->metadata[$field]['element_label'];
				if (mb_strlen($label) > 40) $label = mb_substr($label, 0, 38) . "...";
				$headerText = RCView::b($label) . RCView::SP . RCView::SP . RCView::span(array('class'=>'badge badge-secondary RandSummaryBadge'),$field);
			}
			// Add header
			$header = RCView::div(array('class'=>'gridwrap'), $headerText);
			$tableHeaders[] = array($colWidth, $header);
		}
		// Set the table width
		$width = (130+(2*12)) + (count($tableHeaders)-2)*($colWidth+12);
		// Get html for the resources table
		$html .= renderGrid("randomization_dashboard_all", "", $width, "auto", $tableHeaders, $tableData, true, true, false);
		// Output to page
		print $html;
	}

	// Render the allocation dashboard (in groups)
	static function renderDashboardGroups($rid)
	{
		global $status, $Proj;
		$html = '';

		// Check if it's setup yet. If not, then there's nothing to show.
		if (!self::setupStatus($Proj->project_id, $rid))
		{
			print RCView::tt('random_50');
			return;
		}

        // show error if invalid rid supplied
        if (!$rid) {
            print RCView::div(array('class'=>'red'),RCView::tt('random_155'));
            return;
        }

		// Instructions
		$html .= RCView::p(array('class'=>'mt-0 mb-3'),
            RCView::tt('random_45') . " " . ($status ? RCView::tt('random_36') : RCView::tt('random_35')) . RCView::tt('period') .
			" " . RCView::tt('random_43') . " " . RCView::tt('random_80')
		);

		// Get randomization setup values and make sure allocation table exists for current project status
		$randAttr = self::getRandomizationAttributes($rid);
        $isBlinded = $randAttr['isBlinded'];
		if ($randAttr === false || !self::allocTableExists($status, $rid))
		{
			print RCView::p(array('class'=>'yellow'),
				RCView::img(array('src'=>'exclamation_orange.png')) .
				RCView::tt('random_72') . RCView::SP . ($status > 0 ? RCView::tt('random_36') : RCView::tt('random_35')) . RCView::tt('period') . RCView::SP .
				RCView::tt('random_73') . RCView::SP . ($status > 0 ? RCView::tt('random_36') : RCView::tt('random_35')) . RCView::tt('period')
			);
			return;
		}

		// Obtain all allocated values and put into array
		$critFldsSql = array();
		for ($k = 1; $k <= count($randAttr['strata']); $k++) {
			$critFldsSql[] = "a.source_field" . $k;
		}
		$critFldsSql = (empty($critFldsSql) ? "" : ", " . implode(", ", $critFldsSql));

		// Add group_id to query if grouping by DAG
		$groupIdSql = ($randAttr['group_by'] == 'DAG') ? ", a.group_id" : "";
        
        // Query to get allocated values
		$sql = "select a.is_used_by $groupIdSql $critFldsSql , a.target_field
				from redcap_randomization_allocation a, redcap_randomization r
				where r.project_id = " . PROJECT_ID . " and r.rid = " . intval($rid) . " and a.project_status = $status
				and r.rid = a.rid order by 1 $groupIdSql $critFldsSql , a.target_field";
		$q = db_query($sql);
		$allocValues = array();
		$hdrFields = array();
		$records = array();
		if (db_num_rows($q) > 0)
		{
			while ($row = db_fetch_assoc($q))
			{
				// Has it been used yet?
				$is_used_by = $row['is_used_by'];
				// Remove is_used_by in array so we can isolate just target and criteria field values
				unset($row['is_used_by']);
                // if blinded, also remove target field - show total used/not per stratum, not per stratum and group
                if ($isBlinded) unset($row['target_field']);
				// Create array of the fields returned
				if (empty($hdrFields)) {
					$hdrFields = array_keys($row);
				}
				// Put values into string delimited by comma
				$delimVals = implode(",", $row);
				// Now put delimited values into array key to count instances of each
				if (isset($allocValues[$delimVals])) {
					//$allocValues[$delimVals]['count']++;
					if (!empty($is_used_by)) {
						$allocValues[$delimVals]['used']++;
					} else {
						$allocValues[$delimVals]['not_used']++;
					}
				} else {
					//$allocValues[$delimVals]['count'] = 1;
					if (!empty($is_used_by)) {
						$allocValues[$delimVals]['used'] = 1;
						$allocValues[$delimVals]['not_used'] = 0;
					} else {
						$allocValues[$delimVals]['used'] = 0;
						$allocValues[$delimVals]['not_used'] = 1;
					}
				}
				// Add record names for this row
				if ($is_used_by != "") {
					$records[$delimVals][] = $is_used_by;
				}
			}

			// Get array of randomization fields (target + criteria fields)
			$randomizationFieldsNums = self::getRandomizationFields($rid);
            $randomizationFields = $randomizationFieldsNums;
            $target_field = $randomizationFieldsNums['target_field'];
            unset($randomizationFieldsNums['target_field']);
            $stratCount = count($randomizationFieldsNums);
			// Create array of randomization fields with numbers as keys
			$randomizationFieldsNums = array_values($randomizationFieldsNums);
            if (!$isBlinded) $randomizationFieldsNums[] = $target_field; // open - show target column last; blinded - do not show separation by allocation group

			// Get the enum choices for all randomization fields
			$randomizationEnums = array();
			foreach ($randomizationFields as $rfld) {
				$randomizationEnums[$rfld] = array();
				if (isset($Proj->metadata[$rfld])) {
					foreach (parseEnum($Proj->metadata[$rfld]['element_enum']) as $key => $val) {
						$randomizationEnums[$rfld][$key] = strip_tags(label_decode($val));
					}
				}
			}
			// If grouping by DAG, then add DAG to rand field enums
			if ($randAttr['group_by'] == 'DAG') {
				array_unshift($randomizationFieldsNums, 'group_id');
				$randomizationEnums['group_id'] = $Proj->getGroups();
			}

			// Get the form and event that the randomization field is on
			$randomForm  = $Proj->metadata[$randAttr['targetField']]['form_name'];
			$randomEvent = $randAttr['targetEvent'];

			// Display allocated values as a table
			$tableData = array();
			foreach ($allocValues as $delimVals=>$attr)
			{
				// Add some styling
				$completed = ($attr['not_used'] == 0) ? "<span style='display:none;'>1</span>".RCView::img(array('src'=>'accept.png','style'=>'vertical-align:middle;')) : "<span style='display:none;'>0</span>".RCView::img(array('src'=>'stop_gray.png','class'=>'opacity50','style'=>'vertical-align:middle;'));
				$attr['not_used'] = RCView::span(array('style'=>'color:red;'), $attr['not_used']);
				// Set data values for each column and prepend with enum label
				$vals = array();
				$fldnum = 0;
                if ($delimVals != '') {
                    foreach (explode(",", $delimVals) as $val) {
                        $field_name = $randomizationFieldsNums[$fldnum];
                        $vals[] = RCView::div(array('class'=>'gridwrap'),
                                    (isset($randomizationEnums[$field_name][$val]) ? $randomizationEnums[$field_name][$val] : "???")
                                    . RCView::span(array('style'=>'color:#777;'), " ($val)")
                                );
                        $fldnum++;
                    }
                }
				// Set records allocated for this row
				$allocRecs = "";
				if (isset($records[$delimVals])) {
					foreach ($records[$delimVals] as $record) {
                        if (ends_with($record, '-UNAVAILABLE')) {
                            $attr['used']--;
                        } else {
    						$allocRecs .= "<a style='font-size:11px;text-decoration:underline;' href='".APP_PATH_WEBROOT."DataEntry/index.php?pid=".PROJECT_ID."&page=$randomForm&event_id=$randomEvent&id=$record'>$record</a>, ";
                        }
					}
					$allocRecs = substr($allocRecs, 0, -2);
				}
                $attr['used'] = RCView::span(array('style'=>'color:green;'), $attr['used']);

                // Add values to data array
                if (UserRights::isSuperUserNotImpersonator()) {
                    // allow superusers to view allocation table
                    $stratum = explode(",", $delimVals);
                    if (!$randAttr['isBlinded']) {
                        // if open allocation then remove the last elem of $delimVals - the target field field value
                        unset($stratum[count($stratum)-1]); 
                    }
                    $viewLink = '<a class="btn btn-xs" href="'.APP_PATH_WEBROOT.'Randomization/view_allocation_table.php?pid='.$Proj->project_id.'&rid='.$rid.'&stratum='.implode(',',$stratum).'"><i class="fas fa-table"></i></a>';
                    $tableData[] = array_merge(array($completed, $attr['used'], $attr['not_used'], RCView::div(array('class'=>'wrap'), $allocRecs)), $vals, [$viewLink]);
                } else {
                    $tableData[] = array_merge(array($completed, $attr['used'], $attr['not_used'], RCView::div(array('class'=>'wrap'), $allocRecs)), $vals);
                }
			}
			// Set width of each field column
			$colWidth = 150;
			// Set up the table headers
			$tableHeaders = array(
				array(30, "", "center"),
				array(50, RCView::tt('random_46','span',array('class'=>'font-weight-bold')), "center", "int"),
				array(50, RCView::tt('random_47','span',array('class'=>'font-weight-bold')), "center", "int"),
				array(100, RCView::tt('random_127','span',array('class'=>'font-weight-bold')))
			);
			// If grouping by DAG, then add DAG as header
			if ($randAttr['group_by'] == 'DAG') {
				// Add header
				$header = RCView::div(array('class'=>'gridwrap'), RCView::span(array(),RCView::b(RCView::tt('global_78')) . RCView::SP . RCView::SP . RCView::span(array('class'=>'badge badge-secondary RandSummaryBadge'),'redcap_data_access_group')));
				$tableHeaders[] = array($colWidth, $header);
			}
			// Set column number of randomization field
			$randFieldColNum = 4 + $stratCount + (($randAttr['group_by'] == 'DAG') ? 1 : 0);
            // Place randomization field at end of table
            unset($randomizationFields['target_field']);
            $randomizationFields['target_field'] = $target_field;
			// Loop through each column
			foreach ($randomizationFields as $fKey => $field)
			{
				if ($field == '') continue;
				if ($isBlinded && $fKey == 'target_field') continue; // no coloumn for allocation group when blinded
				// Customize field label for display
				$label = isset($Proj->metadata[$field]) ? strip_tags(label_decode($Proj->metadata[$field]['element_label'])) : "";
				if (mb_strlen($label) > 40) {
					$label = mb_substr($label, 0, 38) . "...";
					// Prevent causing issues with two-byte characters
					if (function_exists('mb_detect_encoding') && mb_detect_encoding($Proj->metadata[$field]['element_label']) == 'UTF-8' && mb_detect_encoding($label) == 'ASCII') {
						// Revert to original
						$label = $Proj->metadata[$field]['element_label'];
					}
				}
				// Set header text
				if (count($tableHeaders) == $randFieldColNum) {
                    // Make randomization field's font color red and show field name in blue badge rather than grey
                    $headerText = RCView::b(RCView::span(array('style'=>'color:#800000;'),$label)) . RCView::SP . RCView::SP . RCView::span(array('class'=>'badge badge-primary RandSummaryBadge'),$field);
				} else {
                    $headerText = RCView::b(RCView::span(array(),$label)) . RCView::SP . RCView::SP . RCView::span(array('class'=>'badge badge-secondary RandSummaryBadge'),$field);
                }
				// Add header
				$header = RCView::div(array('class'=>'gridwrap'), $headerText);

				$tableHeaders[] = array($colWidth, $header);
			}
            if (UserRights::isSuperUserNotImpersonator()) {
                // allow superusers to view allocation table
                $superColWidth = 80;
                $header = RCView::div(array('class'=>'gridwrap font-weight-bold text-center'), RCView::tt('dash_03').RCView::div(array('class'=>'text-muted font-weight-normal'),RCView::tt('random_165'))); // View
				$tableHeaders[] = array($superColWidth, $header, "center");
                $nonFieldHeaderCount = 5;
            } else {
                $nonFieldHeaderCount = 4;
                $superColWidth = 0;
            }
			// Set the table width
			$width = 279 + (count($tableHeaders)-$nonFieldHeaderCount)*($colWidth+12) + (1.25*$superColWidth);
			// Get html for the resources table
			$html .= renderGrid("randomization_dashboard", "", $width, "auto", $tableHeaders, $tableData, true, true, false);
		}
		// Output to page
		print $html;
	}

	// Return the instructions for top of page
	static function renderInstructions()
	{
		return	RCView::p(array(),
                    RCView::tt('random_01') .
					RCView::SP . RCView::span(array('style'=>'color:#800000;'), RCView::tt('random_129')) . RCView::SP .
					RCView::a(array('href'=>'javascript:;','onclick'=>" $('#instrdetails').toggle('fade');",'style'=>"text-decoration:underline;"), RCView::tt('survey_86'))
				) .
				RCView::div(array('style'=>'display:none;max-width:820px;border:1px solid #ccc;background-color:#eee;padding:0 10px;margin-bottom:20px;','id'=>'instrdetails'),
					RCView::p(array(), RCView::b(RCView::tt('random_22')) . RCView::br() .  RCView::tt('random_24')) .
					RCView::p(array(), RCView::b(RCView::tt('random_25')) . RCView::br() .  RCView::tt('random_28')) .
					RCView::p(array(), RCView::b(RCView::tt('random_16')) . RCView::br() .  RCView::tt('random_101')) .
					RCView::p(array(), RCView::b(RCView::tt('random_102')) . RCView::br() .  RCView::tt('random_103'))
				);
	}

	// Render the steps for setting up randomization
	static function renderSetupSteps($rid)
	{
		global $status, $Proj, $longitudinal;
		$html = '';

		// Get list of DAGS (to use in multi-site step)
		$dags = $Proj->getGroups();

		// Get criteria fields and target fields (and their events), if already set up
		$randomizationAttributes = ($rid>0) ? self::getRandomizationAttributes($rid) : false;
		$group_by_field_event = $targetField = $targetEvent = "";
		if ($randomizationAttributes !== false) {
			$targetField = $randomizationAttributes['targetField'];
			$targetEvent = $randomizationAttributes['targetEvent'];
			$criteriaFields = $randomizationAttributes['strata'];
			$disableSetup = true;
			// If grouping by a field, then remove last strata field to make it the group-by field
			if ($randomizationAttributes['group_by'] == 'FIELD') {
                $criteriaFieldsKeys = array_keys($criteriaFields);
				$group_by_field_name = array_pop($criteriaFieldsKeys);
				$group_by_field_event = array_pop($criteriaFields);
				unset($randomizationAttributes['strata'][$group_by_field_name]);
			}
		} else {
			$disableSetup = false;
		}

		## STEP 1
		// If already uploaded alloc table, then give note about why drop-downs are disabled
		if ($disableSetup) {
			$html .= RCView::p(array('class'=>'yellow','style'=>'font-size:arial;margin:0 0 15px;font-size:10px;'),
                        RCView::tt('random_74') . RCView::SP . ($status > 0 ? RCView::tt('random_76') : RCView::tt('random_75'))
					);
		}

		// 1A setup: (Stratified only) Text and field drop-down with button to add another drop-down
		$stratStepHtml = "";
		if (empty($criteriaFields)) {
			$stratStepHtml .= self::renderSingleDropDown();
			// Button to add more criteria fields
			$stratStepHtml .= RCView::div(array('style'=>'margin-left:15px;'),
						RCView::button(array('id'=>'addMoreFields','style'=>'font-size:11px;','onclick'=>'return false;'), RCView::tt('random_03'))
					);
		} else {
			// Render all saved criteria fields and disable the drop-downs with field pre-selected
			foreach ($criteriaFields as $fld=>$evt) {
				$stratStepHtml .= self::renderSingleDropDown('randomVar',null,$fld,$evt,true,false,$rid);
			}
		}

		// Set scheme radio button's pre-defined values
		$scheme_selected = '';
		$scheme_disabled = '';
		if ($disableSetup) {
			$scheme_disabled = 'disabled';
			if ($randomizationAttributes['stratified']) {
				$scheme_selected = 'checked';
			}
		}

		// Set pre-fill values for Randomize by Group
		$show_group_by = ($disableSetup && $randomizationAttributes['group_by'] != '');
		$group_by_checked = ($show_group_by) ? 'checked' : '';
		$group_by_disabled = ($disableSetup) ? 'disabled' : '';
		$group_by_dag_disabled = ($show_group_by) ? 'disabled' : '';
		$group_by_field_disabled = ($show_group_by) ? 'disabled' : '';
		$group_by_dag_checked = ($show_group_by && $randomizationAttributes['group_by'] == 'DAG') ? 'checked' : '';
		$group_by_field_checked = ($show_group_by && $randomizationAttributes['group_by'] == 'FIELD') ? 'checked' : '';
		$group_by_field_name = ($show_group_by && $randomizationAttributes['group_by'] == 'FIELD') ? $group_by_field_name : '';

		// Save and erase button disable
		$saveSetup = ($disableSetup) ? 'disabled' : '';
		$saveSetupClass = ($disableSetup) ? 'opacity50' : '';
		$eraseSetup = ($disableSetup && $status < 1) ? '' : 'disabled';
		$realtimeSetup = ($disableSetup) ? 'disabled' : '';

		// Only allow super users to download allocation table when in production
		$downloadAllocTableProd = ($status < 1 || ($status > 0 && SUPER_USER)) ? "" : "disabled";

		// 1A: Choose randomization scheme (block vs. stratified)
		$html .= RCView::div(array(),
                    RCView::tt('random_78','span',array('class'=>'font-weight-bold')) . RCView::SP . RCView::SP .
					RCView::checkbox(array('name'=>'scheme',$scheme_selected=>$scheme_selected,$scheme_disabled=>$scheme_disabled)) .
					RCView::div(array('style'=>'margin-left:15px;padding:3px 0;'),
                        RCView::tt('random_130') . RCView::SP .
						RCView::a(array('id'=>'schemeTellMore','href'=>'javascript:;','onclick'=>"$('#schemeExplain').toggle('fade');",'style'=>'text-decoration:underline;color:#800000;font-family:tahoma;font-size:10px;'), RCView::tt('global_58'))
					) .
					RCView::div(array('id'=>'schemeExplain','style'=>'color:#800000;border:1px solid #bbb;padding:4px;margin:4px 0 4px 15px;display:none;'),  RCView::tt('random_79')) .
					RCView::div(array('style'=>''),
						RCView::div(array('id'=>'stratStep','style'=>(($disableSetup && $randomizationAttributes['stratified']) ? '' : 'display:none;').'margin:8px 0 0;color:#800000;'),
							RCView::div(array('style'=>'margin-left:15px;'), RCView::b(RCView::tt('random_06')) . RCView::SP . RCView::tt('random_131')) .
							$stratStepHtml
						)
					)
				);

		// 1B: Is multi-site study? If so, use DAG or "DAG"-field
		$html .= RCView::div(array('style'=>'margin:10px 0 0;'),
                    RCView::tt('random_81','span',array('class'=>'font-weight-bold')) . RCView::SP . RCView::SP .
					RCView::checkbox(array('id'=>'multisite_chk',$group_by_checked=>$group_by_checked,$group_by_disabled=>$group_by_disabled)) .
					RCView::div(array('style'=>'margin-left:15px;padding:3px 0;'), RCView::tt('random_92')) .
					RCView::div(array('id'=>'multisite_options','style'=>'color:#800000;margin-left:15px;'.($show_group_by ? '' : 'display:none;')),
						RCView::radio(array('name'=>'multisite','id'=>'multisite_dag','value'=>'dag',$group_by_dag_checked=>$group_by_dag_checked,$group_by_dag_disabled=>$group_by_dag_disabled,'onclick'=>(count($dags) > 0 ? '' : 'noDagWarning()'))) . RCView::tt('random_83') . RCView::b(count($dags)) . RCView::SP . RCView::tt('random_84') . RCView::br() .
						RCView::radio(array('name'=>'multisite','id'=>'multisite_field','value'=>'field',$group_by_field_checked=>$group_by_field_checked,$group_by_field_disabled=>$group_by_field_disabled)) . RCView::tt('random_82') .
						self::renderSingleDropDown('randomVar','dagField',$group_by_field_name,$group_by_field_event,$disableSetup,false,$rid)
					)
				 );

		// 1C: Render the target randomization variable drop-down
		$html .= RCView::div(array('style'=>'margin:10px 0 0;'),
					RCView::tt('random_04','span',array('class'=>'font-weight-bold')) .
					RCView::div(array('style'=>'margin-left:15px;'), RCView::tt('random_209'))
				) .
			    self::renderSingleDropDown('targetField','targetField',$targetField,$targetEvent,($targetField != ''),true,$rid);
        
        $html .= RCView::div(array('id'=>'errorDuplicateFieldEvt','style'=>'display:none;margin:5px 15px;','class'=>'red'),($Proj->longitudinal)?RCView::tt('random_164'):RCView::tt('random_163'));
        if ($targetField != '') {
            // indicate whether open or blinded allocation based on type of allocation field
            $targetFieldType = $Proj->metadata[$targetField]['element_type'];
            if ($targetFieldType == 'text') {
                $targetFieldTypeMessage = '<td class="text-center"><i class="fas fa-envelope text-danger mr-1"></i>'.RCView::tt('random_138').'</td>';
            } else {
                $targetFieldTypeMessage = '<td class="text-center"><i class="fas fa-envelope-open text-success mr-1"></i>'.RCView::tt('random_137').'</td>';
            }
            $html .= RCView::div(array('style'=>'margin-left:15px;'), 
                RCView::p(array('class'=>'text-muted'), $targetFieldTypeMessage));
        }

		// 1E: Save and Erase buttons (can only erase in Dev or if a super user)
        $eraseBtnClick = $status < 1 ? 'return eraseSetup('.$rid.');' : '';
		$eraseBtn = RCView::button(array('class'=>'btn btn-xs btn-link fs14 text-secondary',$eraseSetup=>$eraseSetup,'onclick'=>$eraseBtnClick), RCView::fa('fa-regular fa-trash-can mr-1').RCView::tt('random_94',''));
		$html .= RCView::div(array('style'=>'margin:20px 0 0;'),
					RCView::button(array('id'=>'saveModelBtn','class'=>'btn btn-xs btn-rcgreen fs14 '.$saveSetupClass,$saveSetup=>$saveSetup,'onclick'=>'return checkVarsSelected();'), RCView::fa('fa-regular fa-floppy-disk mr-1').RCView::tt('random_93','')) . RCView::SP . RCView::SP .
					$eraseBtn
				 );

		// Finalize Step 1: Put all step 1 substeps in a div together
		$html = RCView::div(array('class'=>'round chklist','style'=>'background-color:#eee;border:1px solid #ccc;padding:5px 15px 15px;'),
					RCView::p(array('style'=>'color:#800000;font-weight:bold;font-size:13px;'), RCView::tt('random_29')) .
					RCView::p(array('style'=>'margin-bottom:15px;'), RCView::tt('random_31')) .
					RCView::form(array('name'=>'random_step1','action'=>APP_PATH_WEBROOT."Randomization/save_randomization_setup.php?pid=".PROJECT_ID,'method'=>'post','enctype'=>'multipart/form-data'), $html)
				);

		## STEP 2: DOWNLOAD TEMPLATE FILES: Add form with button to generate file
		$html .= RCView::div(array('id'=>'step2div','class'=>'round chklist','style'=>'background-color:#eee;border:1px solid #ccc;padding:5px 15px 15px;'),
					RCView::p(array('style'=>'color:#800000;font-weight:bold;font-size:13px;'), RCView::tt('random_07')) .
					RCView::p(array(),
                        RCView::tt('random_33') . RCView::SP .
						RCView::a(array('href'=>'javascript:;','onclick'=>" $('#step2details').toggle('fade');",'style'=>"text-decoration:underline;"), RCView::tt('survey_86'))
					) .
					RCView::p(array('style'=>'display:none;','id'=>'step2details'), RCView::tt('random_100')) .
					RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs13','onclick'=>"window.location.href=app_path_webroot+'Randomization/download_allocation_file_template.php?pid='+pid+'&rid=$rid&example_num=1';"), RCView::tt('random_05')) . RCView::SP .
					RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs13','onclick'=>"window.location.href=app_path_webroot+'Randomization/download_allocation_file_template.php?pid='+pid+'&rid=$rid&example_num=2';"), RCView::tt('random_34')) . RCView::SP .
					RCView::button(array('class'=>'btn btn-xs btn-defaultrc fs13','onclick'=>"window.location.href=app_path_webroot+'Randomization/download_allocation_file_template.php?pid='+pid+'&rid=$rid&example_num=3';"), RCView::tt('random_104'))
				 );

		## STEP 3: FILE UPLOAD BUTTON
		$devAllocTableExists = self::allocTableExists(0, $rid);
		$prodAllocTableExists = self::allocTableExists(1, $rid);
		$html .= RCView::div(array('id'=>'step3div','class'=>'round chklist','style'=>'background-color:#eee;border:1px solid #ccc;padding:5px 15px 15px;'),
					RCView::p(array('style'=>'color:#800000;font-weight:bold;font-size:13px;'), RCView::tt('random_30')) .
					RCView::p(array('style'=>''), RCView::tt('random_32')) .
					// Reminder bullet list
					RCView::div(array('style'=>'margin-bottom:15px;'),
						RCView::div(array('style'=>'font-weight:bold;'), RCView::tt('random_116')) .
						RCView::div(array('style'=>'margin-left:20px;text-indent:-8px;'), " &bull; " . RCView::tt('random_113')) .
						RCView::div(array('style'=>'margin-left:20px;text-indent:-8px;'), " &bull; " . RCView::tt('random_114')) .
						RCView::div(array('style'=>'margin-left:20px;text-indent:-8px;'), " &bull; " . RCView::tt('random_115'))
					) .
					// Dev alloc file
					RCView::table(array('id'=>'devAllocUploadTable','cellspacing'=>'0','style'=>'border-top:1px dashed #bbb;padding:5px 0;'),
						RCView::tr(array(),
							RCView::td(array('valign'=>'top','style'=>'padding:15px;text-align:center;width:100px;'),
								RCView::img(array('src'=>($devAllocTableExists ? 'checkbox_checked.png' : 'checkbox_cross.png'))) .
								RCView::div(array(), ($devAllocTableExists ? RCView::tt('random_44','span',array('style'=>'color:green')) : RCView::tt('random_39','span',array('style'=>'color:red'))))
							) .
							RCView::td(array('valign'=>'top','style'=>'padding:15px;'),
								RCView::div(array('style'=>''),
									RCView::div(array('style'=>'margin-bottom:5px;'), RCView::b(RCView::tt('random_08') . RCView::SP . RCView::tt('random_35'))) .
									($devAllocTableExists
										?
										// Already uploaded (with Download button)
										RCView::div(array(),
											(($status < 1)
												?
												RCView::SP . RCView::SP . RCView::SP .
												RCView::a(array("href"=>"javascript:;","style"=>"color:#800000;font-size: 11px;text-decoration:underline;","onclick"=>"delAllocFile($rid,0);"), RCView::tt('random_64')) .
												RCView::SP . RCView::SP . RCView::button(array('onclick'=>"window.location.href=app_path_webroot+'Randomization/download_allocation_file.php?pid='+pid+'&rid=$rid&status=0';"), RCView::tt('random_63'))
												:
												RCView::button(array('disabled'=>'disabled','onclick'=>"window.location.href=app_path_webroot+'Randomization/download_allocation_file.php?pid='+pid+'&rid=$rid&status=0';"), RCView::tt('random_63'))
											)
										)
										:
										// Upload file form
										(($status < 1 || SUPER_USER)
											?
											RCView::form(array('name'=>'form', 'id'=>'devUploadForm', 'action'=>APP_PATH_WEBROOT."Randomization/upload_allocation_file.php?pid=".PROJECT_ID."&rid=$rid",'method'=>'post','enctype'=>'multipart/form-data'),
												RCView::div(array(), RCView::file(array('type'=>'file','id'=>'allocFileDev','name'=>'allocFile'))) .
												RCView::div(array('class'=>'mt-1'), RCView::button(array('id'=>'uploadFileBtn','class'=>'btn btn-xs btn-primaryrc fs14','onclick'=>'setTimeout(function(){$("#uploadFileBtn").prop("disabled",true);},100);return checkFileUploadExt(0);'), RCView::fa('fa-solid fa-upload mr-1').RCView::tt('random_09',''))) .
												RCView::hidden(array('value'=>'0', 'name'=>'alloc_status'))
											)
											:
											RCView::span(array('style'=>'color:#800000;'),
												RCView::img(array('src'=>'exclamation.png')) . RCView::tt('random_77')
											)
										)
									)
								)
							)
						)
					) .
					// Prod alloc file
					RCView::table(array('cellspacing'=>'0','style'=>'border-top:1px dashed #bbb;padding:5px 0;'),
						RCView::tr(array(),
							RCView::td(array('valign'=>'top','style'=>'padding:15px;text-align:center;width:100px;'),
								RCView::img(array('src'=>($prodAllocTableExists ? 'checkbox_checked.png' : 'checkbox_cross.png'))) .
								RCView::div(array(), ($prodAllocTableExists ? RCView::tt('random_44','span',array('style'=>'color:green')) : RCView::tt('random_39','span',array('style'=>'color:red'))))
							) .
							RCView::td(array('valign'=>'top','style'=>'padding:15px;'),
								RCView::div(array('style'=>''),
									RCView::div(array('style'=>'margin-bottom:5px;'), RCView::b(RCView::tt('random_08') . RCView::SP . RCView::tt('random_36'))) .
									($prodAllocTableExists
										?
										// Already uploaded (with Download button)
										RCView::div(array(),
											(($status < 1)
												?
												RCView::SP . RCView::SP . RCView::SP .
												RCView::a(array("href"=>"javascript:;","style"=>"color:#800000;font-size: 11px;text-decoration:underline;","onclick"=>"delAllocFile($rid,1);"), RCView::tt('random_64')) .
												RCView::SP . RCView::SP . RCView::button(array('onclick'=>"window.location.href=app_path_webroot+'Randomization/download_allocation_file.php?pid='+pid+'&rid=$rid&status=1';"), RCView::tt('random_63'))
												:
												RCView::button(array($downloadAllocTableProd=>$downloadAllocTableProd,'onclick'=>"window.location.href=app_path_webroot+'Randomization/download_allocation_file.php?pid='+pid+'&rid=$rid&status=1';"), RCView::tt('random_63')) .
												RCView::SP . RCView::tt('random_126','span',array('style'=>'color:#333;font-size:11px;'))
											)
										)
										:
										""
									) .
									// Upload file form (if in dev status OR allow super users to ADD while in prod)
									((($status < 1 && !$prodAllocTableExists) || SUPER_USER)
										?
										(($status > 0 && SUPER_USER)
											?
											RCView::div(array('style'=>'padding:5px 0;'), RCView::tt('random_125')) .
											RCView::div(array('style'=>'padding:5px 0;'),
												RCView::a(array('href'=>'javascript:;','style'=>'color:#800000;text-decoration:underline;','onclick'=>" $('#prodUploadForm').toggle('fade');"),
                                                    RCView::tt('random_124')
												)
											)
											:
											""
										) .
										RCView::form(array('style'=>((($status < 1 && $prodAllocTableExists) || ($status > 0 && SUPER_USER)) ? "display:none;" : ""), 'id'=>'prodUploadForm', 'name'=>'form','action'=>APP_PATH_WEBROOT."Randomization/upload_allocation_file.php?pid=".PROJECT_ID."&rid=$rid",'method'=>'post','enctype'=>'multipart/form-data'),
											RCView::div(array(), RCView::file(array('type'=>'file','id'=>'allocFileProd','name'=>'allocFile'))) .
											RCView::div(array('class'=>'mt-1'), RCView::button(array('id'=>'uploadFileBtn2','class'=>'btn btn-xs btn-primaryrc fs14','onclick'=>'setTimeout(function(){$("#uploadFileBtn2").prop("disabled",true);},100);return checkFileUploadExt(1);'), RCView::fa('fa-solid fa-upload mr-1').RCView::tt('random_09',''))) .
                                            RCView::hidden(array('value'=>'1', 'name'=>'alloc_status'))
										)
										:
										($status > 0
											?
											RCView::span(array('style'=>'color:#800000;'),
												RCView::img(array('src'=>'exclamation.png')) . RCView::tt('random_77') .
												RCView::SP . RCView::tt('random_125')
											)
											:
											""
										)
									)
								)
							)
						)
					)
				);

        ## Step 4: Realtime Randomization
        $realtimeOpts = array(
            0 => RCView::tt('random_202',false),
            1 => RCView::tt('random_203',false),
            2 => RCView::tt('random_204',false)
        );
        $realtimeOpt = $randomizationAttributes['triggerOption'] ?? '';
        $realtimeForm = $randomizationAttributes['triggerForm'] ?? '';
        $realtimeEvent = $randomizationAttributes['triggerEvent'] ?? '';
        $realtimeLogic = $randomizationAttributes['triggerLogic'] ?? '';
        $logicDisplay = ($realtimeOpt > 0) ? 'block' : 'none';
        $forms = array();
        foreach ($Proj->forms as $formName => $formAttr) {
            $forms[$formName] = $formAttr['menu'];
        }
        $form_dd = RCView::select(array('class'=>'mr-1', 'style'=>'max-width:400px;', 'id'=>'realtime-form', 'name'=>'realtime-form'), $forms, $realtimeForm, 45);;
        if ($longitudinal) {
			$event_dd_info = array();
			foreach ($Proj->eventInfo as $event_id=>$attr) {
				$event_dd_info[$event_id] = $attr['name_ext'];
			}
			$event_dd = RCView::select(array('class'=>'ml-1', 'style'=>'max-width:200px;', 'id'=>'realtime-event', 'name'=>'realtime-event'), $event_dd_info, $realtimeEvent, 40);
        } else {
            $event_dd = RCView::input(array('type'=>'hidden', 'id'=>'realtime-event', 'name'=>'realtime-event', 'value'=>$Proj->firstEventId));
        }

        $html .= RCView::div(array('id'=>'step4div','class'=>'round chklist','style'=>'background-color:#eee;border:1px solid #ccc;padding:5px 15px 15px;'),
                    RCView::p(array('style'=>'color:#800000;font-weight:bold;font-size:13px;'), RCView::tt('random_199')) .
                    RCView::p(array('class'=>'mb-3'), RCView::tt('random_214')) .
                    RCView::div(array('class'=>'mb-3'),
                        RCView::span(array('class'=>'font-weight-bold', 'style'=>'display:inline-block;min-width:120px;'), RCView::tt('random_201')) . 
                        RCView::select(array('class'=>'mr-2','style'=>'max-width:400px;top: 2px;position: relative;', 'id'=>'realtime-opt', 'name'=>'realtime-opt'), $realtimeOpts, $realtimeOpt) .
                        RCView::button(array('id'=>'saveRealtimeOptBtn','class'=>'btn btn-xs btn-defaultrc','onclick'=>'saveRealtimeOpt();',$realtimeSetup=>$realtimeSetup), '<i class="fas fa-cog mr-1"></i>'.RCView::tt('random_215')) .
                        RCView::span(array('id'=>'realtimeSavedMsg','class'=>'savedMsg ml-1'), '<i class="fas fa-circle-tick mr-1"></i>'.RCView::tt('design_243')) . // Invisible saved message
                        RCView::input(array('type'=>'hidden','id'=>'realtime-rid','value'=>$rid))
                    ) .
                    RCView::div(array('id'=>'realtime-logic-div', 'style'=>"display:$logicDisplay;"),
                        RCView::p(array(),
                            RCView::span(array('class'=>'font-weight-bold', 'style'=>'display:inline-block;min-width:120px;'), RCView::tt('global_89')) . 
                            $form_dd .
                            $event_dd 
                        ) .
                        RCView::p(array(), RCView::b(RCView::tt('random_205'))) . 
                        RCView::textarea(
                            array('style'=>'width:99%;', 'id'=>'realtime-logic', 'name'=>'realtime-logic', 'class'=>'x-form-field notesbox', 'onfocus'=>'$("#saveRealtimeOptBtn").prop("disabled",false);openLogicEditor($(this))'), 
                            $realtimeLogic) .
                        RCView::p(array('class'=>'mt-1 text-muted'), RCView::tt('random_206')) 
                    ) .
                    ''
                );

		// Output to page
		print $html;
	}

	// Render single variable drop-downs with either categorical fields or calc fields
	static function renderSingleDropDown($class='randomVar',$idname=null,$selectedFldVal='',$selectedEvtVal='',$disabled=false, $includeTextFields=false, $rid=1)
	{
		global $Proj, $table_pk, $longitudinal;
		// Set list of categorical field types (exclude checkboxes since they can have multiple values)
		$catFields = array('radio', 'select', 'advcheckbox', 'yesno', 'truefalse');
		// Set string for disabled value, if disabled
		$disabled = ($disabled ? 'disabled' : '');
		// Array for collecting fields
		$fields = array(''=>'- '.RCView::tt('random_02').' -');
        $catFieldList = array();
        $textFieldList = array();
		// Get field list with labels
		foreach ($Proj->metadata as $field=>$attr)
		{
			// Exclude first field and Form Status fields
			if ($field == $table_pk || $field == $attr['form_name']."_complete") continue;
			// Is categorical (or calc)?
			if (in_array($attr['element_type'], $catFields)) // || $attr['element_type'] == 'calc')
			{
				$catFieldList[$field] = $field . " (" . strip_tags(label_decode($attr['element_label'])) . ")";
			}
		}
        if ($includeTextFields) {
            foreach ($Proj->metadata as $field=>$attr)
            {
                // Exclude first field, Form Status fields, and text fields with a validation type or @CALCTEXT
                if ($field == $table_pk || $field == $attr['form_name']."_complete") continue;
                if ($attr['element_type']==='text' && empty($attr['element_validation_type']) && !contains($attr['misc'], '@CALCTEXT'))
                {
                    $textFieldList[$field] = $field . " (" . strip_tags(label_decode($attr['element_label'])) . ")";
                }
            }
            $fields = $fields + array(
                RCView::tt('random_135',false) => $catFieldList,
                RCView::tt('random_136',false) => $textFieldList
            );
        } else {
            $fields = $fields + $catFieldList;
        }
		// Return the HTML for the drop-down inside a div
		if (empty($idname)) {
			$rand      = rand(0,999999);
			$idname    = 'fld_'.$rand;
			$idnameEvt = 'evt_'.$rand;
		} else {
			$idnameEvt = $idname.'Evt';
		}
		if ($longitudinal) {
			// If longitudinal, also render and event drop-down
			$field_dd = RCView::select(array($disabled=>$disabled,'class'=>$class, 'style'=>'max-width:400px;', 'id'=>$idname, 'name'=>$idname), $fields, $selectedFldVal, 45);
			// Collect event names/ids into array
			$event_dd_info = array();
			foreach ($Proj->eventInfo as $event_id=>$attr) {
				$event_dd_info[$event_id] = $attr['name_ext'];
			}
			// Add warning inside drop-down if event has somehow not been selected
			$event_dd_warning = '';
			if ($rid>0 && $selectedEvtVal == '' && $idname != 'dagField' && self::setupStatus($Proj->project_id, $rid)) {
				$event_dd_info[''] = RCView::tt('global_01');
				$event_dd_warning = RCView::div(array('class'=>'text-danger boldish'), '<i class="fas fa-exclamation-triangle"></i> '.RCView::tt('global_01').RCView::tt('colon')." ".RCView::tt('random_133'));
			}
			// Add drop-down
			$event_dd = RCView::tt('data_entry_67','span',array('style'=>'margin:0 3px;'))
					  . RCView::select(array($disabled=>$disabled,'class'=>$class.'Evt', 'style'=>'max-width:200px;', 'id'=>$idnameEvt, 'name'=>$idnameEvt), $event_dd_info, $selectedEvtVal, 40)
					  . $event_dd_warning;
		} else {
			// Non-longitudinal
			$field_dd = RCView::select(array($disabled=>$disabled,'class'=>$class, 'style'=>'max-width:400px;', 'id'=>$idname, 'name'=>$idname), $fields, $selectedFldVal, 70);
			$event_dd = "";
		}
		return RCView::div(array('class'=>$class.'Parent','style'=>'margin-left:15px;'), $field_dd.$event_dd);
	}

	// Check the values from the uploaded allocation file for integrity (accepts array beginning with key 0)
	static function checkAllocFile($rid, &$csv_array)
	{
		global $Proj, $status;

		// Get randomization setup values first so we know what to check for
		$randAttr = self::getRandomizationAttributes($rid);
		if ($randAttr === false) redirect(APP_PATH_WEBROOT . 'Randomization/index.php?pid=' . PROJECT_ID);

		// Array to store any error msgs
		$errorMsg = array();

		// Make sure not empty
		if (empty($csv_array)) {
			$errorMsg[] = RCView::tt('random_14');
			return $errorMsg;
		}

		## Determine header fields and compare with headers in uploaded file
		$hdrFields = array();
		// Target field
        $targetField = $randAttr['targetField'];
        $isBlinded = $randAttr['isBlinded'];

        if ($isBlinded) {
            $hdrFields[] = 'redcap_randomization_number';
            if (array_search('redcap_randomization_group', $csv_array[0])!==false) {
                $hdrFields[] = 'redcap_randomization_group'; // group is optional for blinded
            }
        } else {
            $hdrFields[] = 'redcap_randomization_group';
            if (array_search('redcap_randomization_number', $csv_array[0])!==false) {
                $hdrFields[] = 'redcap_randomization_number'; // number is optional for open
            }
        }
		// DAG pseudo-field
		if ($randAttr['group_by'] == 'DAG') {
			$hdrFields[] = 'redcap_data_access_group';
		}
		// Strata fields
		foreach ($randAttr['strata'] as $field=>$event_id) {
			$hdrFields[] = $field;
		}

		// If there exist blank cells in row 1 after the variable names, then delete those columns
		$numColsTotal = count($hdrFields);
		foreach ($csv_array as $row=>$row_values)
		{
			// If rows has more than $numCols columns, then remove those extra columns
			$numColsThisRow = count($row_values);
			if ($numColsThisRow > $numColsTotal) {
				for ($k=$numColsTotal; $k<$numColsThisRow; $k++) {
					unset($csv_array[$row][$k]);
				}
			}
			// Remove any blank rows
			if (implode("", $csv_array[$row]) == "") {
				unset($csv_array[$row]);
			}
		}
		// Reindex array
		$csv_array = array_values($csv_array);

		// Make sure the headers in the first row are correct
        $targetFieldIndex = array_search($targetField, $csv_array[0]);
        if ($targetFieldIndex!==false) {
            // target field name uploaded instead of redcap_randomization_number/group -> replace
            $csv_array[0][$targetFieldIndex] = ($isBlinded) ? 'redcap_randomization_number' : 'redcap_randomization_group';
        }

        $diff = array_diff($hdrFields, $csv_array[0]);
        if (count($diff)) {
			$errorMsg[] = RCView::tt('random_15') . ' "' . implode(",", $hdrFields) . '"';
            $errorMsg[] = sprintf(RCView::tt('random_148'), $targetField, ($isBlinded)?'redcap_randomization_number':'redcap_randomization_group');
			return $errorMsg;
		}

		// Make sure the file contains more than just the first row
		if (count($csv_array) === 1) {
			$errorMsg[] = RCView::tt('random_18');
			return $errorMsg;
		}

		// Check raw values: Make sure that ALL values are not blank and correspond to real choices for each field
		$choices_all = array();
		foreach ($csv_array[0] as $field)
		{
			if ($field == 'redcap_randomization_number') {
                $choices_all[] = array();
            } else if ($field == 'redcap_randomization_group') {
                $choices_all[] = ($isBlinded) ? array() : parseEnum($Proj->metadata[$targetField]['element_enum']);
            } else if ($randAttr['group_by'] == 'DAG' && $field == 'redcap_data_access_group') {
				// DAG field
				$choices_all[] = $Proj->getGroups();
			} else {
				$choices_all[] = isset($Proj->metadata[$field]) ? parseEnum($Proj->metadata[$field]['element_enum']) : array();
			}
		}
		foreach ($csv_array as $key=>$values) {
			// Skip first row
			if ($key == 0) continue;
			// Loop through each col in this row
			foreach ($values as $field_num=>$value) {
				// Check if the value is a real categorical option
                $value = trim($value);
                $field = $csv_array[0][$field_num];
                if ($field == "") continue;
                if ($field == "redcap_randomization_number" && !$isBlinded) continue;
                if ($field == "redcap_randomization_group" && $isBlinded) continue;
                if (count($choices_all[$field_num])===0) {
                    if ($value=='') $errorMsg[] = RCView::tt('random_147') . " \"$field\""; // random_147 = "Empty values in column"
                } else if (!isset($choices_all[$field_num][$value])) 
                {
					$errorMsg[] = "\"$value\" " . RCView::tt('random_20') . " \"$field\"";
				}
			}
		}

		// Check if allocation table already exists for the given project status (prevent uploading again)
		if (self::allocTableExists($_POST['alloc_status'], $rid)) {
			// Allow super users to append to allocation table while in production
			if (!($_POST['alloc_status'] == '1' && $status > 0 && SUPER_USER)) {
				// Add error message
				$errorMsg[] = RCView::tt('random_42') . " " . ($_POST['alloc_status'] == '1' ? RCView::tt('random_36') : RCView::tt('random_35'));
			}
		}

		// Return errors
		return $errorMsg;
	}

	// Store the values from the uploaded allocation file in the database tables
	static function saveAllocFile($rid, $csv_array)
	{
		// Count query errors
		$errors = 0;
		// Get randomization setup values first so we know what to check for
		$randAttr = self::getRandomizationAttributes($rid);
		// Get rid key
		$rid = self::getRid($rid);
		// Create sql column string
		$isBlinded = $randAttr['isBlinded'];
		$sqlColNamesAlloc = array();

		foreach($csv_array[0] as $hdr) {
			if ($hdr=='redcap_randomization_number') {
				$sqlColNamesAlloc[] = ($isBlinded) ? 'target_field' : 'target_field_alt';
			} else if ($hdr=='redcap_randomization_group') {
				$sqlColNamesAlloc[] = ($isBlinded) ? 'target_field_alt' : 'target_field';
			} else if ($hdr=='redcap_data_access_group') {
				$sqlColNamesAlloc[] = 'group_id';
			} else {
				$stratumIndex = array_search($hdr, array_keys($randAttr['strata']));
				if ($stratumIndex!==false) $sqlColNamesAlloc[] = 'source_field'.($stratumIndex+1);
			}
		}
		$alloc_status = db_escape($_POST['alloc_status']);

		// Store allocation file values
		$success = true;
		$totalRows = count($csv_array);
		$colList = implode(", ", $sqlColNamesAlloc);
		$sql_values = [];
		for ($k = 1; $k < $totalRows; $k++) {
			$escaped = array_map('db_escape', $csv_array[$k]);
			$sql_values[] = "($rid, $alloc_status, '" . implode("', '", $escaped) . "')";
		}
		// Use transaction
		db_query("START TRANSACTION");
		$chunkSize = 1000;
		try {
			foreach (array_chunk($sql_values, $chunkSize) as $chunk) {
				$sql = "INSERT INTO redcap_randomization_allocation
						(rid, project_status, $colList)
						VALUES " . implode(",\n", $chunk);
				if (!db_query($sql)) {
					throw new Exception("Insert failed");
				}
			}
			db_query("COMMIT");
		} catch (Exception $e) {
			db_query("ROLLBACK");
			$success = false;
		}
		return $success;
	}

	// Output contents of existing allocation table
	static function getAllocFileContents($rid,$status,$returnAllocatedRecordName=false)
	{
		global $Proj, $table_pk;

		// Get randomization setup values
		$randAttr = self::getRandomizationAttributes($rid);
		if ($randAttr === false) exit(RCView::tt('random_11'));

		## Create file header
		$hdrFields = array();
		// Target field
        $targetField = $randAttr['targetField'];
		$hdrFields[] = 'redcap_randomization_number';
		$hdrFields[] = 'redcap_randomization_group';
        if ($Proj->metadata[$targetField]['element_type']=='text') {
            $targetSql = 'a.target_field, a.target_field_alt'; // blinded: redcap_randomization_number is target; redcap_randomization_group is alt
        } else {
            $targetSql = 'a.target_field_alt, a.target_field'; // open: redcap_randomization_number is alt; redcap_randomization_group is target
        }
		// Strata fields
		foreach ($randAttr['strata'] as $field=>$event_id) {
			$hdrFields[] = $field;
		}
		// DAG pseudo-field
		$groupIdSql = "";
		if ($randAttr['group_by'] == 'DAG') {
			$hdrFields[] = 'redcap_data_access_group';
			$groupIdSql = ", a.group_id";
		}

		// Build parts of sql query to use to pull allocated values
		$critFldsSql = array();
		$numCritFields = count($randAttr['strata']);
		for ($k = 1; $k <= $numCritFields; $k++) {
			$critFldsSql[$k-1] = "a.source_field" . $k;
		}
		$critFldsSql = ($numCritFields > 0) ? ", ".implode(", ", $critFldsSql) : "";

		// Return the record name if a record has been allocated for a given row
		$returnRecordSql = "";
		if ($returnAllocatedRecordName) {
			$returnRecordSql = "a.is_used_by, ";
			array_unshift($hdrFields, $table_pk);
		}

		// Obtain all allocated values and put into array
        $rid = self::getRid($rid);
		$sql = "select $returnRecordSql $targetSql $critFldsSql $groupIdSql
				from redcap_randomization_allocation a, redcap_randomization r
				where r.project_id = " . PROJECT_ID . " and r.rid = $rid and a.project_status = $status
				and r.rid = a.rid order by a.aid";
		$q = db_query($sql);
		$allocValues = array();
		while ($row = db_fetch_assoc($q)) {
			// Save values in array
			$allocValues[] = $row;
		}
		// Output allocation table as CSV rows
		$output = "";
		foreach (array_merge(array($hdrFields), $allocValues) as $col)
		{
			$output .= implode(",", $col) . "\n";
		}
		return $output;
	}

	// Save (add new record to) the randomization setup in the randomization table
	static function saveRandomizationSetup($fields)
	{
		global $Proj, $longitudinal;

		// Check the scheme
		$stratified = (isset($fields['scheme']) && $fields['scheme'] == 'on') ? 1 : 0;
		unset($fields['scheme']);

		// Check if we're using DAGs as pseudo-field
		$useDags = (isset($fields['multisite']) && $fields['multisite'] == 'dag');

		// Make sure we have all the fields we need
		$haveSourceField = false;
		$haveTargetField = false;
		$sourceFields = array();
		$targetField  = null;
		foreach ($fields as $name=>$field)
		{
			// Criteria field(s) or DAG field
			if ($name == 'dagField' || ($stratified && substr($name, 0, 4) == 'fld_')) {
				if ($field != '') {
					$haveSourceField = true;
					// If longitudinal, also get event_id and validate it
					if ($longitudinal) {
						$event_id = ($name == 'dagField') ? $fields[$name.'Evt'] : $fields['evt_'.substr($name, 4)];
						// If not valid, then give it first event_id
						if (!$Proj->validateEventId($event_id)) $event_id = $Proj->firstEventId;
					} else {
						$event_id = $Proj->firstEventId;
					}
					// Add to array
					$sourceFields[$field] =  $event_id;
				}
			}
			// Target field
			elseif ($name == 'targetField') {
				$haveTargetField = true;
				// Set variable
				$targetField = $field;
				// If longitudinal, also get event_id and validate it
				$targetFieldEvent = ($longitudinal) ? $fields['targetFieldEvt'] : '';
			}
		}
		if ($targetFieldEvent == '') $targetFieldEvent = $Proj->firstEventId;

		// Do completion check
		if (($stratified && !$haveSourceField) || !$haveTargetField) {
			return false;
		}

		// Make sure all fields are real project fields
		$allFieldsAreReal = (isset($Proj->metadata[$targetField]));
		foreach (array_keys($sourceFields) as $field)
		{
			if (!isset($Proj->metadata[$field])) {
				$allFieldsAreReal = false;
			}
		}
		if (!$allFieldsAreReal) return false;

        // check target event/field combination is unique 
		$sql = "select 1 from redcap_randomization where project_id=? and target_field=? and target_event=?";
        $q = db_query($sql, [PROJECT_ID, db_escape($targetField), db_escape($targetFieldEvent)]);
		if (db_num_rows($q)) {
            return -1;
        }

        // Create sql string for source fields
		$srcFldSqlNames = $srcFldSqlValues = array();
		$k = 1;
		foreach ($sourceFields as $field=>$event_id) {
			// Add sql field names and their values
			$srcFldSqlNames[] = 'source_field' . $k . ', source_event' . $k;
			$srcFldSqlValues[] = "'$field', ".checkNull($event_id);
			// Increment counter
			$k++;
		}
		$srcFldSqlNames = (empty($srcFldSqlNames) ? "" : ", ") . implode(", ", $srcFldSqlNames);
		$srcFldSqlValues = (empty($srcFldSqlValues) ? "" : ", ") . implode(", ", $srcFldSqlValues);

		// Insert into table
        $rid = 0;
		$sql = "insert into redcap_randomization (project_id, stratified, group_by, target_field, target_event $srcFldSqlNames)
				values (" . PROJECT_ID . ", $stratified, " . checkNull(strtoupper($fields['multisite']??'')) . ", '$targetField', " . checkNull($targetFieldEvent) . " $srcFldSqlValues)";
		if (db_query($sql)) {
            $rid = db_insert_id();
	    	// If any data exists for the randomization field, then delete it (user has already been warned about this)
    		self::deleteSingleFieldData($targetField, $targetFieldEvent);
        }
		// Return success
		return $rid;
	}

    // Save the realtime execution option
    static function saveRealtimeOption($fields) {
        global $Proj, $project_id;
        $rid = self::getRid($fields['rid']);
        if (!$rid) return 0;
        $opt = 0;
        $form = $Proj->firstForm;
        $eventId = $Proj->firstEventId;
        $logic = '';
        if (isset($fields['opt'])) {
            $opt = intval($fields['opt']);
        }
        if (isset($fields['form'])) {
            $form = $fields['form'];
        }
        if (isset($fields['event'])) {
            $eventId = intval($fields['event']);
        }
        if (isset($fields['logic'])) {
            $logic = trim($fields['logic']);
        }
        $sql = "update redcap_randomization set trigger_option=?, trigger_instrument=?, trigger_event_id=?, trigger_logic=? where project_id=? and rid=? limit 1";
        $q = db_query($sql, [$opt, $form, $eventId, $logic, $project_id, $rid]);
        if ($q) {
            if (db_affected_rows()) {
                $realtimeOpts = array(
                    0 => "Manual",
                    1 => "Trigger logic (user with Randomize permission)",
                    2 => "Trigger logic (any user or survey participant)"
                );
                $optLabel = $realtimeOpts[$opt];
                $logicLog = strlen($logic) > 15 ? substr($logic, 0, 13)."..." : $logic;
                Logging::logEvent("", "redcap_randomization", "MANAGE", $rid, "trigger_option: $optLabel, instrument: $form, ".($Proj->longitudinal ? "event_id: $eventId, " : "logic: $logicLog"),
                    "Save randomization execute option (rid = $rid)");
            }
            return 1;
        } 
        return 0;
    }

	// Output contents of allocation template file based upon posted fields selected in setup
	static function getAllocTemplateFileContents($rid, $example_num)
	{
		// Get randomization setup values
		$randAttr = self::getRandomizationAttributes($rid);
		if ($randAttr === false) exit(RCView::tt('random_11'));

		// Get formatted target field/event
		$targetField = $randAttr['targetField'];

		// Get formatted strata (criteria source fields)
		$sourceFields = array();
		foreach ($randAttr['strata'] as $field=>$event_id) {
			$sourceFields[] = $field;
		}

		// If using DAGs to group by
		$useDags = ($randAttr['group_by'] == 'DAG');

		// Return the output string
		return self::generateAllocFileCSV($randAttr['stratified'], $targetField, $sourceFields, $example_num, $useDags);
	}

	// Create CSV output for example allocation files
	static function generateAllocFileCSV($stratified=null, $targetField=null, $sourceFields=array(), $example_num=1, $useDags=false)
	{
        // Set max rows to output
        $max_rows = 50000;
		// Set output string beginning with headers
		$headers = "redcap_randomization_number,redcap_randomization_group"; //$targetField;
		if ($useDags) {
			$headers .= ",redcap_data_access_group";
		}
		if (!empty($sourceFields)) {
			$headers .= "," . implode(",", $sourceFields);
		}
		// Create combos for all source fields utilized and output them to file
		$output = $headers;
		// Return combinations
        $num_rows = 0;
		foreach (self::getSourceFieldCombos($sourceFields, $targetField, $example_num, $useDags) as $row) {
            if (++$num_rows >= $max_rows) break;
			$output .= "\n".implode(',',$row);
		}
		// Add annotations to CSV output as help notes
		$output = self::addAllocFileAnnotations($output, $useDags, $targetField, $sourceFields);
		// Return file contents
		return $output;
	}

	// Add annotations to CSV output as help notes
	static function addAllocFileAnnotations($output, $useDags, $targetField, $sourceFields)
	{
		global $Proj;

		## Set all notes text here
		// Use $notes array to place notes in CSV begining with row 2 (skip one column)
		$notes = array("", "", RCView::tt('random_91', false), " ".RCView::tt('random_88', false), " ".RCView::tt('random_121', false), " ".RCView::tt('random_89', false), " ".RCView::tt('random_106', false));

        // Add explanation for target field based on tyype for blinded vs. open allocation
        // Format field label
        $label = isset($Proj->metadata[$targetField]) ? label_decode($Proj->metadata[$targetField]['element_label']) : "";
        if (mb_strlen($label) > 40) $label = mb_substr($label, 0, 27) . "..." . mb_substr($label, -10);
        // Set initial line
        $notes[] = "";
        $notes[] = RCView::tt('random_139', false) . RCView::tt('colon', false) . " \"$targetField\" ($label)";
        if ($Proj->metadata[$targetField]['element_type'] == 'text') {
            // blinded allocation - randomisation numbers in target field, group desc optional
            $notes[] = RCView::tt('random_140', false) . " " . RCView::tt('random_142', false) . RCView::tt('colon', false);
            $notes[] = " - " . RCView::tt('random_143', false);
            $notes[] = " - " . RCView::tt('random_144', false);
        } else {
            // open allocation - randomisation groups in target field, randomisation number optional
            $notes[] = RCView::tt('random_140', false) . " " . RCView::tt('random_141', false) . RCView::tt('colon', false);
            $notes[] = " - " . RCView::tt('random_145', false);
            $notes[] = " - " . RCView::tt('random_146', false);
			$notes[] = RCView::tt('random_90', false) . " \"randomization_group\" (\"$targetField\")" . RCView::tt('colon', false);
            // Set lines for all choices
            if (isset($Proj->metadata[$targetField])) {
                foreach (parseEnum($Proj->metadata[$targetField]['element_enum']) as $code => $label) {
                    $notes[] = array($code, label_decode($label));
                }
            }
        }

        // Add all multiple choices raw values and their labels for randomization strata
		foreach ($sourceFields as $field) {
			// Format field label
			$label = isset($Proj->metadata[$field]) ? label_decode($Proj->metadata[$field]['element_label']) : "";
			if (mb_strlen($label) > 40) $label = mb_substr($label, 0, 27) . "..." . mb_substr($label, -10);
			// Set initial line
			$notes[] = "";
			$notes[] = RCView::tt('random_90', false) . " \"$field\" ($label)" . RCView::tt('colon', false);
			// Set lines for all choices
			if (isset($Proj->metadata[$field])) {
				foreach (parseEnum($Proj->metadata[$field]['element_enum']) as $code => $label) {
					$notes[] = array($code, label_decode($label));
				}
			}
		}
		// print_array($sourceFields);
		// print_array($notes);
		// exit;
		// Add notes about DAGs and list them by id/name
		if ($useDags) {
			$notes[] = "";
			$notes[] = RCView::tt('random_90', false) . " \"redcap_data_access_group\" " . RCView::tt('random_105', false) . RCView::tt('colon', false);
			foreach ($Proj->getGroups() as $gid=>$gname) {
				$notes[] = array($gid, label_decode($gname));
			}
		}

		## Integrate notes in existing CSV data
		// Get the column number where notes will begin and put in new string
		$outputRows = explode("\n", $output);
		// Reset string
		$output = "";
		// Loop through each row and add any notes
		foreach ($outputRows as $key=>$this_row)
		{
			// Add row to new string
			$output .= $this_row;
			// Check if note should be added to row
			$rowHasNote = (isset($notes[$key]) && $notes[$key] != "");
			if ($rowHasNote) {
				$output .= ",," . self::convertAllocFileNote($notes[$key]);
			}
			// End line
			$output .= "\n";
		}
		// If $notes array is larger than $outputRows, then finish with the rest of $notes
		$numNotes = count($notes);
		$numOutputRows = count($outputRows);
		if ($numNotes > $numOutputRows)
		{
			// Get number of columns in existing CSV before notes are added
			$numCols = substr_count($outputRows[0], ",")+2;
			// Loop through remaining notes
			for ($k=$numOutputRows; $k<$numNotes; $k++) {
				$output .= str_repeat(",", $numCols) . self::convertAllocFileNote($notes[$k]) . "\n";
			}
		}
		// Return CSV formatted string
		return $output;
	}

	// Parse allocation file notes/annnotations as string or array and return as CSV escaped element
	static function convertAllocFileNote($string)
	{
		$noteElement = "";
		if (is_array($string)) {
			foreach ($string as $element) {
				$noteElement .= ',"' . str_replace('"', '""', $element) . '"';
			}
		} else {
			$noteElement = ',"' . str_replace('"', '""', $string) . '"';
		}
		return $noteElement;
	}

	// Create array of all statum combinations
	static function getCombinations($arrays, $i=0) 
    {
        if (!isset($arrays[$i])) {
            return array();
        }
        if ($i == count($arrays) - 1) {
            return $arrays[$i];
        }
    
        // get combinations from subsequent arrays and combine with each element from $arrays[$i]
        $tmp = self::getCombinations($arrays, $i + 1);
        $result = array();
        foreach ($arrays[$i] as $v) {
            foreach ($tmp as $t) {
                $result[] = is_array($t) ? 
                    array_merge(array($v), $t) : array($v, $t);
            }
        }
    
        return $result;
    }

	// Create combos for all source fields utilized
	static function getSourceFieldCombos($sourceFields, $targetField, $example_num, $useDags)
	{
		global $Proj;

        // example_num output descriptions:
        // 1. All stratum/dag combinations, 1 allocation each (sequential group progression)
        // 2. All stratum/dag combinations, all allocation groups for each
        // 3. 5x output of 2 (each allocation, each stratum)

        // blinded vs open
        // - blinded allocation of randomisation number, group optional
        // - open allocation of randomisation group, number optional
        if ($Proj->metadata[$targetField]['element_type']=='text') {
            $isBlinded = true;
            $targetFieldEnum = null;
            $rowsPerStratum = ($example_num==3) ? 5 : $example_num;
        } else {
            $isBlinded = false;
            $targetFieldEnum = array_keys(parseEnum($Proj->metadata[$targetField]['element_enum']));
            switch ($example_num) {
                case 3: $rowsPerStratum = 5 * count($targetFieldEnum); break;
                case 2: $rowsPerStratum = count($targetFieldEnum); break;
                default: $rowsPerStratum = 1;
            }
        }

		// If we're using DAGs as a pseudo-field, add DAG group_ids as enum choices
		if ($useDags) {
			$dags = $Proj->getGroups();
			if (!empty($dags)) {
				$sourceFields = array_merge(array('redcap_data_access_group'),$sourceFields);
			}
		}
		// Store all categories for every source field into array
		$sourceFieldEnum = array();
		foreach ($sourceFields as $field) {
			if ($field == 'redcap_data_access_group') {
				$sourceFieldEnum[] = array_keys($dags);
			} else {
				$element_type = isset($Proj->metadata[$field]) ? $Proj->metadata[$field]['element_type'] : "";
				$element_enum = isset($Proj->metadata[$field]) ? $Proj->metadata[$field]['element_enum'] : "";
				// Convert sql field types' query result to an enum format
				if ($element_type == "sql") {
					$element_enum = getSqlFieldEnum($element_enum);
				}
				// Load status yesno choices
				elseif ($element_type == "yesno") {
					$element_enum = YN_ENUM;
				}
				// Load status truefalse choices
				elseif ($element_type == "truefalse") {
					$element_enum = TF_ENUM;
				}
				$sourceFieldEnum[] = array_keys(parseEnum($element_enum));
			}
		}
		// Get array of all stratum combinations
        if (count($sourceFieldEnum)) {
            $combos = self::getCombinations($sourceFieldEnum);
        } else {
            $combos = array(-1 => array());
        }
        $outputRows = array();
        $rowNum = 0;
        foreach ($combos as $s => $stratum) {
            $stratum = (is_array($stratum)) ? $stratum : array($stratum);
            for($i=0; $i<$rowsPerStratum; $i++) {
                $thisRow = array();
                if ($isBlinded) {
                    $thisRow[] = ($s+1).''.($i+1); // rand num 
                    $thisRow[] = ''; // rand grp empty
                } else {
                    $thisRow[] = ''; // rand num empty
					if (count($targetFieldEnum) > 0) {
						$groupIndex = $rowNum % count($targetFieldEnum);
					} else {
						// Edge case: handle the case where $targetFieldEnum is empty
						$groupIndex = null;
					}
                    $thisRow[] = $targetFieldEnum[$groupIndex] ?? '';
                }
                $outputRows[] = array_merge($thisRow, $stratum);
                $rowNum++;
            }
        }
        
		return $outputRows;
	}

	// Get record randomization data for smart variables
	static function getSmartVariableData($smartVar, $record, $randRef=1, $rawValue=false, $project_id=null)
	{
        global $Proj;
        if (!in_array($smartVar, array('rand-group','rand-number','rand-time','rand-utc-time'))) return '';

        // $randRef in sequence of project randomizations -> get the corresponding randomization id (rid)
        $randRef = intval($randRef);
        $allRands = self::getAllRandomizationAttributes($project_id);
        if ($randRef < 1 || count($allRands) < $randRef) return '';
        $rid = intval((array_keys($allRands))[$randRef-1]);

        $record = db_escape($record);
		$sql = "select r.target_field, ra.target_field as allocation, target_field_alt as allocation_alt, allocation_time, allocation_time_utc
                from redcap_randomization_allocation ra
                inner join redcap_randomization r on ra.rid=r.rid
                inner join redcap_projects p on ra.project_status=p.status and r.project_id=p.project_id
                where ra.rid=? 
                and ra.is_used_by=? 
                order by aid limit 1";
		$q = db_query($sql, [$rid, $record]);
        if (db_num_rows($q)===0) return '';
		while ($row = db_fetch_assoc($q)) {
			// Save values in array
			$targetField = $row['target_field'];
			$alloc = $row['allocation'];
			$allocAlt = $row['allocation_alt'];
			$ts = $row['allocation_time'];
			$tsutc = $row['allocation_time_utc'];
		}

        $isBlinded = ($Proj->metadata[$targetField]['element_type']=='text');
        $rtnValue = '';
        switch ($smartVar) {
            case "rand-number" :
                $rtnValue = ($isBlinded) ? $alloc : $allocAlt;
                break;
            case "rand-time" :
                $rtnValue = !($rawValue) ? DateTimeRC::format_user_datetime($ts, 'Y-M-D_24',null,false,true) : $ts;
                break;
            case "rand-utc-time" :
                $rtnValue = !($rawValue) ? DateTimeRC::format_user_datetime($tsutc, 'Y-M-D_24',null,false,true) : $tsutc;
                break;
            default:
                break;
        }

        return $rtnValue;
    }

    static function renderSummaryTable()
    {
        global $Proj, $user_rights, $status;
        $configs = array();
        $sql = "select r.rid, coalesce(sum(1-project_status),0) as list_recs_dev, coalesce(sum(project_status),0) as list_recs_prod from redcap_randomization r left outer join redcap_randomization_allocation ra on r.rid=ra.rid where project_id={$Proj->project_id} group by r.rid order by r.rid ";
        $q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {        
            $randAttr = self::getRandomizationAttributes($row['rid']);
            $target = RCView::span(array('class'=>'RandSummaryFieldName'),$randAttr['targetField']);
            if ($Proj->longitudinal) {
                $target .= " (".$Proj->eventInfo[$randAttr['targetEvent']]['name_ext'].")"; 
            }
            $target = RCView::span(array('class'=>'badge badge-primary RandSummaryBadge'),$target);

            if ($randAttr['triggerOption'] > 0) {
                $target .= RCView::div(array(),
                    "<a class='RandSummaryViewLogic' style='font-size: 75%;' href='javascript:;' class='viewEq d-print-none' tabindex='-1'>".RCView::tt("form_renderer_68")." <i class='fas fa-caret-down'></i><i class='fas fa-caret-up' style='display:none;'></i></a>".
                    RCView::div(array('class'=>'RandSummaryLogic'), '<code>'.$randAttr['triggerLogic'].'</code>')
                );
            }

            $strata = array();
            if ($randAttr['group_by']==='DAG') {
                $strata[] = RCView::span(array('class'=>'badge badge-secondary'),RCView::tt('global_78')); // Data Access Group;
            }
            foreach ($randAttr['strata'] as $stratumfield => $stratumevent) {
                $stratumFieldDisplay = RCView::span(array('class'=>'RandSummaryFieldName'),$stratumfield);
                if ($Proj->longitudinal) {
                    $stratumFieldDisplay .= " (".$Proj->eventInfo[$stratumevent]['name_ext'].")";
                }
                $strata[] = RCView::span(array('class'=>'badge badge-secondary RandSummaryBadge'),$stratumFieldDisplay);
            }

            $configs[] = array(
                'rid' => $row['rid'],
                'target' => $target,
                'is_blinded' => $randAttr['isBlinded'],
                'stratification' => $strata,
                'list_recs_dev' => $row['list_recs_dev'],
                'list_recs_prod' => $row['list_recs_prod']
            );
        }

        if (count($configs) > 0) {
            echo '<div id="RandSummaryTableContainer">';
            echo '<table id="RandSummaryTable"><thead><tr><th>#</th>';
            echo '<th class="text-center">'.RCView::tt('random_156').'</th>'; // "Target"
            echo '<th class="text-center">'.RCView::tt('random_160').'</th>'; // "Allocation Type"
            echo '<th class="text-center">'.RCView::tt('random_157').'</th>'; // "Stratification"
            if ($status==0) echo '<th class="text-center">'.RCView::tt('random_158').'</th>'; // "Allocations (Development)"
            echo '<th class="text-center">'.RCView::tt('random_159').'</th>'; // "Allocations (Production)"
            if ($user_rights['random_setup']) echo '<th class="text-center">'.RCView::tt('rights_142').'</th>'; // "Setup"
            if ($user_rights['random_dashboard']) echo '<th class="text-center">'.RCView::tt('rights_143').'</th>'; // "Dashboard"
            echo '<th class="text-center"><span class="text-muted font-weight-normal">'.RCView::tt('random_197').'</span></th>'; // "Unique ID"
            echo '</tr></thead><tbody>';
            foreach ($configs as $i => $thisrid) {
                echo '<tr><td class="text-center">'.($i+1).'</td>';
                echo '<td>'.$thisrid['target'].'</td>';
                if ($thisrid['is_blinded']) {
                    echo '<td class="text-center"><i class="fas fa-envelope text-danger fs14" data-bs-toggle="tooltip" title="'.RCView::tt('random_162',false).'"></i></td>';
                } else {
                    echo '<td class="text-center"><i class="fas fa-envelope-open text-success fs14" data-bs-toggle="tooltip" title="'.RCView::tt('random_161',false).'"></i></td>';
                }
                if (count($thisrid['stratification']) > 0) {
                    echo '<td class="px-1"><ul class="pl-1 RandSummaryFieldList"><li>'.implode('</li><li>',$thisrid['stratification']).'</li></ul></td>';
                } else {
                    echo '<td class="text-center px-1"><i class="fas fa-times text-danger"></i></td>';
                }
                if ($status==0) echo '<td class="text-center">'.$thisrid['list_recs_dev'].'</td>';
                echo '<td class="text-center">'.$thisrid['list_recs_prod'].'</td>';
                if ($user_rights['random_setup']) echo '<td class="text-center"><a class="btn btn-xs" href="'.APP_PATH_WEBROOT.'Randomization/index.php?pid='.$Proj->project_id.'&rid='.$thisrid['rid'].'"><i class="fas fa-edit fs14"></i></a></td>';
                if ($user_rights['random_dashboard']) echo '<td class="text-center"><a class="btn btn-xs" href="'.APP_PATH_WEBROOT.'Randomization/dashboard.php?pid='.$Proj->project_id.'&rid='.$thisrid['rid'].'"><i class="fas fa-table fs14"></i></a></td>';
                echo '<td class="text-center"><span class="text-muted font-weight-normal">'.$thisrid['rid'].'</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
            ?>
            <script type="text/javascript">
                $(document).ready(function() {
                    $('#RandSummaryTable').DataTable();
                    $('a.RandSummaryViewLogic').on('click', function() {
                        let thisLogicDiv = $(this).closest('div').find('div.RandSummaryLogic');
                        let thisCaretDown = $(this).closest('div').find('i.fa-caret-down');
                        let thisCaretUp = $(this).closest('div').find('i.fa-caret-up');
                        if ($(thisLogicDiv).is(':visible')) {
                            $(thisLogicDiv).slideUp();
                            $(thisCaretDown).show();
                            $(thisCaretUp).hide();
                        } else {
                            $(thisLogicDiv).slideDown();
                            $(thisCaretDown).hide();
                            $(thisCaretUp).show();
                        }
                    });
                });
            </script>
            <style type="text/css">
                #RandSummaryTableContainer { max-width: 850px; }
                #RandSummaryTable th, #RandSummaryTable td { padding: 1em; }
                .RandSummaryBadge { font-size: 85%; }
                .RandSummaryFieldName { font-family: monospace; }
                .RandSummaryFieldList { list-style-type: none; margin: 0; }
                .RandSummaryLogic { display: none; }
            </style>
            <?php
        }

        if ($user_rights['random_setup']) {
            self::renderRandConfigAddButton();
        }   
    }

    static function renderRandConfigAddButton()
    {
        print RCView::div(array('class'=>'mt-2'),RCView::button(
            array(
                'id'=>'randConfigAddBtn',
                'class'=>'btn btn-xs btn-success',
                'onclick'=>'window.location.href=app_path_webroot+page+"?pid="+pid+"&rid=new"'
            ),
            '<i class="fas fa-plus mr-1"></i>'.RCView::tt('random_153') // Add new randomization
        ));
    }

    // Return T/F is any project randomization (or the specified randomization) uses DAG stratification
	static function randomizeByDAG($rid=null)
	{
		$sql = "select 1 from redcap_randomization where project_id = ".PROJECT_ID." and group_by = 'DAG' ";
		if (self::getRid($rid) > 0) {
            $sql .= " and rid = ".intval($rid);
		}
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0);
	}

    // Return array of randomization ids that a field [/event] is utilised in (as target, as criteria, as either)
    static function getFieldRandomizationIds($field_name, $event_id=null, $project_id=null, $asCriterion=true, $asTarget=true) 
    {
        $fieldRids = array();
        $allRandomizationFields = self::getAllRandomizationFields(true,$asCriterion,$asTarget,$project_id);
        foreach ($allRandomizationFields as $rid => $randFields) {
            if (array_search($rid, $fieldRids)!==false) continue;
            // $randField keys are target_field, target_event, source_field1, source_event1, source_field2, source_event2, ...
            $thisFldKey = array_search($field_name, $randFields);
            if ($thisFldKey!==false) {
                $thisEvtKey = str_replace('field','event',$thisFldKey); // e.g. source_field1 -> source_event1
                $thisEvt = $randFields[$thisEvtKey] ?? null;
                if (is_null($event_id) || is_null($thisEvt) || $event_id==$thisEvt) {
                    $fieldRids[] = $rid;
                }
            }
        }
        return $fieldRids;
    }

    // Return array of fields used in randomisations on form[/event], keys are field names, values array of rids
    static function getFormRandomizationFields($instrument, $event_id=null, $project_id=null, $returnCriteriaFields=true, $returnTargetFields=true) 
    {
		if ($project_id == null) {
			global $Proj;
        } else {
            $Proj = new \Project($project_id);
        }
        $formRandFields = array();
        if (array_key_exists($instrument, $Proj->forms)) {
            foreach(array_keys($Proj->forms[$instrument]['fields']) as $ff) {
                $fieldRids = Randomization::getFieldRandomizationIds($ff, $event_id, $project_id, $returnCriteriaFields, $returnTargetFields);
                if (count($fieldRids)) {
                    $formRandFields[$ff] = $fieldRids;
                }
            }
        }
        return $formRandFields;
    }

    // Return T/F is the record randomized using any field on specified form[/event]
    static function wasRecordRandomizedByForm($record, $instrument, $event_id=null, $project_id=null) 
    {
        // get the fields on this form that are used in randomizations (array of fields with values an array of the rids)
        $formRandTargetFields = self::getFormRandomizationFields($instrument, $event_id, $project_id);
    
        // flip to being an array of randomization ids with values being an array of the fields used
        $ridFields = array();
        foreach ($formRandTargetFields as $f => $ridArray) {
            foreach ($ridArray as $r) {
                $ridFields[$r][] = $f;
            }
        }
    
        // Determine if this record has already been randomized using fields on this form
        $wasRecordRandomized = false;
        if (count($formRandTargetFields) > 0) {
            foreach (array_keys($ridFields) as $formRid) {
                $wasRecordRandomized = self::wasRecordRandomized($record, $formRid, $project_id);
                if ($wasRecordRandomized) break;
            }
        } 
        return $wasRecordRandomized;
    }

    // Return T/F is the record randomized using any field from specified event
    static function wasRecordRandomizedByEvent($record, $event_id, $project_id=null) 
    {
        global $Proj;
        $wasRecordRandomized = false;
        foreach ($Proj->eventsForms[$event_id] as $instrument) {
            $wasRecordRandomized = self::wasRecordRandomizedByForm($record, $instrument, $event_id, $project_id);
            if ($wasRecordRandomized) break;
        } 
        return $wasRecordRandomized;
    }

    // Return T/F is the field[/event] used (as criterion or target) in an existing randomization for the record
    static function wasRecordRandomizedUsingField($record, $field, $event_id=null, $project_id=null) 
    {
        $fieldUsed = false;
        $fieldRids = self::getFieldRandomizationIds($field, $event_id, $project_id);
        foreach ($fieldRids as $thisRid) {
            if (self::wasRecordRandomized($record, $thisRid)) {
                $fieldUsed = true;
                break;
            }
        }
        return $fieldUsed;
    }

	/**
	 * Get the id of the next available allocation table entry for the specified 
	 * randomization and stratification field values
	 * @param int $rid 
	 * @param array $fields 
	 * @param string|int|null $group_id 
	 * @param string|int|null $project_id 
	 * @return false|'0'|int false = error, '0' = no allocations left, int = allocation id
	 */
    static function getNextAllocation(int $rid, array $fields, $group_id, $project_id=null)
    {
        // Validate project id and rid
		$project_id = intval($project_id ?? (defined("PROJECT_ID") ? PROJECT_ID : null));
		if ($project_id == 0) return false;
        $rid = self::getRid($rid, $project_id);
        if (!$rid) return false;
		$project_id = intval($project_id);

		// Validate fields
		$criteriaFields = self::getRandomizationFields($rid, false, true, false, $project_id);
		if (count($fields) != count($criteriaFields)) return false;
		foreach (array_keys($fields) as $field) {
			if (!in_array($field, $criteriaFields)) return false;
		}
		// Validate group_id
		$group_id = intval($group_id ?? 0);
		// Get project status
		$Proj = new \Project($project_id);
		$status = intval($Proj->project["status"]);

        // Create sql subquery for DAG
		$sqlsub = " AND `group_id`" . ($group_id > 0 ? " = $group_id" : " IS NULL");
		// Create sql subquery for strata critera
		foreach ($criteriaFields as $col => $field) {
			$sqlsub .= " AND `$col` = '".db_escape($fields[$field])."'";
		}
		// Query to get an aid key for these field value combinations
		// All params are integers or escaped col names
		$sql = "SELECT `aid`
				FROM redcap_randomization_allocation
				WHERE `rid` = $rid 
					AND `project_status` = $status
					AND `is_used_by` IS NULL
					$sqlsub
					ORDER BY `aid`
					LIMIT 1
				";
		$q = db_query($sql);
		if (db_num_rows($q) < 1) {
			// Return as 0 to give error message that allocations are exhausted
			return '0';
		} else {
			return intval(db_result($q, 0));
		}
    }

    // Update the target/alt value or used by of an allocation table entry
    public static $allowedColumns = array('target_field', 'target_field_alt', 'is_used_by', 'allocation_time', 'allocation_time_utc');
	static function updateAllocationTable(int $aid, string $column, $value, $reasonMessage='', $skipLogEntry=false, $appendLoggingDescription='')
	{
        global $require_change_reason;
        if (!in_array($column, self::$allowedColumns)) return false;
        $aid = intval($aid);

        if ($column=='is_used_by') {
            // If we're manually randomizing a record or removing randomization, fetch the details of the allocation before we make a change
            $sql = "select r.project_id, a.project_status, a.is_used_by as record, r.target_field, r.target_event, a.target_field as target_field_value
                    from redcap_randomization r, redcap_randomization_allocation a 
                    where a.rid = r.rid and a.aid = ? limit 1";
            $row = db_fetch_assoc(db_query($sql, $aid));
            $project_status = $row['project_status'];
            $project_id = $row['project_id'];
            $record = ($value == '' ? $row['record'] : $value);
            $target_field = $row['target_field'];
            $target_field_value = ($value == '' ? $value : $row['target_field_value']);
            $eventId = $row['target_event'];
        } else {
            $project_status = db_result(db_query("select project_status from redcap_randomization_allocation where aid = ?", $aid));
            $project_id = db_result(db_query("select r.project_id from redcap_randomization r, redcap_randomization_allocation a where a.rid = r.rid and a.aid = ?", $aid));
        }

        // Set the value in the allocation table for the specified column
		$sql = "update redcap_randomization_allocation set ".db_escape($column)." = ? where aid = ? limit 1";
		$q = db_query($sql, [$value, $aid]);

		if ($q && db_affected_rows() > 0) {

            if ($column=='is_used_by') {
                // allocating (or removing), capture (or remove) timestamps
                if ($value=='') {
                    $ts = $tsutc = null;
                } else {
                    $dt = new \DateTime();
                    $ts = $dt->format('Y-m-d H:i:s');
                    $dt->setTimezone(new \DateTimeZone('UTC'));
                    $tsutc = $dt->format('Y-m-d H:i:s');
                }
                self::updateAllocationTable($aid,'allocation_time',$ts,'',true);
                self::updateAllocationTable($aid,'allocation_time_utc',$tsutc,'',true);
            }

            $sql = preg_replace('/\?/',$value,$sql,1);
            $sql = preg_replace('/\?/',$aid,$sql,1);
            $display = "aid: $aid, $column: \"$value\"";
            if ($require_change_reason) {
                $reason = $reasonMessage;
            } else {
                $reason = null;
                $display .= ", reason: \"$reasonMessage\"";
            }
            if (!$skipLogEntry) {
                if ($column=='is_used_by') {
                    // Log the rand field's data change
                    \Logging::logEvent($sql, "redcap_data", "update", $record, "$target_field = '$target_field_value'", "Update record", $reason, "", $project_id, true, $eventId);
                }
                $statusText = ($project_status == '1') ? "production" : "development";
    			\Logging::logEvent($sql,'redcap_randomization_allocation','MANAGE',$aid,$display,trim("Update randomization allocation table ($statusText) $appendLoggingDescription"),$reason,"",$project_id);
            }
			return true;
		} else {
			return false;
		}
	}

    // Validate that the supplied aid is valid for the supplied other parameters, return (int)$aid when valid, false if not
    static function validateAllocationTableId($aid, $project_status, $rid, $record)
    {
        if (!isinteger($aid)) return false;
        $sql = "SELECT rid, project_status, is_used_by FROM redcap_randomization_allocation WHERE aid = ?";
        $q = db_query($sql, $aid);
		if (db_num_rows($q) < 1) return false;
        $row = db_fetch_assoc($q);
        // Validate items from table
        if ($row['rid'] != $rid) return false;
        if ($row['project_status'] != $project_status) return false;
        if ($row['is_used_by'] != null && $row['is_used_by'] != $record) return false;
        // Return aid if everything was validated
        return $aid;
    }

    public static function renderAllocationTable($rid, $stratum) 
    {
        global $Proj, $longitudinal, $status;
        $randAttr = self::getRandomizationAttributes($rid);

        // decode stratum
        $stratumArray = ($stratum == '') ? array() : explode(',',$stratum);
        $dagIndexOffset = ($randAttr['group_by'] == 'DAG') ? 1 : 0;

        if (count($stratumArray) !== count($randAttr['strata']) + $dagIndexOffset) {
            print RCView::div(array('class'=>'red'),RCView::tt('random_167').' "'.$stratum.'"');
            return;
        }
        
        $statusLabel = RCView::tt('edit_project_58','span',array('style'=>'font-weight-bold;color:#000;float:none;border:0;')).'&nbsp; ';
        // Set icon/text for project status
        if ($status == '0') {
            print '<div class="mb-2"><span style="color:#666;font-weight:normal;">'.$statusLabel.'<i class="far fa-check-square"></i> '.RCView::tt('global_29').'</span></div>';
        } elseif ($status == '1') {
            print '<div class="mb-2"><span style="color:#00A000;font-weight:normal;">'.$statusLabel.'<i class="ms-1 fas fa-minus-circle"></i> '.RCView::tt('global_30').'</span></div>';
        } else {
            print '<div class="red"><span style="color:#A00000;font-weight:normal;">'.$statusLabel.'<i class="ms-1 fas fa-wrench"></i> '.RCView::tt('global_159').'</span></div>';
            return;
        }

        // Display target
        print RCView::h6(array('class'=>'mt-4','style'=>'color:#800000'),RCView::tt('random_139')); // Randomization Field

        if ($longitudinal) {
            $targetDiv = RCView::div(array('class'=>'my-1'), 
                RCView::tt('random_170').RCView::tt('colon'). ' '.
                RCView::span(array('class'=>'font-weight-bold'), $Proj->eventInfo[$randAttr['targetEvent']]['name_ext'])
            );
        }

        $targetField = $randAttr['targetField'];
        $targetFieldLabel = strip_tags($Proj->metadata[$targetField]['element_label']);
        if (mb_strlen($targetFieldLabel) > 40) $label = mb_substr($targetFieldLabel, 0, 38) . "...";
        $targetDiv .= RCView::div(array('class'=>'my-1'), 
            RCView::tt('random_171').RCView::tt('colon'). ' '.
            RCView::span(array('class'=>'font-weight-bold'), $targetFieldLabel).
            RCView::span(array(), " ($targetField)")
        );
        
        if ($randAttr['isBlinded']) {
            $targetDiv .= RCView::div(array('class'=>'font-weight-normal gray'), '<i class="fas fa-envelope text-danger mr-1"></i>'.RCView::tt('random_138'));
        } else {
            $targetDiv .= RCView::div(array('class'=>'font-weight-normal gray'), '<i class="fas fa-envelope-open text-success mr-1"></i>'.RCView::tt('random_137'));
        }

        print RCView::div(array('class'=>'px-4'), $targetDiv);

        // Display stratification 
        $dagSql = "";
        $stratumSql = "";
        if (count($stratumArray) > 0 || $randAttr['group_by'] == 'DAG') {
            print RCView::h6(array('class'=>'mt-4','style'=>'color:#800000'),RCView::tt('random_169')); // Stratification
            if ($randAttr['group_by']=='DAG') {
                // validate the DAG id
                $dags = $Proj->getGroups();
                $dagId = $stratumArray[0]; // DAG id is always first element of stratum array when stratifying by dag
                if (array_key_exists($dagId, $dags)) {
                    $stratDiv = RCView::div(array('class'=>'my-1'), 
                        RCView::span(array('class'=>'font-weight-bold'), RCView::tt('global_78').RCView::tt('colon').' ').
                        RCView::span(array('class'=>'font-weight-bold'),$dags[$dagId]).
                        RCView::span(array()," ($dagId)")
                    );
                    $dagSql = " and group_id = ".db_escape($dagId);
                } else {
                    print RCView::div(array('class'=>'red'),RCView::tt('random_168').' "'.$dagId.'" ('.RCView::tt('global_78').')');
                    return;
                }
            }

            foreach (array_keys($randAttr['strata']) as $sIdx => $thisStrat) {
                $sourceFieldIndex = $sIdx+$dagIndexOffset;
                $label = strip_tags($Proj->metadata[$thisStrat]['element_label']);
                if (mb_strlen($label) > 40) $label = mb_substr($label, 0, 38) . "...";
                $choices = parseEnum($Proj->metadata[$thisStrat]['element_enum']);
                $level = $stratumArray[$sourceFieldIndex];
                if (array_key_exists($level, $choices)) {
                    $choiceLabel = strip_tags($choices[$level]);
                    if (mb_strlen($choiceLabel) > 40) $choiceLabel = mb_substr($choiceLabel, 0, 38) . "...";
                    $stratDiv .= RCView::div(array('class'=>'my-1'), 
                        RCView::span(array('class'=>'font-weight-bold'), $label).
                        RCView::span(array(), " ($thisStrat)").
                        RCView::tt('colon').' '.
                        RCView::span(array('class'=>'font-weight-bold'), $choiceLabel).
                        RCView::span(array(), " ($level)")
                    );
                } else {
                    print RCView::div(array('class'=>'red'),RCView::tt('random_168').' "'.$level.'" ('.$thisStrat.')');
                    return;
                }
                $stratumSql .= " and source_field".($sIdx+1)." = '".db_escape($level)."'";
            }
            print RCView::div(array('class'=>'px-4'), $stratDiv);
        }

        print RCView::h6(array('class'=>'mt-4','style'=>'color:#800000'),RCView::tt('random_172')); // Allocation Table
        print RCView::p(array('class'=>'mt-1'),RCView::tt('random_210'));

        $sql = "select aid, target_field, target_field_alt, is_used_by 
                from redcap_randomization_allocation
                where rid = ? and project_status = ? $dagSql $stratumSql order by aid";
        $q = db_query($sql, [$rid,$status]);

        $tableData = array();
        if (db_num_rows($q) > 0)
		{
            $i = 0;
            while ($row = db_fetch_assoc($q))
            {
                $i++;
                $aid = $row['aid'];
                $target = $row['target_field'];
                $alt = $row['target_field_alt'];
                $alloc = $row['is_used_by'];
                if ($alloc==$aid."-UNAVAILABLE") {
                    $alloc = '<i class="fas fa-ban text-danger"></i>';
                    $displayEdit = 'none';
                    $displayUnavailable = 'inherit';
                    $displayRemove = 'none';
                } else {
                    $displayEdit = ($alloc == '') ? 'inherit' : 'none';
                    $displayUnavailable = 'none';
                    $displayRemove = ($alloc == '') ? 'none' : 'inherit';
                }

                $edit = RCView::span(array('id'=>"allocation_table_remove_$aid",'style'=>"display:$displayRemove"), 
                            "<a class='table-edit-btn btn btn-xs' data-bs-toggle='tooltip' title='".RCView::tt('random_176',false)."' onclick='RandomizationEdit.removeRandomization($aid, $i)'><i class='fas fa-user-minus text-danger'></i></a>"
                        ).
                        RCView::span(array('id'=>"allocation_table_unavailable_$aid",'style'=>"display:$displayUnavailable"), 
                            "<a class='table-edit-btn btn btn-xs' data-bs-toggle='tooltip' title='".RCView::tt('random_192',false)."' onclick='RandomizationEdit.makeAvailable($aid, $i)'><i class='fas fa-check-circle'></i></a>"
                        ).
                        RCView::span(array('id'=>"allocation_table_edit_$aid",'style'=>"display:$displayEdit"),
                            "<a class='table-edit-btn btn btn-xs' data-bs-toggle='tooltip' title='".RCView::tt('random_182',false)."' onclick='RandomizationEdit.editTarget($aid, $i)'><i class='fas fa-bullseye'></i></a>".
                            "<a class='table-edit-btn btn btn-xs' data-bs-toggle='tooltip' title='".RCView::tt('random_184',false)."' onclick='RandomizationEdit.editAlternate($aid, $i)'><i class='far fa-dot-circle'></i></a>".
                            "<a class='table-edit-btn btn btn-xs' data-bs-toggle='tooltip' title='".RCView::tt('random_178',false)."' onclick='RandomizationEdit.manualRandomization($aid, $i)'><i class='fas fa-user-plus'></i></a>".
                            "<a class='table-edit-btn btn btn-xs' data-bs-toggle='tooltip' title='".RCView::tt('random_180',false)."' onclick='RandomizationEdit.makeUnavailable($aid, $i)'><i class='fas fa-ban'></i></a>"
                        );

                $tableData[] = array(
                    $i,
                    "<span id='allocation_table_edit_target_$aid'>$target</span>",
                    "<span id='allocation_table_edit_alt_$aid'>$alt</span>",
                    "<span id='allocation_table_edit_record_$aid'>$alloc</span>",
                    $edit
                );
            }
        }
        $isBlinded = self::getRandomizationAttributes($rid)['isBlinded'];
        $targetFieldText = $isBlinded ? RCView::tt('random_211') : RCView::tt('random_212');
        $altFieldText    = $isBlinded ? RCView::tt('random_212') : RCView::tt('random_211');
        $tableHeaders = array(
            array( 60, RCView::tt('random_173','span',array('class'=>'font-weight-bold')), 'center', 'int'), // Sequence
            array(150, RCView::tt('random_171','span',array('class'=>'font-weight-bold')).'<i class="fas fa-bullseye ml-1"></i><br>'.$targetFieldText  , 'center'), // Target Field
            array(150, RCView::tt('random_174','span',array('class'=>'font-weight-bold')).'<i class="far fa-dot-circle ml-1"></i><br>'.$altFieldText, "center"), // Alternative
            array(100, RCView::tt('global_49' ,'span',array('class'=>'font-weight-bold')).'<i class="fas fa-user ml-1"></i>'      , 'center'), // Record 
            array(100, RCView::tt('global_27' ,'span',array('class'=>'font-weight-bold')).'<i class="fas fa-pencil-alt ml-1"></i>', 'center')  // Edit
        );
        print RCView::div(array('class'=>'my-2 px-4'), RCView::b(count($tableData)).' '.RCView::tt('random_175'));
        if (empty($tableData)) {
            $tableData[] = array("",RCView::tt('random_213','span',['class'=>'text-danger']));
        }
        print renderGrid("allocation_table", "", 626, "auto", $tableHeaders, $tableData, true, true, false);
        \addLangToJS(['random_176','random_178','random_180','random_182','random_184','random_190','random_192']);
        ?>
        <style type="text/css">
            a.table-edit-btn { color:#000066; margin:0; }
            #edit_alloc_table_confirm { border:2px solid red; }
            #edit_alloc_table_reason { width: 100% }
            #table-allocation_table td.edited-highlight { background-color: lightgreen; }
        </style>
        <script type="text/javascript">
            var RandomizationEdit = {
                removeRandomization: function(aid, seq) {
                    let current = $('#allocation_table_edit_record_'+aid).text();
                    this.showDialog('removeRandomization', aid, seq, current, '<i class="fas fa-user-minus mr-1"></i>'+lang.random_176);
                },
                manualRandomization: function(aid, seq) {
                    let current = $('#allocation_table_edit_record_'+aid).text();
                    this.showDialog('manualRandomization', aid, seq, '', '<i class="fas fa-user-plus mr-1"></i>'+lang.random_178);
                },
                makeUnavailable: function(aid, seq) {
                    let current = $('#allocation_table_edit_record_'+aid).text();
                    this.showDialog('makeUnavailable', aid, seq, '', '<i class="fas fa-ban mr-1"></i>'+lang.random_180);
                },
                makeAvailable: function(aid, seq) {
                    let current = $('#allocation_table_edit_record_'+aid).text();
                    this.showDialog('makeAvailable', aid, seq, '', '<i class="fas fa-check-circle mr-1"></i>'+lang.random_192);
                },
                editTarget: function(aid, seq) {
                    let current = $('#allocation_table_edit_target_'+aid).text();
                    this.showDialog('editTarget', aid, seq, current, '<i class="fas fa-bullseye mr-1"></i>'+lang.random_182);
                },
                editAlternate: function(aid, seq) {
                    let current = $('#allocation_table_edit_alt_'+aid).text();
                    this.showDialog('editAlternate', aid, seq, current, '<i class="far fa-dot-circle mr-1"></i>'+lang.random_184);
                },
                showDialog: function(operation, aid, seq, current, title) {
                    $.get(
                        app_path_webroot+'Randomization/edit_allocation_table_ajax.php?pid='+pid, 
                        { action: 'prompt', operation: operation, aid: aid, seq: seq, current: current }, 
                        function(data) {
                    		initDialog("edit_alloc_table_dialog",data);
                            $('#edit_alloc_table_dialog').dialog(
                                { 
                                    bgiframe: true, 
                                    title: title, 
                                    modal: true, 
                                    width: 450, 
                                    buttons: {
                                        <?=RCView::tt('global_53' ,false)?>: function() { $(this).dialog('close'); } ,
			                            <?=RCView::tt('design_654',false)?>: function() {
				                            if ($('#edit_alloc_table_confirm').val().trim().toUpperCase() !== "CONFIRM" ||
                                                    $('#edit_alloc_table_reason').val().trim() == "" ||
                                                    $('#edit_alloc_table_newval').val().trim() == "" ) {
					                            simpleDialog(lang.random_190);
					                            return;
				                            }
                                            let data = {
                                                aid: aid,
                                                seq: seq,
                                                current: current, 
                                                newval: $('#edit_alloc_table_newval').val().trim(),
                                                reason: $('#edit_alloc_table_reason').val().trim()
                                            };
                                            RandomizationEdit.sendEditRequest(operation, data);
                                        }
                                    }
                    			}
		                    );
	                    }
                    );
                },
                sendEditRequest: function(operation, data) {
                    let result;
                    $.post(
                        app_path_webroot+'Randomization/edit_allocation_table_ajax.php?pid='+pid, 
                        { action: 'edit', operation: operation, data: data }, 
                        function(data) {
                    		RandomizationEdit.processResponse(operation, data)
	                    },
                        function(data) {
                            alert(woops);
                        }
                    );
                    return result;
                },
                processResponse: function(operation, response) {
                    try {
                        var responseObj = JSON.parse(response);
                        if (responseObj.result==0) { 
                            simpleDialog(responseObj.message);
                            return;
                        }
                        switch (operation) {
                            case 'removeRandomization':
                                $('#allocation_table_edit_record_'+responseObj.aid).html('');
                                $('#allocation_table_edit_record_'+responseObj.aid).parents('td:first').addClass('edited-highlight');
                                $('#allocation_table_remove_'+responseObj.aid).hide();
                                $('#allocation_table_edit_'+responseObj.aid).show();
                                break;
                            case 'manualRandomization':
                                $('#allocation_table_edit_record_'+responseObj.aid).html(responseObj.newval);
                                $('#allocation_table_edit_record_'+responseObj.aid).parents('td:first').addClass('edited-highlight');
                                $('#allocation_table_remove_'+responseObj.aid).show();
                                $('#allocation_table_edit_'+responseObj.aid).hide();
                                break;
                            case 'makeUnavailable':
                                $('#allocation_table_edit_record_'+responseObj.aid).html('<i class="fas fa-ban text-danger"></i>');
                                $('#allocation_table_edit_record_'+responseObj.aid).parents('td:first').addClass('edited-highlight');
                                $('#allocation_table_unavailable_'+responseObj.aid).show();
                                $('#allocation_table_edit_'+responseObj.aid).hide();
                                break;
                            case 'makeAvailable':
                                $('#allocation_table_edit_record_'+responseObj.aid).html('');
                                $('#allocation_table_edit_record_'+responseObj.aid).parents('td:first').addClass('edited-highlight');
                                $('#allocation_table_unavailable_'+responseObj.aid).hide();
                                $('#allocation_table_edit_'+responseObj.aid).show();
                                break;
                            case 'editTarget':
                                $('#allocation_table_edit_target_'+responseObj.aid).html(responseObj.newval);
                                $('#allocation_table_edit_target_'+responseObj.aid).parents('td:first').addClass('edited-highlight');
                                break;
                            case 'editAlternate':
                                $('#allocation_table_edit_alt_'+responseObj.aid).html(responseObj.newval);
                                $('#allocation_table_edit_alt_'+responseObj.aid).parents('td:first').addClass('edited-highlight');
                                break;
                            default:
                                throw new Exception('unexpected operation '+operation)
                                break;
                        }
                        $('#edit_alloc_table_dialog').dialog('close');
                    } catch (ex) {
                        console.log(ex);
                        alert(woops);
                    }
                }
            };
        </script>
        <?php
    }

    // get html content for edit allocation table dialogs
    public static function getEditAllocationTableDialogContent($operation, $aid, $sequence, $currentValue='') 
    {
        $operation = strip_tags($operation);
        $aid = intval($aid);
        $sequence = intval($sequence);
        $currentValue = strip_tags($currentValue);
        $prompt = '';
        $newValLabel = RCView::tt('random_187');
        $newValValue = '';
        $newValClass = '';
        switch ($operation) {
            case 'removeRandomization':
                $prompt = RCView::span(array('class'=>'text-danger'), '<i class="fas fa-exclamation-triangle mr-1"></i>'.RCView::tt('random_177')); // Confirm removal of this randomized allocation...
                $prompt .= RCView::p(array(), RCView::tt('random_173')." <b>$sequence</b> ".RCView::tt('colon')." ".RCView::tt('random_191')." <b>$currentValue</b>");
                $newValValue = '-';
                $newValClass = 'd-none';
                break;
            case 'manualRandomization':
                $prompt = RCView::tt('random_179'); // Manually assign an existing record to this allocation table entry.
                $prompt .= RCView::p(array(), RCView::tt('random_173')." <b>$sequence</b>");
                $newValLabel = RCView::tt('random_186'); // Record to assign
                break;
            case 'makeUnavailable':
                $prompt = RCView::tt('random_181'); // Make this allocation unavailable to future randomizations.
                $prompt .= RCView::p(array(), RCView::tt('random_173')." <b>$sequence</b>");
                $newValValue = '-';
                $newValClass = 'd-none';
                break;
            case 'makeAvailable':
                $prompt = RCView::tt('random_193'); // Restore availability of this allocation..
                $prompt .= RCView::p(array(), RCView::tt('random_173')." <b>$sequence</b>");
                $newValValue = '-';
                $newValClass = 'd-none';
                break;
            case 'editTarget':
                $prompt = RCView::tt('random_183'); // Specify a new value for the target field for this allocation table entry.
                $prompt .= RCView::p(array(), RCView::tt('random_173')." <b>$sequence</b> ".RCView::tt('colon')." ".RCView::tt('random_191')." <b>$currentValue</b>");
                break;
            case 'editAlternate':
                $prompt = RCView::tt('random_185'); // Specify a new value for the alternate field for this allocation table entry.
                $prompt .= RCView::p(array(), RCView::tt('random_173')." <b>$sequence</b> ".RCView::tt('colon')." ".RCView::tt('random_191')." <b>$currentValue</b>");
                break;
            default:
                $response = '0';
                break;
        }
        
        $response = "$prompt 
                    <div class='container' style='width:100%'>
                        <div class='row my-2'>
                            <div class='col-6 font-weight-bold'>".RCView::tt('random_189')." <span id='edit_alloc_table_test' style='color:#fff;' onclick='$(\"#edit_alloc_table_reason\").val(\"testing\");$(\"#edit_alloc_table_confirm\").val(\"CONFIRM\");'>test</span></div>
                            <div class='col-12'><input id='edit_alloc_table_reason' type='text' value='' class='x-form-text x-form-field'></div>
                        </div>
                        <div class='row my-2 $newValClass'>
                            <div class='col-6 font-weight-bold'>$newValLabel</div>
                            <div class='col-6 text-align-right'><input id='edit_alloc_table_newval' type='text' value='$newValValue' class='x-form-text x-form-field'></div>
                        </div>
                        <div class='row my-2'>
                            <div class='col-6 font-weight-bold'>".RCView::tt('random_188')."</div>
                            <div class='col-6 text-align-right'><input id='edit_alloc_table_confirm' type='text' value='' class='x-form-text x-form-field'></div>
                        </div>
                    </div>";
        return $response;
    }

    // check user-supplied details and perform update to allocation table (and redcap_data table when required)
    public static function editAllocationTableEntry($operation, $project_id, $aid, $seq, $reason, $currentval, $newval) 
    {
        global $Proj;

        if ($newval == $currentval) throw new Exception(RCView::tt('random_195',false));
        
        $aidsql = "select ra.*, r.target_event, r.target_field as target_field_name, p.data_table
                from redcap_randomization_allocation ra
                inner join redcap_randomization r on ra.rid=r.rid
                inner join redcap_projects p on r.project_id=p.project_id and ra.project_status=p.status
                where p.project_id=? and ra.aid=? limit 1";
        $q = db_query($aidsql, [$project_id, $aid]);

        if (db_num_rows($q) !== 1) throw new Exception("aid not consistent with project");

        $aidValues = db_fetch_assoc($q);
        $redcap_data = db_escape($aidValues['data_table']);
        if (!$Proj->longitudinal || $aidValues['target_event'] == '') {
            $aidValues['target_event'] = $Proj->firstEventId;
        }

        switch ($operation) {
            case 'removeRandomization':
                // validate aid/record assignment - clear aid and remove data value from record
                if ($currentval != $aidValues['is_used_by']) throw new Exception("Unexpected current value");

                self::updateAllocationTable($aid, 'is_used_by', null, $reason, false, "- Remove randomization");
                $sql = "delete from $redcap_data where project_id=? and event_id=? and field_name=? and record=? and instance is null limit 1"; // nb. can't have randomization on repeating form
                $dq = db_query($sql, [$project_id, $aidValues['target_event'], $aidValues['target_field_name'], $currentval]);
                if ($dq && db_affected_rows()) {
//                    $logRecord = $currentval;
//                    $display = "{$aidValues['target_field_name']} = ''";
                    $response = $aid;
//                    \Logging::logEvent($sql, "redcap_data", "UPDATE", $logRecord, $display, "Update record", $reason, "", $Proj->project_id, true, $aidValues['target_event']);
//                    \Logging::logEvent($sql, "redcap_data", "MANAGE", $logRecord, $display, "Remove randomization", $reason, "", $Proj->project_id, true, $aidValues['target_event']);
                } else {
                    throw new Exception("failed to remove data table value");
                }
                break;

            case 'manualRandomization':
                // validate aid/record exist and not already randomized - assign aid and add data value to record
                $armnum = $Proj->eventInfo[$aidValues['target_event']]['arm_num'];
                $recordExists = Records::recordExists($project_id, $newval, $armnum);
                if (!$recordExists) throw new Exception(RCView::tt('random_196',false).RCView::tt('colon').' '.$newval);
                $isRandomized = self::wasRecordRandomized($newval, $aidValues['rid']);
                if ($isRandomized) throw new Exception(RCView::tt('random_56',false).RCView::tt('colon').' '.$newval); // Already randomized: x

                // check record has stratification data corresponding to aid to allocate to
                $randAttr = self::getRandomizationAttributes($aidValues['rid']);
                if ($randAttr['group_by']=='DAG' || count($randAttr['strata']) > 0) {
                    list($fields, $group_id, $missing) = self::readStratificationData($aidValues['rid'], $newval);
                    if (count($missing) > 0) {
                        throw new Exception("Cannot allocate record '$newval'. Missing stratification data for fields: ".implode(',',$missing));
                    }
                    if (!empty($group_id) && $group_id!=$aidValues['group_id']) {
                        throw new Exception("Cannot allocate record '$newval'. Assigned DAG (id=$group_id) is incorrect for this stratum.");
                    }
                    $sfIdx = 0;
                    foreach ($fields as $fieldName => $recValue) {
                        $sfIdx++;
                        if ($recValue!=$aidValues["source_field$sfIdx"]) {
                            throw new Exception("Cannot allocate record '$newval'. Value for field '$fieldName' ('$recValue') is incorrect for this stratum.");
                        }
                    }
                }
                // allocate schedule entry and record in data table
                self::updateAllocationTable($aid, 'is_used_by', $newval, $reason, false, "- Randomize record (manual)");
                self::saveRandomizationResultToDataTable($aidValues['rid'], $newval);
                $response = $aid;
                break;

            case 'makeUnavailable':
                // validate aid not assigned - assign unique value aid+"-UNAVAILABLE" to hide from future randomisations
                if ($aidValues['is_used_by'] != '') throw new Exception("Cannot make used allocation unavailable");
                self::updateAllocationTable($aid, 'is_used_by', $aid."-UNAVAILABLE", $reason, false);
                $response = $aid;
                break;

            case 'makeAvailable':
                // validate aid not assigned - assign unique value aid+"-UNAVAILABLE" to hide from future randomisations
                if ($aidValues['is_used_by'] != $aid."-UNAVAILABLE") throw new Exception("Cannot make allocation available");
                self::updateAllocationTable($aid, 'is_used_by', null, $reason, false);
                $response = $aid;
                break;

            case 'editTarget':
                // validate aid not assigned and value is valid if choice field - update target_field with supplied value
                if ($aidValues['target_field'] != $currentval) throw new Exception("Unexpected current target field value");
                if ($aidValues['is_used_by'] != '') throw new Exception("Cannot edit target when allocated to record");
                $randAttr = self::getRandomizationAttributes($aidValues['rid']);
                if (!$randAttr['isBlinded']) {
                    // target is choice field - new value must be valid choice
                    $choices = parseEnum($Proj->metadata[$aidValues['target_field_name']]['element_enum']);
                    if (!array_key_exists($newval, $choices)) throw new Exception($newval.' '.RCView::tt('random_194',false).' '.$aidValues['target_field_name']);
                }
                self::updateAllocationTable($aid, 'target_field', $newval, $reason, false);
                $response = $aid;
                break;

            case 'editAlternate':
                // validate aid not assigned - update target_field_alt with supplied value
                if ($aidValues['target_field_alt'] != $currentval) throw new Exception("Unexpected current alternate value");
                self::updateAllocationTable($aid, 'target_field_alt', $newval, $reason, false);
                $response = $aid;
                break;

            default:
                $response = '0';
                break;
        }
        return $response;
    }

    // Read stratification field data and DAG for record - return array of fields with values, group_id (or null if missing or not required), array of missing required fields/redcap_data_access_group
    static function readStratificationData($rid, $record) {
        global $Proj, $user_rights;
        $randAttr = self::getRandomizationAttributes($rid);
        $group_id = null;
        $fields = array();
        $missing = array();
        
        if ((isset($randAttr['strata']) && is_array($randAttr['strata']) && count($randAttr['strata']) > 0) || (isset($randAttr['group_by']) && $randAttr['group_by'] == 'DAG')) {
            $recordData = Records::getData(array(
                'return_format'=>'array', 
                'records'=>[$record],
                'fields'=>array_merge([$Proj->table_pk,$randAttr['targetField']],array_keys($randAttr['strata'])),
                'groups'=>$user_rights['group_id'],
                'exportDataAccessGroups'=>1
            ));
        
            if ($randAttr['group_by']=='DAG') {
                if (!isset($recordData[$record][$randAttr['targetEvent']]['redcap_data_access_group']) || 
                        empty($recordData[$record][$randAttr['targetEvent']]['redcap_data_access_group'])) {
                    $missing[] = 'redcap_data_access_group';
                }
                $dagIdsByUniqueName = array_flip($Proj->getUniqueGroupNames());
                $group_id = $dagIdsByUniqueName[$recordData[$record][$randAttr['targetEvent']]['redcap_data_access_group']];
            }
        
            if (count($randAttr['strata']) > 0) {
                foreach ($randAttr['strata'] as $stratField => $stratFieldEvent) {
                    if (!isset($recordData[$record][$stratFieldEvent][$stratField]) || 
                            $recordData[$record][$stratFieldEvent][$stratField] === "") {
                        $missing[] = "$stratField (event $stratFieldEvent)";
                    } else {
                        $fields[$stratField] = $recordData[$record][$stratFieldEvent][$stratField];
                    }
                }
            }
        }
        return array($fields, $group_id, $missing);
    }

    // Check and perform any randomizations that should be triggered for the current event/form and record
    static function realtimeRandomization($record, $event_id, $instrument, $repeat_instance)
    {
        global $Proj, $user_rights;

        if (empty($record) || empty($event_id) || empty($instrument)) return;

        $isSurveyPage = (defined("PAGE") && PAGE == "surveys/index.php");

        $allRand = self::getAllRandomizationAttributes();

        foreach($allRand as $rid => $randAttr) {
            if ($randAttr['triggerOption']==0) continue;
            if ($randAttr['triggerOption']==1 && ($user_rights['random_perform']!=1 || $isSurveyPage)) continue; // Do not allow trigger option 1 unless user has randomize prileges and only on data entry form
            if (!($randAttr['triggerEvent']==$event_id && $randAttr['triggerForm']==$instrument)) continue;

            // If record has already been randomized, then skip this loop
            if (self::wasRecordRandomized($record, $rid)) continue;

            list($fields, $group_id, $missing) = self::readStratificationData($rid, $record);

            if (count($missing) > 0) { // stratification field or dag missing
                $missingFields = array();
                foreach ($missing as $fldevt) {
                    $split = explode(' ',$fldevt);
                    $missingFields[] = $split[0];
                }
                Logging::logEvent("", "redcap_data", "MANAGE", $record, $Proj->table_pk." = '$record'\nrandomization_id = $rid", "Randomize record (via trigger) failed due to missing stratification data for field(s): ".implode(',',$missingFields));
                continue;
            }

            $trigger = REDCap::evaluateLogic(
                $randAttr['triggerLogic'],
                $Proj->project_id,
                $record,
                $event_id,
                $repeat_instance,
                ($Proj->isRepeatingForm($event_id, $instrument) ? $instrument : ""),
                $instrument
            );

            if ($trigger) {
                // Randomize the record
                $aid = self::randomizeRecord($rid, $record, $fields, $group_id);
                // Now set the field data values for the randomization field
                if (isinteger($aid) && $aid > 0 && self::saveRandomizationResultToDataTable($rid, $record)) {
                    // Log the data change
                    $targetVals = self::getRandomizedValue($record, $rid);
                    Logging::logEvent("", "redcap_data", "update", $record, "{$targetVals[0]} = '{$targetVals[1]}'", ($isSurveyPage ? "Update survey response" : "Update record"), "", "", "", true, $event_id, $repeat_instance);
                    // Log the randomization event
                    Logging::logEvent("", "redcap_data", "MANAGE", $record, $Proj->table_pk." = '$record'\nrandomization_id = $rid", "Randomize record (via trigger)");
                }
            }
        }
    }

    // Save randomization result to redcap_data (no saving of stratification data required)
    static function saveRandomizationResultToDataTable($rid, $record) 
    {
        global $Proj;
        $rid = self::getRid($rid);
        if (!$rid) return false;

        $randAttr = self::getRandomizationAttributes($rid);

        // save the result to the record's target event/field (can't update target field using Records::saveData() now a randomization result is present)
        list ($target_field, $target_field_value) = Randomization::getRandomizedValue($record, $rid);

        // Build data array
        $data = [[$Proj->table_pk=>$record, $target_field=>$target_field_value]];
        if ($Proj->longitudinal) {
            $data[0]['redcap_event_name'] = $Proj->getUniqueEventNames($randAttr['targetEvent']);
        }
        if ($Proj->isRepeatingFormOrEvent($randAttr['targetEvent'], $Proj->metadata[$target_field]['form_name'])) {
            $data[0]['redcap_repeat_instance'] = '1';
        }
        if ($Proj->isRepeatingForm($randAttr['targetEvent'], $Proj->metadata[$target_field]['form_name'])) {
            $data[0]['redcap_repeat_instrument'] = $Proj->metadata[$target_field]['form_name'];
        }
        $args = ['project_id'=>$Proj->project_id, 'dataFormat'=>'json-array', 'data'=>$data, 'dataLogging'=>false, 'bypassRandomizationCheck'=>true];
        $response = Records::saveData($args);

        return empty($response['errors']);
    }
}
