<?php

use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\AutoAdjudicator;
use REDCap\Context;
use MultiLanguageManagement\MultiLanguage;
use Vanderbilt\REDCap\Classes\Cache\REDCapCache;
use Vanderbilt\REDCap\Classes\Cache\CacheFactory;
use Vanderbilt\REDCap\Classes\Cache\States\DisabledState;
use Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies\ProjectActivityInvalidation;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

$draft_preview_enabled = Design::isDraftPreview(PROJECT_ID);

$cacheManager = CacheFactory::manager(PROJECT_ID);
$cacheOptions = [
	// REDCapCache::OPTION_TTL => null,
	REDCapCache::OPTION_INVALIDATION_STRATEGIES => [ProjectActivityInvalidation::signature($project_id)],
];
// Set to true to disable the cache (or add &no-cache to URL
$cache_disabled = false || isset($_GET['no-cache']);
if ($cache_disabled) {
	$cacheManager->setState(new DisabledState());
}

## PERFORMANCE: Kill any currently running processes by the current user/session on THIS page
System::killConcurrentRequests(10);

// Increase memory limit in case needed for intensive processing
System::increaseMemory(2048);

$errorMsg = "";

// Add DAG to uistate if passed in query string for users NOT in a DAG
$dags = $Proj->getGroups();
$uiStateDag = UIState::getUIStateValue($project_id, 'record_status_dashboard', 'dag');
if (isset($_GET['dag']) && isinteger($_GET['dag']) && $user_rights['group_id'] == "" && ($uiStateDag == '' || isset($dags[$uiStateDag]))) {
	if ($uiStateDag != $_GET['dag']) {
		UIState::saveUIStateValue($project_id, 'record_status_dashboard', 'dag', $_GET['dag']);
	}
} elseif (!isset($_GET['dag']) && $uiStateDag != '' && $user_rights['group_id'] == "" && isset($dags[$uiStateDag])) {
	$_GET['dag'] = $uiStateDag;
} else {
    if (isset($_GET['dag']) && $_GET['dag'] != $uiStateDag) {
        $_GET['dag'] = null;
    }
	if ($uiStateDag != '') {
		UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'dag');
	}
}

// Add arm to uistate if passed in query string
if ($multiple_arms) {
	$uiStateArm = UIState::getUIStateValue($project_id, 'record_status_dashboard', 'arm');
	if (isset($_GET['arm']) && is_numeric($_GET['arm']) && isset($Proj->events[$_GET['arm']])) {
		if ($uiStateArm != $_GET['arm']) {
			UIState::saveUIStateValue($project_id, 'record_status_dashboard', 'arm', (int)$_GET['arm']);
		}
	} elseif (!isset($_GET['arm']) && $uiStateArm != '') {
		$_GET['arm'] = $uiStateArm;
	} else {
		$_GET['arm'] = 1;
		if ($uiStateArm != '') {
			UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'arm');
		}
	}
} else {
	$_GET['arm'] = false;
}

// Add "records per page" to uistate if passed in query string
$uiStateNumRecs = UIState::getUIStateValue($project_id, 'record_status_dashboard', 'num_per_page');
if ($uiStateNumRecs == 'ALL') {
    $uiStateNumRecs == '';
    UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'num_per_page');
}
if (isset($_GET['num_per_page'])) {
	if ($uiStateNumRecs != $_GET['num_per_page'] && $_GET['num_per_page'] != 'ALL') { // Save UIState but not if set to show ALL records (page could be too large and never load in the future)
		UIState::saveUIStateValue($project_id, 'record_status_dashboard', 'num_per_page', $_GET['num_per_page']);
	}
} elseif (!isset($_GET['num_per_page']) && $uiStateNumRecs != '') {
	$_GET['num_per_page'] = $uiStateNumRecs;
} else {
	unset($_GET['num_per_page']);
	if ($uiStateNumRecs != '') {
		UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'num_per_page');
	}
}

// Add "page number" to uistate if passed in query string
$uiStatePageNum = UIState::getUIStateValue($project_id, 'record_status_dashboard', 'pagenum');
if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum'])) {
	if ($uiStatePageNum != $_GET['pagenum']) {
		UIState::saveUIStateValue($project_id, 'record_status_dashboard', 'pagenum', (int)$_GET['pagenum']);
	}
} elseif (!isset($_GET['pagenum']) && $uiStatePageNum != '') {
	$_GET['pagenum'] = $uiStatePageNum;
} else {
	unset($_GET['pagenum']);
	if ($uiStatePageNum != '') {
		UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'pagenum');
	}
}

// Get/save UI value to remember last dashboard viewed in this project
$uiStateRdId = UIState::getUIStateValue($project_id, 'record_status_dashboard', 'rd_id');
$rd_id = (isset($_GET['rd_id']) && is_numeric($_GET['rd_id'])) ? (int)$_GET['rd_id'] : null;
if ($uiStateRdId != $rd_id) {
	if (!empty($rd_id)) {
		UIState::saveUIStateValue($project_id, 'record_status_dashboard', 'rd_id', $rd_id);
	} elseif (!empty($uiStateRdId) && empty($rd_id) && isset($_GET['rd_id'])) {
		UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'rd_id');
	} elseif (!empty($uiStateRdId) && empty($rd_id)) {
		$rd_id = $uiStateRdId;
	} else {
		UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'rd_id');
	}
}

// Get dashboard settings of current dashboard
$dashboard = RecordDashboard::getRecordDashboardSettings($rd_id);
// In case a dashboard was deleted but still has it's uistate saved
if (is_numeric($rd_id) && $dashboard['rd_id'] == '') {
	$rd_id = null;
	UIState::removeUIStateValue($project_id, 'record_status_dashboard', 'rd_id');
}

// Rapid Retrieval: Cache salt
$cacheOptions[REDCapCache::OPTION_SALT] = [];
$cacheOptions[REDCapCache::OPTION_SALT][] = ['dag'=>$user_rights['group_id']];
// If the dashboard has filter logic containing datediff() with today or now, then add more salt since these will cause different results with no data actually changing.
if (strpos($dashboard['filter_logic'] ?? '', 'datediff') !== false) {
    list ($ddWithToday, $ddWithNow) = containsDatediffWithTodayOrNow($dashboard['filter_logic']);
    if ($ddWithNow) $cacheManager->setState(new DisabledState());  // disable the cache since will never be used
    elseif ($ddWithToday) $cacheOptions[REDCapCache::OPTION_SALT][] = ['datediff'=>TODAY];
}
// Place all Form Display Logic into a single string to test it for cache-related issues
$formDisplayLogicAll = FormDisplayLogic::getAndedFormDisplayRules($project_id);
// If the dashboard has FDL containing datediff() with today or now, then add more salt since these will cause different results with no data actually changing.
if (strpos($formDisplayLogicAll, 'datediff') !== false) {
    list ($ddWithToday, $ddWithNow) = containsDatediffWithTodayOrNow($formDisplayLogicAll);
    if ($ddWithNow) $cacheManager->setState(new DisabledState());  // disable the cache since will never be used
    elseif ($ddWithToday) $cacheOptions[REDCapCache::OPTION_SALT][] = ['datediff'=>TODAY];
}
// If the dashboard has filter logic or FDL containing a [user-X] smart variable, then add the USERID to the salt
if (strpos($dashboard['filter_logic'] ?? '', '[user-') !== false || strpos($formDisplayLogicAll, '[user-') !== false) {
    $cacheOptions[REDCapCache::OPTION_SALT][] = ['user'=>USERID];
}

// Set arm manually if saved in custom dashboard
$showArmTabs = $multiple_arms;
if ($multiple_arms && is_numeric($dashboard['arm'])) {
	$_GET['arm'] = $dashboard['arm'];
	$showArmTabs = false;
}
// Get selected events/forms array to limit columns
$selected_forms_events_array = $Proj->convertSelectedFormsEventsFromBackendAsArray($dashboard['selected_forms_events']);

// Set dashboard title and instructions
$instructions = ($dashboard['rd_id'] == '') ? RCView::tt("data_entry_176") : trim(filter_tags($dashboard['description']));
$title = ($dashboard['rd_id'] == '') ? (RCView::tt("global_91")." ".RCView::tt("bottom_61")) : strip_tags($dashboard['title']);

// Set vertical/horizontal orientation values for table headers
$th_span1 = $th_span2 = '';
$th_width = 'width:35px;';
if ($dashboard['orientation'] == 'V') {
	$th_span1 = '<span class="vertical-text"><span class="vertical-text-inner">';
	$th_span2 = '</span></span>';
	$th_width = '';
}

// Get list of all records
$recordNames = Records::getRecordList(PROJECT_ID, ($user_rights['group_id'] != '' ? $user_rights['group_id'] : ($_GET['dag']??null)), true, false, $_GET['arm']);
if (!is_array($recordNames)) $recordNames = [];
$recordNames = $recordNamesReal = array_values($recordNames);
// For DDE user, append DDE#
$isDDE = ($Proj->project['double_data_entry'] && isset($user_rights['double_data']) && $user_rights['double_data'] != 0);
if ($isDDE) {
	foreach ($recordNamesReal as &$this_record) {
		$this_record .= "--" . $user_rights['double_data'];
	}
}

// Apply filter logic (if defined for a custom dashboard)
if (trim($dashboard['filter_logic']??"") != '' && !empty($recordNames))
{
	// Set events
	$events = (is_numeric($_GET['arm']) && isset($Proj->events[$_GET['arm']])) ? array_keys($Proj->events[$_GET['arm']]['events']) : array_keys($Proj->eventInfo);
	// Get record names
	try {
        $getDataParams = array('project_id'=>PROJECT_ID, 'return_format'=>'array', 'records'=>$recordNamesReal, 'fields'=>$Proj->table_pk,
                                'events'=>$events, 'filterLogic'=>$dashboard['filter_logic'], 'returnEmptyEvents'=>true);
		$recordNames = array_keys(Records::getData($getDataParams));
		if ($isDDE && !empty($recordNames)) {
			foreach ($recordNames as &$this_record) {
				$this_record = removeDDEending($this_record);
			}
		}
	} catch (Exception $e) {
		$errorMsg = RCView::tt("data_entry_368");
		$recordNames = array();
	}
}

// If using Order Records By feature, then order records by that field's value instead of by record name
$preserve_record_order = false;
if ($errorMsg == '' && !empty($recordNames) && !($dashboard['sort_order'] == 'ASC' && ($dashboard['sort_field_name'] == '' || $dashboard['sort_field_name'] == $Proj->table_pk)))
{
	$preserve_record_order = true;
	// Get all values for the Order Records By field
	$order_id_by_records = Records::getData('array', $recordNamesReal, $dashboard['sort_field_name'], $dashboard['sort_event_id']);
	// Isolate values only into separate array
	$order_id_by_values = array();
	foreach ($recordNames as $this_record) {
		$this_record2 = $isDDE ? addDDEending($this_record) : $this_record;
		$val = "";
        if ($dashboard['sort_field_name'] == $Proj->table_pk) {
            $val = $this_record2;
        } elseif (isset($order_id_by_records[$this_record2][$dashboard['sort_event_id']][$dashboard['sort_field_name']])) {
			$val = $order_id_by_records[$this_record2][$dashboard['sort_event_id']][$dashboard['sort_field_name']];
		}
		$order_id_by_values[$this_record] = strtolower($val); // Make lowercase since we want to do case-insensitive ordering
		unset($order_id_by_records[$this_record]);
	}
	// Now sort $formStatusValues by values in $order_id_by_values
	$field_type = $Proj->metadata[$dashboard['sort_field_name']]['element_type'];
	$val_type = $Proj->metadata[$dashboard['sort_field_name']]['element_validation_type'];
	$sortFieldIsNumber = (($dashboard['sort_field_name'] == $Proj->table_pk && $Proj->project['auto_inc_set']) 
						 || $val_type == 'float' || $val_type == 'int' || $field_type == 'calc' || $field_type == 'slider');
	array_multisort($order_id_by_values, ($dashboard['sort_order'] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber ? SORT_NUMERIC : SORT_STRING), $recordNames);
	unset($order_id_by_values, $order_id_by_records);
}
// No longer need this
unset($recordNamesReal);



$numRecords = count($recordNames);

// Remove records from $formStatusValues array based upon page number
if (isset($_GET['num_per_page'])) {
	$num_per_page = is_numeric($_GET['num_per_page']) ? (int)$_GET['num_per_page'] : $numRecords;
} else {
	$num_per_page = ($numRecords <= 100) ? $numRecords : 100;
}
if ($num_per_page < 1) $num_per_page = 100;
$limit_begin  = 0;
if (!isset($_GET['pagenum']) || $num_per_page*($_GET['pagenum']-1) > $numRecords) {
	$_GET['pagenum'] = 1;
} elseif (isset($_GET['pagenum']) && is_numeric($_GET['pagenum']) && $_GET['pagenum'] > 1) {
	$limit_begin = ((int)$_GET['pagenum'] - 1) * $num_per_page;
}

// Do not slice array if showall flag is in query string
if ($_GET['pagenum'] != 'ALL' && $numRecords > $num_per_page) {
	$recordNamesThisPage = array_slice($recordNames, $limit_begin, $num_per_page, true);
} else {
	$recordNamesThisPage = $recordNames;
}

// Cache the record-DAG assignment list in case we're using [record-dag-X] smart variables in the CRL
if (!empty($dags)) {
    Records::cacheMultipleRecordsGroupId($project_id, $recordNamesThisPage);
}

// Obtain custom record label & secondary unique field labels for ALL records.
if ($isDDE) {
	$recordNamesThisPageDDE = [];
	foreach ($recordNamesThisPage as $this_record) {
		$recordNamesThisPageDDE[] = $this_record . "--" . $user_rights['double_data'];
	}
	$extra_record_labels = $cacheManager->getOrSet([Records::class, 'getCustomRecordLabelsSecondaryFieldAllRecords'], [$recordNamesThisPageDDE], $cacheOptions);
} else {
	$extra_record_labels = $cacheManager->getOrSet([Records::class, 'getCustomRecordLabelsSecondaryFieldAllRecords'], [$recordNamesThisPage], $cacheOptions);
}

// Get form status of just this page's records
if ($errorMsg == '') {
	$formStatusValues = $cacheManager->getOrSet([Records::class, 'getFormStatus'], [PROJECT_ID, ($isDDE ? $recordNamesThisPageDDE : $recordNamesThisPage), (is_numeric($dashboard['arm']) ? $dashboard['arm'] : $_GET['arm']),
        ($_GET['dag']??null), $selected_forms_events_array, $preserve_record_order], $cacheOptions);
}
if (!isset($formStatusValues) || !is_array($formStatusValues)) {
    $formStatusValues = array();
}
$numRecordsThisPage = count($formStatusValues);
unset($recordNamesThisPageDDE);

 
## LOCKING & E-SIGNATURES
$displayLocking = $displayEsignature = false;
// Check if need to display this info at all
$sql = "select display, display_esignature from redcap_locking_labels
		where project_id = $project_id and form_name in (".prep_implode(array_keys($Proj->forms)).")";
$q = db_query($sql);
if (db_num_rows($q) == 0) {
	$displayLocking = true;
} else {
	$lockFormCount = count($Proj->forms);
	$esignFormCount = 0;
	while ($row = db_fetch_assoc($q)) {
		if ($row['display'] == '0') $lockFormCount--;
		if ($row['display_esignature'] == '1') $esignFormCount++;
	}
	if ($esignFormCount > 0) {
		$displayLocking = $displayEsignature = true;
	} elseif ($lockFormCount > 0) {
		$displayLocking = true;
	}
}
if ($GLOBALS['esignature_enabled_global'] != '1') $displayEsignature = false;

// Build drop-down list of page numbers
$num_pages = ceil($numRecords/$num_per_page);
//$pageNumDropdownOptions = array('ALL'=>'-- '.$lang['docs_44'].' --');
$pageNumDropdownOptions = array();
for ($i = 1; $i <= $num_pages; $i++) {
	$end_num   = $i * $num_per_page;
	$begin_num = $end_num - $num_per_page + 1;
	$value_num = $end_num - $num_per_page;
	if ($end_num > $numRecords) $end_num = $numRecords;
	$pageNumDropdownOptions[$i] = "{$lang['survey_132']} $i {$lang['survey_133']} $num_pages{$lang['colon']} \"".removeDDEending($recordNames[$begin_num-1])."\" {$lang['data_entry_216']} \"".removeDDEending($recordNames[$end_num-1])."\""; // ttfy
}
if ($num_pages == 0) {
	$pageNumDropdownOptions[0] = "0";
}

$dagsDropdown = '';
if ($user_rights['group_id'] == '' && !empty($dags))
{
	$dagOptions = array('ALL'=>'-- ' . $lang['docs_44'] . ' --', '0'=>$lang['data_access_groups_ajax_23']);
	$dag = isset($_GET['dag']) && isinteger($_GET['dag']) ? $_GET['dag'] : 'ALL';

	foreach( $dags as $k => $v )
	{
		$dagOptions[$k] = $v;
	}

	$dagsDropdown = RCView::div(array('style'=>"margin-bottom:7px;"), $lang['data_entry_261'] . '&nbsp;&nbsp;' .
						RCView::select(array('class'=>'x-form-text x-form-field', 'style'=>'color:#008000;',
							'onchange'=>"showProgress(1);window.location.href=updateParameterInURL(window.location.href,'dag',this.value);"),
							$dagOptions, $dag));
}


		
// Build drop-down list of records per page options, including any legacy values
$recordsPerPageOptions = array('ALL' => $lang['docs_44'] . " (".User::number_format_user($numRecords).")");
$defaultRecordsPerPage = array(10,25,50,100,250,500,1000);
if (is_numeric($num_per_page) && !is_array($num_per_page)) {
	array_push($defaultRecordsPerPage,$num_per_page);
	sort($defaultRecordsPerPage);
}
foreach ($defaultRecordsPerPage as $opt) {
	$recordsPerPageOptions[$opt] = $opt;
}

$otherOptionsDropdown = $newDashBtn = "";
// Create dashboard option
if ($user_rights['design']) {
    $newDashBtn = RCView::button(array('class' => 'btn btn-defaultrc btn-xs float-left fs12 ml-2', 'style'=>'color:green;', 'onclick' => "openDashboardSetup('');"),
        RCView::fa('fas fa-plus mr-1') . $lang['data_entry_335']
    );
}
// Bulk Record Delete option
$li1 = "";
if (UserRights::canDeleteWholeOrPartRecord() && $GLOBALS['bulk_record_delete_enable_global'] == '1') {
    $li1 = RCView::li([],
        RCView::a(array('class'=>'dropdown-item fs12', 'href'=>'javascript:;', 'onclick'=>"window.location.href=app_path_webroot+'index.php?route=BulkRecordDeleteController:index&pid='+pid;"),
            RCView::fa('fa-regular fa-times-circle mr-1') .
            $lang['data_entry_619']
        )
    );
}
// Button
if ($li1 != '') {
    $otherOptionsDropdown = RCView::div(array('class' => 'float-right dropdown ml-3'),
        RCView::button(array('id' => 'other-options-btn', 'class' => 'btn btn-defaultrc btn-xs dropdown-toggle fs12 text-primaryrc', 'data-bs-toggle' => "dropdown"),
            RCView::fa('fa-solid fa-gears mr-1') .
            $lang['data_entry_655']
        ) .
        RCView::ul(array('class' => 'dropdown-menu', 'aria-labelledby' => 'other-options-btn'),
            $li1
        )
    );
}

// Custom dashboards list
$dashboards = RecordDashboard::getRecordDashboardsList(true);
$dashboardNames = array();
foreach ($dashboards as $rd_id2=>$attr) {
	$dashboardNames[$rd_id2] = strip_tags($attr['title']);
}
$dashboardsDropdown = RCView::div(array('class'=>'clearfix', 'style'=>"margin-bottom:7px;"), 
						RCView::div(array('style'=>'float:left;'),
							$lang['data_entry_334'] . '&nbsp;&nbsp;' .
                            // Custom dashboard drop-down list
							RCView::select(array('class'=>'x-form-text x-form-field', 'style'=>'color:#A00000;max-width:250px;',
							'onchange'=>"showProgress(1);window.location.href=updateParameterInURL(window.location.href,'rd_id', this.value);"),
							$dashboardNames, $rd_id) .
                            // "Modify" button
							(!(is_numeric($rd_id) && $user_rights['design']) ? '' :
								RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs12 mr-3', 'onclick'=>"openDashboardSetup('$rd_id');"),
                                    RCView::fa('fas fa-pencil-alt mr-1') . $lang['design_169']
								)
							)
						) .
                        $otherOptionsDropdown . $newDashBtn
					  );

// Settings section
$dashboardOptionsBox = RCView::div(array('class'=>'chklist clearfix','style'=>'padding:8px 15px 7px;margin:5px 0 20px;max-width:900px;'),
						$dashboardsDropdown .
						$dagsDropdown .
						RCView::div(array('style'=>'float:left;'),
							$lang['data_entry_177'] .
							RCView::select(array('class'=>'x-form-text x-form-field','style'=>'margin-left:8px;margin-right:4px;',
								'onchange'=>"showProgress(1);window.location.href=updateParameterInURL(window.location.href,'pagenum',this.value);"),
								$pageNumDropdownOptions, $_GET['pagenum'], 500) .
							$lang['survey_133'].
							RCView::span(array('style'=>'font-weight:bold;margin:0 4px;font-size:13px;'),
								User::number_format_user($numRecords)
							) .
							$lang['data_entry_173']
						) .
						// Num records per page
						RCView::div(array('style'=>'float:right;'),
							RCView::select(
								array('class'=>'x-form-text x-form-field',
									'style'=>'margin-right:4px;',
									'onchange'=>"showProgress(1);window.location.href=updateParameterInURL(window.location.href,'num_per_page',this.value);"
								), $recordsPerPageOptions, ($num_per_page == $numRecords ? 'ALL' : $num_per_page)
							) . " " . $lang['data_entry_332']
						)
					);
if ($user_rights['design']) $dashboardOptionsBox .= replaceUrlOldRedcapVersion($cacheManager->getOrSet([RecordDashboard::class, 'renderSetup'], [$dashboard], $cacheOptions));

// Determine if Real-Time Web Service is enabled, mapping is set up, and that this user has rights to adjudicate
$showRTWS = (((DynamicDataPull::isEnabledInSystem() && DynamicDataPull::isEnabled($project_id)) || (DynamicDataPull::isEnabledInSystemFhir() && DynamicDataPull::isEnabledFhir($project_id))) && $DDP->userHasAdjudicationRights());

// Obtain a list of all instruments used for all events (used to iterate over header rows and status rows)
$formsEvents = $formsEventsColspan = array();
// Loop through each event and output each where this form is designated
if ($dashboard['group_by'] == 'event') {
	foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
		// If we are viewing a specific arm, then only show events for the current arm
		if ($multiple_arms && is_numeric($_GET['arm']) && !isset($Proj->events[$_GET['arm']]['events'][$this_event_id])) {
			continue;
		}
		// Loop through forms
		foreach ($these_forms as $form_name) {
			// If user does not have form-level access to this form, then do not display it
			if (UserRights::hasDataViewingRights($user_rights['forms'][$form_name], "no-access")) continue;
			if (!isset($selected_forms_events_array[$this_event_id])) continue;
			if (isset($selected_forms_events_array[$this_event_id]) && !in_array($form_name, $selected_forms_events_array[$this_event_id])) continue;
			// Add to array
			$formsEvents[] = array('form_name'=>$form_name, 'event_id'=>$this_event_id);
			// Set colspan for event/form, depending on group_by
			if (isset($formsEventsColspan[$this_event_id])) {
				$formsEventsColspan[$this_event_id]++;
			} else {
				$formsEventsColspan[$this_event_id] = 1;
			}
		}
	}
} 
else {
	foreach (array_keys($Proj->forms) as $form_name) {
		// If user does not have form-level access to this form, then do not display it
		if (UserRights::hasDataViewingRights($user_rights['forms'][$form_name], "no-access")) continue;
		// Loop through events
		foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
			// Skip if form not designated for this event
			if (!in_array($form_name, $these_forms)) continue;
			if (!isset($selected_forms_events_array[$this_event_id])) continue;
			if (isset($selected_forms_events_array[$this_event_id]) && !in_array($form_name, $selected_forms_events_array[$this_event_id])) continue;
			// If we are viewing a specific arm, then only show events for the current arm
			if ($multiple_arms && is_numeric($_GET['arm']) && !isset($Proj->events[$_GET['arm']]['events'][$this_event_id])) {
				continue;
			}
			// Add to array
			$formsEvents[] = array('form_name'=>$form_name, 'event_id'=>$this_event_id);
			// Set colspan for event/form, depending on group_by
			if (isset($formsEventsColspan[$form_name])) {
				$formsEventsColspan[$form_name]++;
			} else {
				$formsEventsColspan[$form_name] = 1;
			}
		}
		
	}
}

// HEADERS: Add all row HTML into $rows. Add header to table first.
$rows = '';
$hdrs = RCView::th(array('rowspan'=>($longitudinal ? '2' : '1'), 'style'=>'text-align:center;color:#800000;padding:5px 10px;vertical-align:bottom;'), 
			$th_span1 . "<span data-mlm data-mlm-name='{$table_pk}' data-mlm-type='field-label'>".$table_pk_label . "</span>" . $th_span2
		);
// If RTWS is enabled, then display column for it
if ($showRTWS) {
	$hdrs .= RCView::th(array('id'=>'rtws_rsd_hdr', 'rowspan'=>($longitudinal ? '2' : '1'), 'class'=>'wrap darkgreen', 'style'=>'line-height:10px;width:100px;font-size:11px;text-align:center;padding:5px;white-space:normal;vertical-align:bottom;'),
				RCView::div(array('style'=>'font-weight:bold;font-size:12px;margin-bottom:7px;'),
					'<i class="fas fa-database"></i> ' .
                    ($DDP->isEnabledInProjectFhir() ? RCView::tt("ws_292") : RCView::tt("ws_30"))
				) .
				RCView::tt_i("ws_333", array($DDP->getSourceSystemName()))
			);
}
if ($longitudinal) {
	$prev_event_id = $prev_form_name = null;
	foreach ($formsEvents as $attr) {
		if ($dashboard['group_by'] == 'event') {
			// Skip if already did this event
			if ($prev_event_id == $attr['event_id']) continue;
			// Group by event
			$hdrs .= RCView::th(array('class'=>'rsd-left', 'colspan'=>$formsEventsColspan[$attr['event_id']], 'style'=>'border-bottom:1px dashed #aaa;color:#800000;font-size:11px;text-align:center;padding:5px;white-space:normal;vertical-align:bottom;'),
						"<span data-mlm data-mlm-name='{$attr["event_id"]}' data-mlm-type='event-name'>".
						RCView::escape($Proj->eventInfo[$attr['event_id']]['name']).
						"</span>"
					);
			$prev_event_id = $attr['event_id'];
		} else {
			// Skip if already did this event
			if ($prev_form_name == $attr['form_name']) continue;
			// Group by form
			$hdrs .= RCView::th(array('class'=>'rsd-left', 'colspan'=>$formsEventsColspan[$attr['form_name']], 'style'=>'border-bottom:1px dashed #aaa;font-size:11px;text-align:center;padding:5px;white-space:normal;vertical-align:bottom;'),
						"<span data-mlm data-mlm-name='{$attr["form_name"]}' data-mlm-type='form-name'>".
						RCView::escape($Proj->forms[$attr['form_name']]['menu']).
						"</span>"
					);
			$prev_form_name = $attr['form_name'];
		}
	}
	$rows = RCView::tr('', $hdrs);
	$hdrs = "";
}
$prev_form = $prev_event = null;
foreach ($formsEvents as $attr) {
	if ($dashboard['group_by'] == 'event') {
		// Group by event
		$hdrs .= RCView::th(array('class'=>($longitudinal && $prev_event != $attr['event_id'] ? ' rsd-left' : ''), 'style'=>$th_width.($longitudinal ? 'border-top:0;' : '').'font-size:11px;text-align:center;padding:3px;white-space:normal;vertical-align:bottom;'),
					RCView::div(array('style'=>($longitudinal ? 'font-weight:normal;' : '')), 
						$th_span1 . "<span data-mlm data-mlm-name='{$attr["form_name"]}' data-mlm-type='form-name'>".RCView::escape($Proj->forms[$attr['form_name']]['menu']) . "</span>" . $th_span2
					)
				);
	} else {
		// Group by form
		$hdrs .= RCView::th(array('class'=>($longitudinal && $prev_form != $attr['form_name'] ? ' rsd-left' : ''), 'style'=>$th_width.($longitudinal ? 'border-top:0;' : '').'color:#800000;font-size:11px;text-align:center;padding:3px;white-space:normal;vertical-align:bottom;'),
					RCView::div(array('style'=>($longitudinal ? 'font-weight:normal;' : '')), 
						$th_span1 . "<span data-mlm data-mlm-name='{$attr["event_id"]}' data-mlm-type='event-name'>" . RCView::escape($Proj->eventInfo[$attr['event_id']]['name']) . "</span>" . $th_span2
					)
				);		
	}
	$prev_form = $attr['form_name'];
	$prev_event = $attr['event_id'];
}
$rows .= RCView::tr('', $hdrs);
$rows = RCView::thead('', $rows);


// IF NO RECORDS EXIST, then display a single row noting that
if (empty($formStatusValues)) {
	$rows .= RCView::tr([], RCView::td([
			'class'=>'data',
			'colspan'=>count($formsEvents)+($showRTWS ? 1 : 0)+1,
			'style'=>'font-size:12px;padding:10px;color:#555;'
		],
		$errorMsg == '' 
			? ($dashboard['filter_logic'] == '' ? RCView::tt("data_entry_179") : RCView::tt("data_entry_372")) 
			: RCView::div(['class'=>'red'], $errorMsg)
	));
}

$arm = is_numeric($_GET['arm']) ? $_GET['arm'] : getArm();
$arm_id = $Proj->getArmIdFromArmNum($arm);

// ADD ROWS: Get form status values for all records/events/forms and loop through them
function processFormStatusValues(
	$project_id, 
	$displayLocking,
	$displayEsignature,
	$formStatusValues, 
	$arm, 
	$arm_id,
	$extra_record_labels, 
	$lang, 
	$showRTWS, 
	$DDP, 
	$realtime_webservice_offset_days, 
	$realtime_webservice_offset_plusminus, 
	$formsEvents, 
	$selected_forms_events_array, 
	$longitudinal, 
	$dashboard,
	$surveys_enabled,
	&$prev_form,
	&$prev_event
) {
	list($Proj, $project_id) = Project::requireProject($project_id);
	// Get all locked / e-signed records
	$locked_records = $displayLocking 
		? Form::getFormLockedData($project_id, array_keys($formStatusValues))
		: [];
	$esigned_records = $displayEsignature 
		? Form::getFormESignedData($project_id, array_keys($formStatusValues)) 
		: [];
	// Has repeating instances?
	$hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
	// Determine if records also exist as a survey response for some instruments
	$surveyResponses = $surveys_enabled ? Survey::getResponseStatus($project_id, array_keys($formStatusValues)) : [];
	// Determine which records are locked via record-level locking
	$lockingWhole = new Locking();
	$lockingWhole->findLockedWholeRecord($project_id, array_keys($formStatusValues), $arm);
	// Cache all record data for relevant fields being evaluated in the conditions
	FormDisplayLogic::loadRecordData($project_id, array_keys($formStatusValues));
	// Preset icons
	$lockimgStatic = RCView::span(["class" => "rc-rsd-locked-indicator lock"], RCIcon::Locked("fa-xs text-warning"));
	$lockimgMultipleStatic  = RCView::span(["class" => "rc-rsd-locked-indicator lock"], RCIcon::Locked("fa-xs text-warning multiple"));
	$esignimgStatic = RCView::span(["class" => "rc-rsd-esigned-indicator esign"], RCIcon::ESigned("fa-xs text-success"));
	$esignimgMultipleStatic = RCView::span(["class" => "rc-rsd-esigned-indicator esign"], RCIcon::ESigned("fa-xs text-success multiple"));
	// If RTWS is enabled, collect records with cached data into array with record as key and last fetch timestamp as value
	$records_with_cached_data = [];
	if ($showRTWS) {
		$sql = "SELECT r.record, r.item_count FROM redcap_ddp_records r
				WHERE r.project_id = $project_id AND r.record IN (" . prep_implode(array_keys($formStatusValues)) . ")";
		$q = db_query($sql);
		if($q !== false) {
			while ($row = db_fetch_assoc($q)) {
				// Avoid null values because isset() won't work with it as an array value
				if ($row['item_count'] === null) $row['item_count'] = ''; 
				$records_with_cached_data[$row['record']] = $row['item_count'];
			}
		}
	}
	// Initialize loop vars
	$prev_form = $prev_event = null;
	$rowclass = "even";
	$rows = '';
	foreach ($formStatusValues as $this_record=>$rec_attr) {
		// Determine the existing instances for each repeating event
		$repeating_event_instances = [];
		foreach (array_keys($rec_attr) as $this_event_id) {
			if (!$Proj->isRepeatingEvent($this_event_id)) continue;
			$repeating_event_instances[$this_event_id] = [];
			// Add all instances of all forms
			foreach (array_values($rec_attr[$this_event_id]) as $this_instances) {
				$repeating_event_instances[$this_event_id] = array_merge($repeating_event_instances[$this_event_id], array_keys($this_instances));
			}
			// Keep unique and sort numerically
			$repeating_event_instances[$this_event_id] = array_unique($repeating_event_instances[$this_event_id]);
			sort($repeating_event_instances[$this_event_id]);
		}
		// For each record (i.e. row), loop through all forms/events
		$this_row = RCView::td(array("class"=>"rc-rsd-id-cell", 'style'=>'font-size:12px;'),
						RCView::a(array('href'=>APP_PATH_WEBROOT . "DataEntry/record_home.php?pid=$project_id&arm=$arm&id=".htmlspecialchars(removeDDEending($this_record), ENT_QUOTES),
								'style'=>'text-decoration:underline;font-size:13px;'), htmlspecialchars(removeDDEending($this_record), ENT_QUOTES)) .
						// Display custom record label or secondary unique field (if applicable)
						(isset($extra_record_labels[$this_record]) ? '&nbsp;&nbsp;' . $extra_record_labels[$this_record] : '') .
						// Is record locked via record-level locking?
						(isset($lockingWhole->lockedWhole[$this_record]) ? RCView::img(array('src'=>'lock_big.png', 'class'=>'lock align-middle', 'style'=>'width:18px;height:18px;', 'data-rc-lang-attrs' => 'title=form_renderer_37', 'title'=>$lang['form_renderer_37']." ".DateTimeRC::format_ts_from_ymd($lockingWhole->lockedWhole[$this_record][$arm_id]))) : "") // ttfy
					);
		// If RTWS is enabled, then display column for it
		if ($showRTWS) {
			// If record already has cached data, then obtain count of unadjudicated items for this record
			if (isset($records_with_cached_data[$this_record])) {
				// Get number of items to adjudicate and the html to display inside the dialog
				if ($records_with_cached_data[$this_record] != "") {
					$itemsToAdjudicate = $records_with_cached_data[$this_record];
				} else {
					$projectUsers = array_keys(UserRights::getPrivileges($project_id)[$project_id] ?? []);
					$username = defined('USERID') ? USERID : null;
					if(!in_array($username, $projectUsers)) {
						$itemsToAdjudicate = 0;
					}else {
						list ($itemsToAdjudicate, $newItemsTableHtml)
							= $DDP->fetchAndOutputData($this_record, null, array(), $realtime_webservice_offset_days, $realtime_webservice_offset_plusminus,
														false, true, false, false);
					}
				}
			} else {
				// No cached data for this record
				$itemsToAdjudicate = 0;
			}
			// Set display values
			if ($itemsToAdjudicate == 0) {
				$rtws_row_class = "darkgreen";
				$rtws_item_count_style = "color:#999;font-size:10px;";
				$num_items_text = RCView::tt("dataqueries_259");
			} else {
				$rtws_row_class = "statusdashred";
				$rtws_item_count_style = "color:red;font-size:15px;font-weight:bold;";
				$num_items_text = $itemsToAdjudicate;
			}
			// Display row
			$this_row .= RCView::td(array('class'=>$rtws_row_class, 'id'=>'rtws_new_items-'.$this_record, 'style'=>'font-size:12px;padding:0 5px;text-align:center;'),
							'<div style="float:left;width:50px;text-align:center;'.$rtws_item_count_style.'">'.$num_items_text.'</div>
							<div style="float:right;"><a href="javascript:;" onclick="window.REDCap.openAdjudicationModal(\''.js_escape2($this_record).'\');" style="font-size:10px;text-decoration:underline;">'.RCView::tt("dataqueries_92").'</a></div>
							<div style="clear:both:height:0;"></div>'
						);
		}
		// Loop through each column ($attr = [ form_name, event_id ])
		foreach ($formsEvents as $attr) {
			// Info
			$this_form_status = $formStatusValues[$this_record][$attr['event_id']][$attr['form_name']] ?? [];
			$this_form_survey_responses = $surveyResponses[$this_record][$attr['event_id']][$attr['form_name']] ?? [];
			$this_event_repeating = $Proj->isRepeatingEvent($attr['event_id']);
			$this_form_repeating = $Proj->isRepeatingForm($attr['event_id'], $attr['form_name']);
			// Locking and E-Signature info: [ instance => [ ts, user ]]
			$this_form_locked = $locked_records[$this_record][$attr['event_id']][$attr['form_name']] ?? [];
			$this_form_esigned = $esigned_records[$this_record][$attr['event_id']][$attr['form_name']] ?? [];
			// Icon and plus button
			$this_icon_type = "G";
			$this_icon_stacked = false;
			$this_icon_enabled = false;
			$this_show_locked = "none";
			$this_show_esigned = "none";
			$this_link_instance = "1";
			$plus_btn = "";
			#region Determine icons (status, locked, e-signed) and plus button states
			if ($this_event_repeating) {
				$this_repeating_event_instances = $repeating_event_instances[$attr['event_id']] ?? [];
				// This entire event is repeating
				if (count($this_repeating_event_instances) == 0) {
					$this_icon_type = "G";
					$this_repeating_event_instances = [1];
				}
				elseif (count($this_repeating_event_instances) == 1) {
					$this_form_instance = array_key_first($this_form_status) ?? "1";
					$this_link_instance = $this_form_instance;
					$this_icon_type = $this_form_status[$this_form_instance] ?? "";
					$this_icon_type = (isset($this_form_survey_responses[$this_form_instance]) ? "S" : "") . $this_icon_type;
				}
				else {
					// Icon type
					if (count($this_form_status) > 0) {
						$this_icon_type = array_unique(array_values($this_form_status));
						$this_icon_type = count($this_icon_type) == 1 ? $this_icon_type[0] : "M";
						// When there are gray icons mixed in, then show the mixed status
						if ($this_icon_type != "M" && count($this_form_status) != count($this_repeating_event_instances)) $this_icon_type = "M";
						if ($this_icon_type != "M" && count($this_form_survey_responses) == count($this_form_status)) {
							$this_icon_type = "S" . $this_icon_type;
						}
					}
					$this_icon_stacked = true;
				}
				// Check Form Display Logic
				// For repeating events, we have to account for gray icons in the test
				// Note: Even with the new implementation, the limitation remains that all gray 
				// and disabled forms will still be shown on the RSD, even when the FDL setting 
				// to hide such forms is enabled.
				foreach ($this_repeating_event_instances as $this_repeating_event_instance) {
					if (!isset($this_form_status[$this_repeating_event_instance])) {
						// Add gray status represented by empty string
						$this_form_status[$this_repeating_event_instance] = "";
					}
				}
				foreach (array_keys($this_form_status) as $this_form_instance) {
					$this_form_access = FormDisplayLogic::getEventFormsState($project_id, $this_record, $attr['event_id'], $this_form_instance, $formStatusValues)[$this_record][$attr['event_id']] ?? [];
					if ($this_form_access[$attr['form_name']]) {
						$this_icon_enabled = true;
						break;
					}
				}
				// Locked and e-signed status
				if (count($this_form_locked) > 0) {
					$this_show_locked = count($this_form_locked) == count($this_repeating_event_instances) ? "all" : "some";
				}
				if (count($this_form_esigned) > 0) {
					$this_show_esigned = count($this_form_esigned) == count($this_repeating_event_instances) ? "all" : "some";
				}
			}
			else if ($this_form_repeating) {
				// This form is repeating
				$this_icon_stacked = count($this_form_status) > 1;
				// Determine icon
				$this_icon_type = array_unique(array_values($this_form_status));
				switch (count($this_icon_type)) {
					case 0: 
						// Gray
						$this_icon_type = "G"; 
						break; 
					case 1: 
						// Single status
						$this_icon_type = $this_icon_type[0];
						$this_link_instance = array_key_first($this_form_status);
						// Need to check survey responses
						if (count($this_form_survey_responses) == count($this_form_status)) {
							// All are survey responses
							$this_icon_type = "S" . $this_icon_type;
						}
						else if (count($this_form_survey_responses) == 0) {
							// None are survey responses - no change
						}
						else {
							// Mixed survey/non-survey
							// Do all have same numeric status (0 or 2)?
							$unique_survey_statuses = array_unique(array_values($this_form_survey_responses));
							if (count($unique_survey_statuses) == 1 && $unique_survey_statuses[0] == $this_icon_type) {
								// Use survey/form icon depending on lowest instance status
								if (min(array_keys($this_form_survey_responses)) <= min(array_keys($this_form_status))) {
									$this_icon_type = "S" . $this_icon_type;
								}
							}
							else {
								// Mixed survey/non-survey statuses
								$this_icon_type = "M";
							}
						}
						break; 
					default: 
						// Mixed statuses
						$this_icon_type = "M"; 
						$this_icon_stacked = true;
						break;
				}
				// Check Form Display Logic
				$this_icon_enabled = FormDisplayLogic::getEventFormsState($project_id, $this_record, $attr['event_id'], 1, $formStatusValues)[$this_record][$attr['event_id']][$attr['form_name']] ?? true;
				// Locked and e-signed status
				if (count($this_form_locked) > 0) {
					$this_show_locked = count($this_form_locked) == count($this_form_status) ? "all" : "some";
				}
				if (count($this_form_esigned) > 0) {
					$this_show_esigned = count($this_form_esigned) == count($this_form_status) ? "all" : "some";
				}
			}
			else {
				// Non-repeating form on non-repeating event
				$this_icon_type = $this_form_status[1] ?? "G";
				// Survey response?
				$this_icon_type = (isset($this_form_survey_responses[1]) ? "S" : "") . $this_icon_type;
				// Check Form Display Logic
				$this_icon_enabled = FormDisplayLogic::getEventFormsState($project_id, $this_record, $attr['event_id'], 1, $formStatusValues)[$this_record][$attr['event_id']][$attr['form_name']] ?? true;
				// Locked and e-signed status
				if (isset($this_form_locked[1])) $this_show_locked = "all";
				if (isset($this_form_esigned[1])) $this_show_esigned = "all";
			}
			// Create the plus button (only for repeating forms, and only when there is at least one existing instance)
			if ($this_form_repeating && $this_icon_type != "G") {
				$plus_btn_class = $this_icon_enabled ? "" : "rc-form-menu-fdl-disabled";
				$plus_btn_url = APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id&id=".urlencode(removeDDEending($this_record))."&event_id={$attr['event_id']}&page={$attr['form_name']}&new";
				$puls_btn_disabled = ($GLOBALS["draft_preview_enabled"] ?? false) ? "disabled" : "";
				$plus_btn_title = ($GLOBALS["draft_preview_enabled"] ?? false) ? RCView::tt_js("draft_preview_11") : "";
				$plus_btn = "<span class='rc-add-new-instance-btn' title='$plus_btn_title'><button $puls_btn_disabled data-rc-lang-attrs='title=grid_43' title='".js_escape($lang['grid_43'])."' onclick=\"window.location.href='$plus_btn_url';\" class='btn btn-defaultrc rc-form-menu-plus $plus_btn_class'>+</button></span>";
			}
			#endregion

			// Build cell
			$cell = "";
			// Form status icon link
			$stack = $this_icon_stacked ? "_stack" : "";
			$status_icon_attr = ["src" => APP_PATH_IMAGES."circle_gray$stack.png"];
			switch($this_icon_type) {
				case "S2":
					$status_icon_attr = ["src" => APP_PATH_IMAGES."circle_green_tick$stack.png"];
					break;
				case "S1":
				case "S0":
				case "SM":
					$status_icon_attr = ["src" => APP_PATH_IMAGES."circle_orange_tick$stack.png"];
					break;
				case "2":
					$status_icon_attr = ["src" => APP_PATH_IMAGES."circle_green$stack.png"];
					break;
				case "1":
					$status_icon_attr = ["src" => APP_PATH_IMAGES."circle_yellow$stack.png"];
					break;
				case "0":
					$status_icon_attr = ["src" => APP_PATH_IMAGES."circle_red$stack.png"];
					break;
				case "M":
					// This must be a stack
					$status_icon_attr = ["src" => APP_PATH_IMAGES."circle_blue_stack.png"];
					break;
			}
			$link_attr = [
				"class" => "rc-rsd-status-link" . ($this_icon_enabled ? "" : " rc-rsd-fdl-disabled"),
			];
			if ($this_icon_stacked) {
				$link_attr["href"] = "javascript:;";
				$link_attr["onclick"] = "showFormInstanceSelector(this, $project_id, '" . htmlspecialchars(($this_record), ENT_QUOTES) . "', '{$attr["form_name"]}', {$attr["event_id"]}, '".htmlspecialchars((removeDDEending($this_record)), ENT_QUOTES)."');";
			}
			else {
				$link_attr["href"] = APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id&id=".urlencode(removeDDEending($this_record))."&page={$attr['form_name']}&event_id={$attr['event_id']}&instance=$this_link_instance";
			}
			$status_icon_attr["class"] = "rc-form-menu-icon";
			// Wrap icon and link in DIV for setting minimal width
			$wrapper_class = "rc-rsd-status-wrapper";
			if ($this_form_repeating) $wrapper_class .= " rc-form-repeating";
			if ($this_event_repeating) $wrapper_class .= " rc-event-repeating";
			$cell .= RCView::div(["class" => $wrapper_class],
				RCView::a($link_attr, RCView::img($status_icon_attr)) .
				$plus_btn
			);
			// Locked icon
			if ($this_show_locked != "none") {
				$cell .= RCView::span([
					"class" => "rc-rsd-locked-indicator rc-$this_show_locked-locked"
				], RCIcon::Locked("fa-xs"));
			}
			// E-signed icon
			if ($this_show_esigned != "none") {
				$cell .= RCView::span([
					"class" => "rc-rsd-esigned-indicator rc-$this_show_esigned-esigned"
				], RCIcon::ESigned("fa-xs"));
			}
			

			// Determine grouping class (longitudinal only)
			if ($longitudinal) {
				if ($dashboard['group_by'] == 'event') {
					$grouping_class = ($prev_event != $attr['event_id'] ? 'rsd-left' : '');
				} else {
					$grouping_class = ($prev_form != $attr['form_name'] ? 'rsd-left' : '');
				}
			} else {
				$grouping_class = '';
			}
			// Add column to row
			$this_row .= RCView::td(["class" => trim("rc-rsd-cell $grouping_class")], $cell);
			$prev_form = $attr['form_name'];
			$prev_event = $attr['event_id'];
		}
		$rowclass = ($rowclass == "even") ? "odd" : "even";
		$rows .= RCView::tr(array('class'=>$rowclass), $this_row);
	}
	return $rows;
}

// ??? Should this be moved below the call to the cacheManager?
if ($GLOBALS["draft_preview_enabled"] ?? false) {
	Design::updateFormStatusForRecordStatusDashboard($project_id, $formStatusValues);
}

$rows .= $cacheManager->getOrSet('processFormStatusValues', [
	$project_id,
	$displayLocking,
	$displayEsignature,
	$formStatusValues, 
	$arm, 
	$arm_id,
	$extra_record_labels, 
	$lang, 
	$showRTWS, 
	$DDP, 
	$realtime_webservice_offset_days, 
	$realtime_webservice_offset_plusminus, 
	$formsEvents, 
	$selected_forms_events_array, 
	$longitudinal, 
	$dashboard,
	$surveys_enabled,
	$prev_form,
	$prev_event
], $cacheOptions);

// Get all repeating events
$repeatingFormsEvents = $Proj->getRepeatingFormsEvents();
$hasRepeatingForms = $Proj->hasRepeatingForms();
$hasRepeatingEvents = $Proj->hasRepeatingEvents();
$hasRepeatingFormsOrEvents = ($hasRepeatingForms || $hasRepeatingEvents);

// Page header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Add JS var to note that we just stored the page in the cache
if (!$cacheManager->hasCacheMiss()) {
	$cacheTime = CacheFactory::logger(PROJECT_ID)->getLastCacheTimeForProject(PROJECT_ID);
	if ($cacheTime != '') print RCView::script("displayPageCacheInfo('$cacheTime');");
}

// JavaScript
loadJS('RecordDashboard.js');
loadCSS('RecordDashboard.css');

// Page title
renderPageTitle("<i class=\"fas fa-th\"></i> $title");
// Instructions and Legend for colored status icons
print	RCView::table(array('class'=>'d-none d-sm-block', 'style'=>'max-width:950px;table-layout:fixed;'.($instructions == '' ? 'margin-top:-25px;' : '')),
			RCView::tr('',
				RCView::td(array('class'=>'col-8', 'style'=>'vertical-align:bottom;padding:10px 10px 10px 0;'),
					// Instructions
					$instructions					
				) .
				RCView::td(array('id'=>'rsd_legend_td', 'style'=>(is_numeric($rd_id) ? 'vertical-align:top;' : 'vertical-align:bottom;').''.($hasRepeatingFormsOrEvents && $surveys_enabled ? 'width:400px;' : 'width:320px;')),
					// "Show legend" link (if hidden in custom dashboard)
					(!is_numeric($rd_id) ? '' :
						RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;display:block;margin:10px 0 0 50px;', 
							'onclick'=>"$(this).remove();$('#rsd_legend_td').css('vertical-align','bottom');$('#rsd_legend').show();"), $lang['data_entry_353'])
					) .
					// Legend
					RCView::div(array('id'=>'rsd_legend', 'class'=>'chklist', 'style'=>(is_numeric($rd_id) ? 'display:none;' : '').'background-color:#eee;border:1px solid #ccc;'),
						RCView::table(array('id'=>'status-icon-legend'),
							RCView::tr('',
								RCView::td(array('colspan'=>'2', 'style'=>'font-weight:bold;'),
									$lang['data_entry_178']
								)
							) .
							RCView::tr('',
								RCView::td(array('class'=>'nowrap', 'style'=>'padding-right:5px;'),
									RCView::img(array('src'=>'circle_red.png', 'alt'=>$lang['global_92'])) . $lang['global_92']
								) .
								RCView::td(array('class'=>'nowrap', 'style'=>''),
									RCView::img(array('src'=>'circle_gray.png', 'alt'=>$lang['global_92'] . " " . $lang['data_entry_205'])) . $lang['global_92'] . " " . $lang['data_entry_205'] .
									RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$lang['global_58'], 'onclick'=>"simpleDialog('".js_escape($lang['data_entry_232'])."','".js_escape($lang['global_92'] . " " . $lang['data_entry_205'])."');"), '?')
								)
							) .
							RCView::tr('',
								RCView::td(array('class'=>'nowrap', 'style'=>'padding-right:5px;'),
									RCView::img(array('src'=>'circle_yellow.png', 'alt'=>$lang['global_93'])) . $lang['global_93']
								) .
								RCView::td(array('class'=>'nowrap', 'style'=>''),
									($surveys_enabled 
										? RCView::img(array('src'=>'circle_orange_tick.png', 'alt'=>$lang['global_95'])) . $lang['global_95']
										: (!$hasRepeatingFormsOrEvents ? "" :
											(RCView::img(array('src'=>'circle_green_stack.png', 'alt'=>$lang['data_entry_282'])) .
                                            RCView::img(array('src'=>'circle_yellow_stack.png', 'alt'=>$lang['data_entry_282'], 'style'=>'position:relative;left:-6px;')) .
											RCView::img(array('src'=>'circle_red_stack.png', 'alt'=>$lang['data_entry_282'], 'style'=>'position:relative;left:-12px;')) .
											RCView::span(array('style'=>'position:relative;left:-12px;'), $lang['data_entry_282'])))
									)
								)
							) .
							RCView::tr('',
								RCView::td(array('class'=>'nowrap', 'style'=>'padding-right:5px;'),
									RCView::img(array('src'=>'circle_green.png', 'alt'=>$lang['survey_28'])) . $lang['survey_28']
								) .
								RCView::td(array('class'=>'nowrap', 'style'=>''),
									($surveys_enabled 
										? RCView::img(array('src'=>'circle_green_tick.png', 'alt'=>$lang['global_94'])) . $lang['global_94']
										: (!$hasRepeatingFormsOrEvents ? "" : RCView::img(array('src'=>'circle_blue_stack.png', 'alt'=>$lang['data_entry_281'])) . $lang['data_entry_281'])
									)
								)
							) .
							( !($hasRepeatingFormsOrEvents && $surveys_enabled) ? "" :
								RCView::tr('',
									RCView::td(array('class'=>'nowrap', 'style'=>'padding-right:5px;'),
										RCView::img(array('src'=>'circle_blue_stack.png', 'alt'=>$lang['data_entry_281'])) . $lang['data_entry_281']
									) .
									RCView::td(array('class'=>'nowrap', 'style'=>''),
										RCView::img(array('src'=>'circle_green_stack.png', 'alt'=>$lang['data_entry_282'])) .
										RCView::img(array('src'=>'circle_yellow_stack.png', 'alt'=>$lang['data_entry_282'], 'style'=>'position:relative;left:-6px;')) .
										RCView::img(array('src'=>'circle_red_stack.png', 'alt'=>$lang['data_entry_282'], 'style'=>'position:relative;left:-12px;')) .
										RCView::span(array('style'=>'position:relative;left:-12px;'), $lang['data_entry_282'])
									)
								)
							)
						)
					)
				)
			)
		);
print	$dashboardOptionsBox;

// User defines the Record ID
$autoIdBtnText = $auto_inc_set ? $lang['data_entry_46'] : $lang['data_entry_443'];
if ($multiple_arms) {
    $autoIdBtnText .= " " . $lang['data_entry_442'];
}
if (!$auto_inc_set && $user_rights['record_create'] > 0 && !Design::isDraftPreview())
{
	// Check if record ID field should have validation
	$text_val_string = "";
	if ($Proj->metadata[$table_pk]['element_type'] == 'text' && $Proj->metadata[$table_pk]['element_validation_type'] != '')
	{
		// Apply validation function to field
		$text_val_string = "if(redcap_validate(this,'{$Proj->metadata[$table_pk]['element_validation_min']}','{$Proj->metadata[$table_pk]['element_validation_max']}','hard','".convertLegacyValidationType($Proj->metadata[$table_pk]['element_validation_type'])."',1)) ";
	}
	//Text box for next records
	?>
	<div class="clearfix">
		<div class="input-group mb-4">
			<input id="inputString" type="text" placeholder="<?php echo js_escape2($autoIdBtnText) ?>" class="fs13 x-form-text x-form-field" style="width:<?=($multiple_arms ? '250' : '180')?>px;">
			<div class="input-group-append">
				<button class="btn btn-xs btn-rcgreen fs13" onclick="var ob=$('#inputString');ob.trigger('blur');if(ob.val().trim()=='')ob.focus();"><i class="fas fa-plus"></i> <?=$lang['design_248']?></button>
			</div>
		</div>
	</div>
	<script type="text/javascript">
		// Enable validation and redirecting if hit Tab or Enter
		$(function(){
			$('#inputString').keypress(function(e) {
				if (e.which == 13) {
					$('#inputString').trigger('blur');
					return false;
				}
			});
			$('#inputString').blur(function() {
				var refocus = false;
				var idval = trim($('#inputString').val());
				if (idval.length < 1) {
					return;
				}
				if (idval.length > 100) {
					refocus = true;
					alert('<?php echo remBr($lang['data_entry_186']) ?>');
				}
				if (refocus) {
					setTimeout(function(){document.getElementById('inputString').focus();},10);
				} else {
					$('#inputString').val(idval);
					<?php echo isset($text_val_string) ? $text_val_string : ''; ?>
					setTimeout(function(){
						idval = $('#inputString').val();
						idval = idval.replace(/&quot;/g,''); // HTML char code of double quote
						var validRecordName = recordNameValid(idval);
						if (validRecordName !== true) {
							$('#inputString').val('');
							alert(validRecordName);
							$('#inputString').focus();
							return false;
						}
						// Redirect, but NOT if the validation pop-up is being displayed (for range check errors)
						if (!$('.simpleDialog.ui-dialog-content:visible').length)
							window.location.href = app_path_webroot+'DataEntry/record_home.php?pid='+pid+'&arm=<?php echo getArm() ?>&id=' + idval;
					},200);
				}
			});
		});
	</script>
	<?php
}
// Auto-number button(s) - if option is enabled
elseif ($auto_inc_set && $user_rights['record_create'] > 0 && !Design::isDraftPreview())
{
	?>
	<div class="mb-3">
		<?php if ($Proj->reachedMaxRecordCount()) { ?>
			<?php print($Proj->outputMaxRecordCountErrorMsg()); ?>
		<?php } else { ?>
            <!-- New record button -->
            <button class="btn btn-xs btn-rcgreen fs13" onclick="window.location.href=app_path_webroot+'DataEntry/record_home.php?pid='+pid+'&id=<?=DataEntry::getAutoId()?>&auto=1&arm=<?=getArm()?>';">
                <i class="fa-solid fa-plus"></i> <?=$autoIdBtnText?>
            </button>
		<?php } ?>
	</div>
	<?php
}
if (Design::isDraftPreview())
{
	?>
	<div class="mb-3 yellow" style="width:fit-content;">
		<!-- DRAFT PREVIEW notice -->
		<?=RCIcon::ErrorNotificationTriangle("text-danger me-1")?>
		<?=RCView::tt("draft_preview_10")?>
	</div>
	<?php
}
if($showRTWS &&  AutoAdjudicator::isEnabledAndAllowed($project_id)): ?>

	<div class="my-2">
		<div id="cdp-auto-adjudication-container"></div>
	</div>
	<style>
		@import url('<?=APP_PATH_JS?>vue/components/dist/style.css');
	</style>

	<script type="module">
		import { CdpAutoAdjudicate } from '<?= getJSpath('vue/components/dist/lib.es.js') ?>'

		const { eventBus } = CdpAutoAdjudicate('#cdp-auto-adjudication-container')

		eventBus.addEventListener('summary-closed', (event) => {
			// console.log('Received event:', event.detail)
			// reload page once the summary page is closed
			location.reload()
		})
	</script>
<?php endif;
// Options to view locking and/or esignature status
print 	(!($displayLocking || $displayEsignature) ? '' :
			RCView::div(array('style'=>'margin-bottom:10px;color:#888;'),
				RCView::tt("data_entry_225", "span", array('style'=>'font-weight:bold;margin-right:10px;color:#000;')) .
				// Instrument status only
				RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_selected', 'onclick'=>"changeLinkStatus(this);$('.rc-rsd-esigned-indicator, .rc-rsd-locked-indicator').hide();$('.rc-rsd-status-link, .rc-add-new-instance-btn').show();"),
					RCView::tt("data_entry_226")) .
				// Lock only
				(!$displayLocking ? '' :
					RCView::SP . " | " . RCView::SP .
					RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.rc-rsd-status-link, .rc-add-new-instance-btn, .rc-rsd-esigned-indicator').hide();$('.rc-rsd-locked-indicator').show();"),
						RCView::tt("data_entry_227"))
					) .
				// Esign only
				(!$displayEsignature ? '' :
					RCView::SP . " | " . RCView::SP .
					RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.rc-rsd-status-link, .rc-add-new-instance-btn, .rc-rsd-locked-indicator').hide();$('.rc-rsd-esigned-indicator').show();"),
						RCView::tt("data_entry_228"))
				) .
				// Esign + Locking
				(!($displayLocking && $displayEsignature) ? '' :
					RCView::SP . " | " . RCView::SP .
					RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.rc-rsd-status-link, .rc-add-new-instance-btn').hide();$('.rc-rsd-locked-indicator, .rc-rsd-esigned-indicator').show();"),
						RCView::tt("data_entry_230"))
				) .
				// All types
				RCView::SP . " | " . RCView::SP .
				RCView::a(array('href'=>'javascript:;', 'class'=>'statuslink_unselected', 'onclick'=>"changeLinkStatus(this);$('.rc-rsd-status-link, .lock, .rc-rsd-esigned-indicator, .rc-rsd-locked-indicator, .rc-rsd-esigned-indicator, .rc-add-new-instance-btn').show();"),
					RCView::tt("data_entry_229"))
			)
		);

// Display Arm number tab
if ($showArmTabs) 
{
	print '<div id="sub-nav" class="clearfix" style="margin-top:5px;margin-bottom:10px;max-width:750px;"><ul>';
	//Loop through each ARM and display as a tab
	foreach ($Proj->events as $this_arm=>$attr) {
		//Render tab
		print '<li'.($this_arm == $_GET['arm'] ? ' class="active"' : '')
			. '><a style="font-size:12px;color:#393733;padding:5px 5px 5px 11px;" href="javascript:;" onclick="window.location.href=updateParameterInURL(window.location.href,\'arm\', \''.$this_arm.'\');"'
			. '>'.RCView::tt_i("grid_57", array($this_arm))
			. RCView::span(array('style'=>'margin-left:6px;font-weight:normal;color:#800000;'), "<span data-mlm data-mlm-name='{$this_arm}' data-mlm-type='arm-name'>".RCView::escape(strip_tags($attr['name']))).'</span></a></li>';
	}
	print  '</ul></div>';
}

print "<table id='record_status_table' class='dataTable cell-border' style='clear:both;'>$rows</table>";
if ($cache_disabled) {
	print RCView::script("console.warn('Note: The REDCap cache for this page is disabled!');");
}

// If RTWS is enabled, then display column for it
if ($showRTWS) {
	$DDP->initializeAdjudicationModal();
}

// MLM
$context = Context::Builder()
	->project_id($project_id)
	->arm_num($arm)
	->dashboard_id($rd_id)
	->survey_page($_GET["pagenum"])
	->survey_pages($num_pages)
	->group_id($_GET["dag"]??null)
	->Build();
MultiLanguage::translateRecordStatusDashboard($context);

// Page footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';