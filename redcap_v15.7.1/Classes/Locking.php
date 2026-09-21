<?php


/**
 * LOCKING
 */
class Locking
{
	// Array of record-event-fields that are locked in a project
	public $locked = array();
	public $lockedWhole = array();

	public function lockWholeRecord($project_id, $record, $arm=1)
	{
		$Proj = new Project($project_id);
		if ($record == '' || !isset($Proj->events[$arm]['id'])) return false;
		$arm_id = $Proj->events[$arm]['id'];
		$sql = "insert into redcap_locking_records (project_id, record, arm_id, username, timestamp)
				values ($project_id, '" . db_escape($record) . "', $arm_id, '" . db_escape(USERID) . "', '".NOW."')";
		return db_query($sql);
	}

	public function unlockWholeRecord($project_id, $record, $arm=1)
	{
		$Proj = new Project($project_id);
		if ($record == '' || !isset($Proj->events[$arm]['id'])) return false;
		$arm_id = $Proj->events[$arm]['id'];
		$sql = "delete from redcap_locking_records where project_id = $project_id and record = '" . db_escape($record) . "' and arm_id = $arm_id";
		return db_query($sql);
	}

	public function isWholeRecordLocked($project_id, $record, $arm=1)
	{
		$Proj = new Project($project_id);
		if ($record == '' || !isset($Proj->events[$arm]['id'])) return false;
		$arm_id = $Proj->events[$arm]['id'];
		$sql = "select 1 from redcap_locking_records where project_id = $project_id and record = '" . db_escape($record) . "' and arm_id = $arm_id";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}

	public function getWholeRecordLockTimeUser($project_id, $record, $arm=1)
	{
		$Proj = new Project($project_id);
		if ($record == '' || !isset($Proj->events[$arm]['id'])) return false;
		$arm_id = $Proj->events[$arm]['id'];
		$sql = "select username, timestamp from redcap_locking_records where project_id = $project_id and record = '" . db_escape($record) . "' and arm_id = $arm_id";
		$q = db_query($sql);
		$row = db_fetch_assoc($q);
		return array($row['username'], $row['timestamp']);
	}

	// Get array of all records/arms that are locked in a project using record-level locking
	public function findLockedWholeRecord($project_id, $records=array(), $arms=array(), $alsoReturnUsername=false)
	{
		// Build SQL
		$Proj = new Project($project_id);
		if (!is_array($records)) $records = array($records);
		$sql_records = empty($records) ? "" : " and record in (".prep_implode($records).")";
		if (!is_array($arms)) $arms = array($arms);
		$sql_arms = "";
		if (!empty($arms)) {
			$arm_ids = array();
			foreach ($arms as $this_arm) {
				if (!is_numeric($this_arm)) continue;
				$arm_ids[] = $Proj->getArmIdFromArmNum($this_arm);
			}
			if (!empty($arm_ids)) {
				$sql_arms = " and arm_id in (".prep_implode($arm_ids).")";
			}
		}
		## LOCKING CHECK: Get all forms that are locked for the uploaded records
		$sql = "select record, arm_id, timestamp, username from redcap_locking_records
				where project_id = $project_id $sql_arms";
		// Deal with long queries
		if (strlen($sql.$sql_records) > 1000000) {
			$checkRecordNameEachLoop = true;
		} else {
			$sql .= $sql_records;
			$checkRecordNameEachLoop = false;
		}
		$locked = array();
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// If we need to validate the record in each loop, then check.
			if ($checkRecordNameEachLoop && !in_array($row['record'], $records)) continue;
			if ($alsoReturnUsername) {
				$this->lockedWhole[$row['record']][$row['arm_id']] = array('timestamp'=>$row['timestamp'], 'username'=>$row['username']);
			} else {
				$this->lockedWhole[$row['record']][$row['arm_id']] = $row['timestamp'];
			}
		}
	}


	// Display record-level locking PDF confirmation page, if applicable
	public static function renderRecordLockingPdfFrame($record)
	{
		global $isTablet, $isMobileDevice, $isIOS;
		$pdfUrlReal = APP_PATH_WEBROOT."index.php?route=PdfController:index&pid=".PROJECT_ID."&id=".htmlspecialchars($record, ENT_QUOTES)."&compact=1&display=inline";
		// Output iframe html
		print "<div id=\"recordLockPdfConfirmDialog\" class=\"simpleDialog\" data-rc-lang-attrs=\"title=data_entry_476\" title=\"".RCView::tt_js2("data_entry_476")."\">
					<div style='margin:10px;font-size:14px;'>".RCView::tt("data_entry_477")."</div>
					<div style='margin:5px;padding:3px;border:1px solid #ccc;'>
					    ".PDF::renderInlinePdfContainer($pdfUrlReal)."
					</div>
					<div id='record_lock_pdf_confirm_checkbox_div' class='yellow' style='font-size:14px;margin:20px 20px 10px;'>
						<label id='record_lock_pdf_confirm_checkbox_label' class='opacity50' style='margin:5px 0;text-indent:-22px;margin-left:40px;cursor:pointer;'>
							<input type='checkbox' id='record_lock_pdf_confirm_checkbox'> ".RCView::tt("data_entry_490")." 
						</label>
					</div>
				</div>";
	}


	// Get array of all record-event-fields that are locked in a project, and add to $locked array
	public function findLocked($Proj, $records=array(), $fields=array(), $events=array())
	{
		// Build SQL
		$project_id = $Proj->project_id;
        if (!is_array($events)) $events = array($events);
        $eventSql = empty($events) ? "" : " and l.event_id in (".prep_implode($events).")";
		if (!is_array($records)) $records = array($records);
        $sql_records = empty($records) ? "" : " and l.record in (".prep_implode($records).")";
		if (!is_array($fields)) $fields = array($fields);
        $sql_fields = empty($fields) ? "" : " and m.field_name in (".prep_implode($fields).")";
		## LOCKING CHECK: Get all forms that are locked for the uploaded records
		$sql = "select l.record, l.event_id, l.instance, m.field_name, m.element_type, m.element_enum
				from redcap_locking_data l, redcap_metadata m
				where m.project_id = $project_id $eventSql
				and l.project_id = m.project_id and m.form_name = l.form_name";
        // Deal with long queries
        if (strlen($sql.$sql_fields) > 1000000) {
            $checkFieldNameEachLoop = true;
        } else {
            $sql .= $sql_fields;
            $checkFieldNameEachLoop = false;
        }
        if (strlen($sql.$sql_records) > 1000000) {
            $checkRecordNameEachLoop = true;
        } else {
            $sql .= $sql_records;
            $checkRecordNameEachLoop = false;
        }
		$locked = array();
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
            // If we need to validate the field name in each loop, then check.
            if ($checkFieldNameEachLoop && !in_array($row['field_name'], $fields)) continue;
            // If we need to validate the record in each loop, then check.
            if ($checkRecordNameEachLoop && !in_array($row['record'], $records)) continue;

			if ($row['element_type'] == 'checkbox') {
				foreach (array_keys(parseEnum($row['element_enum'])) as $this_code) {
					$chkbox_field_name = $row['field_name'] . "___" . Project::getExtendedCheckboxCodeFormatted($this_code);
					$this->locked[$row['record']][$row['event_id']][$row['instance']][$chkbox_field_name] = "";
				}
			} else {
				$this->locked[$row['record']][$row['event_id']][$row['instance']][$row['field_name']] = "";
			}
		}
	}

	// Get all files stored by PDF Record-locking. If provide $doc_id, then return just that file's attributes as an array.
	public static function getLockedRecordPdfFiles(&$Proj, $group_id=null, $doc_id=null)
	{
		// Filter by DAG, if needed
		$dagsql = "";
		if (is_numeric($group_id)) {
			$dagsql = "and a.record in (" . prep_implode(Records::getRecordList($Proj->project_id, $group_id)) . ")";
		}
		// Query table
		$files = array();
		$sql = "select e.stored_date, e.doc_size, e.doc_name, a.*
				from redcap_locking_records_pdf_archive a, redcap_edocs_metadata e
				where e.doc_id = a.doc_id $dagsql
				and e.delete_date is null and e.project_id = " . $Proj->project_id;
		if (is_numeric($doc_id)) {
			$sql .= " and e.doc_id = $doc_id";
		}
		$sql .= " order by e.doc_id desc";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if ($doc_id !== null) return $row;
			else $files[] = $row;
		}
		return $files;
	}

}
