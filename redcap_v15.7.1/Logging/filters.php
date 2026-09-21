<?php

// Obtain array of all Data Quality rules (in case need to reference them by name in logging display)
$dq = new DataQuality();
$dq_rules = $dq->getRules();

// Obtain names of Events (for Longitudinal projects) and put in array
$event_ids = array();
foreach ($Proj->eventInfo as $this_event_id=>$attr) {
    $event_ids[$this_event_id] = $attr['name_ext'];
}

## FILTER BY RECORD
// If a non-record-type event is selected, then blank this drop-down because it wouldn't make sense to use it
$disableRecordFilter = '';
if (isset($_GET['logtype']) && strpos($_GET['logtype'], 'record') === false && $_GET['logtype'] != '') {
    $_GET['record'] = '';
    $_GET['dag'] = '';
    $disableRecordFilter = 'disabled';
}
if (isset($user_rights['group_id']) && $user_rights['group_id'] == '' && isset($_GET['dag']) && isset($dags[$_GET['dag']])) {
    $_GET['record'] = '';
}

// Set filter to specific user's logging actions
if (isset($_GET['usr']) && $_GET['usr'] == 'SYSTEM') {
    $filter_user = "AND (user = 'SYSTEM' or user like 'SYSTEM\\n%')";
} else {
    $filter_user = (isset($_GET['usr']) && $_GET['usr'] != '') ? "AND (user = '".db_escape($_GET['usr'])."' or user like '%\\n".db_escape($_GET['usr'])."')" : "";
}

// Set filter for logged event type
$logtype = $_GET['logtype'] ?? '';
$filter_logtype = Logging::setEventFilterSql($logtype);

// Sections results into multiple pages of results by limiting to 100 per page. $begin_limit is record to begin with.
$begin_limit = (isset($_GET['limit']) && isinteger($_GET['limit'])) ? $_GET['limit'] : 0;

// DAG filtering (for record and non-record events)
$dags = $Proj->getGroups();
if (isset($user_rights['group_id']) && $user_rights['group_id'] == '' && isset($_GET['dag']) && isset($dags[$_GET['dag']])) {
    $dagId = $_GET['dag'];
} elseif (isset($user_rights['group_id']) && $user_rights['group_id'] != '') {
    $dagId = $user_rights['group_id'];
} else {
    $dagId = null;
}
// If user is in DAG, limit viewing of general events to only users in their own DAG (but not if we're limiting to record-specific events for a specific DAG)
$dag_users_array = DataAccessGroups::getDagUsers($project_id, $dagId);
// Set filter for records in a DAG
$filter_record = "";
$filer_data_events_sql = "event in ('MANAGE','ESIGNATURE','LOCK_RECORD','UPDATE','INSERT','DELETE','DOC_UPLOAD','DOC_DELETE','OTHER') 
                          and object_type != 'redcap_user_rights' and object_type != 'redcap_external_links' and !(object_type = 'redcap_alerts' and description != 'Send alert')";
if (isset($_GET['record']) && $_GET['record'] != '') {
    // Single record filtering
    // Verify that record belongs to DAG, if applicable
    if ($dagId != null && $dagId != Records::getRecordGroupId(PROJECT_ID, $_GET['record'])) {
        $_GET['record'] = '';
    }
    $filter_record = "and $filer_data_events_sql and pk = '".db_escape($_GET['record'])."'";
} elseif ($dagId !== null) {
    // Filter records by user's DAG or by non-DAG user using DAG filter
    $filterByRecords = (isset($_GET['record']) && $_GET['record'] != '') ? [$_GET['record']] : [];
    $dagRecords = Records::getRecordList($project_id, $dagId, false, false, null, null, 0, $filterByRecords);
    if (empty($dagRecords) && $user_rights['group_id'] == '') {
        // If non-DAG user is filtering by records in DAG, but the DAG has no records, return nothing
        $filter_record = "AND 1=2";
    } else {
        $dagRecordsSql = empty($dagRecords) ? [''] : $dagRecords;
        $filter_record_sub_sql = "$filer_data_events_sql and pk in (" . prep_implode($dagRecordsSql) . ")";
        $filter_record_sub_sql2 = "$filer_data_events_sql and pk not in (" . prep_implode($dagRecordsSql) . ")";
        if (strpos($logtype, 'record') === false) {
            // If user is in a DAG and this is not a record-limiting query, we need to use $filter_record above to get record events in the DAG and ALSO use $dag_users above to get non-record events
            $dag_users_sql = empty($dag_users_array) ? "" : "(user in (" . prep_implode($dag_users_array) . ") and !($filter_record_sub_sql2)) OR";
            $filter_record = "AND ($dag_users_sql ($filter_record_sub_sql))";
        } else {
            // Filter by records in a DAG
            $filter_record = "AND ($filter_record_sub_sql)";
        }
    }
}
// Reset DAG user limiting because we're limiting by records, not DAG-user record-specific events
$dag_users = (empty($dag_users_array) || $filter_record != '') ? "" : "AND user in (" . prep_implode($dag_users_array) . ")";


# FILTER BY BEGIN AND END TIME
// Preset values for time range buttons
$oneDayAgo = DateTimeRC::format_user_datetime(date("Y-m-d H:i", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-1,date("Y"))), 'Y-M-D_24', null, true);
$oneWeekAgo = DateTimeRC::format_user_datetime(date("Y-m-d H:i", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-7,date("Y"))), 'Y-M-D_24', null, true);
$oneMonthAgo = DateTimeRC::format_user_datetime(date("Y-m-d H:i", mktime(date("H"),date("i"),date("s"),date("m")-1,date("d"),date("Y"))), 'Y-M-D_24', null, true);
$oneYearAgo = DateTimeRC::format_user_datetime(date("Y-m-d H:i", mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y")-1)), 'Y-M-D_24', null, true);
$noFiltersSet = (!isset($_GET['beginTime']) && !isset($_GET['download_all']));
$noteDisplayingPastWeekDefault = '';
// If loading the logging page initially, set begin time filter as "one week ago" by default
if ($noFiltersSet) {
    $_GET['beginTime'] = $oneWeekAgo;
    $noteDisplayingPastWeekDefault = RCView::tr(array(),
        RCView::td(array('colspan'=>2, 'style'=>'color:#D00000;padding:15px 10px 5px;'),
            '<i class="fas fa-info-circle"></i> '.$lang['reporting_64']
        )
    );
}
// If begin time is blank, then set it to project creation time automatically for better query performance
$noLimitActive = "";
$creation_time = $Proj->project['creation_time'];
if (isset($_GET['beginTime']) && $_GET['beginTime'] == "") {
    if (defined("API")) {
        $_GET['beginTime'] = $creation_time;
    } else {
        $_GET['beginTime'] = DateTimeRC::format_user_datetime($creation_time, 'Y-M-D_24', null, true);
    }
    $noLimitActive = "active";
}
// Set UI button active status
$oneDayAgoActive = (isset($_GET['beginTime']) && substr($_GET['beginTime'], 0, -2) == substr($oneDayAgo, 0, -2) && (!isset($_GET['endTime']) || $_GET['endTime'] == "")) ? "active" : "";
$oneWeekAgoActive = (isset($_GET['beginTime']) && substr($_GET['beginTime'], 0, -2) == substr($oneWeekAgo, 0, -2) && (!isset($_GET['endTime']) || $_GET['endTime'] == "")) ? "active" : "";
$oneMonthAgoActive = (isset($_GET['beginTime']) && substr($_GET['beginTime'], 0, -2) == substr($oneMonthAgo, 0, -2) && (!isset($_GET['endTime']) || $_GET['endTime'] == "")) ? "active" : "";
$oneYearAgoActive = (isset($_GET['beginTime']) && substr($_GET['beginTime'], 0, -2) == substr($oneYearAgo, 0, -2) && (!isset($_GET['endTime']) || $_GET['endTime'] == "")) ? "active" : "";
$customRangeActive = ($oneDayAgoActive.$oneWeekAgoActive.$oneMonthAgoActive.$oneYearAgoActive.$noLimitActive == "") ? "active" : "";
// Prep begin and end times
$_GET['beginTime'] = substr($_GET['beginTime'], 0, 16);
$_GET['endTime'] = isset($_GET['endTime']) ? substr($_GET['endTime'], 0, 16) : "";
$beginTime_userPref = (isset($_GET['beginTime']) && $_GET['beginTime'] != "") ? str_replace(array("`","="), array("",""), strip_tags(label_decode(urldecode($_GET['beginTime'])))) : '';
$endTime_userPref   = (isset($_GET['endTime']) && $_GET['endTime'] != "") ? str_replace(array("`","="), array("",""), strip_tags(label_decode(urldecode($_GET['endTime'])))) : '';
// Convert to Y-M-D timestamps for query
if (!defined("API")) {
    $beginTime_YMDts = DateTimeRC::format_ts_to_ymd($beginTime_userPref);
    $endTime_YMDts = DateTimeRC::format_ts_to_ymd($endTime_userPref);
} else {
    $beginTime_YMDts = $beginTime_userPref;
    $endTime_YMDts = $endTime_userPref;
}
if ($beginTime_YMDts != '' && strlen($beginTime_YMDts) <= 16) $beginTime_YMDts .= ":00";
if ($endTime_YMDts != '' && strlen($endTime_YMDts) <= 16) $endTime_YMDts .= ":00";
$beginTime_YMDint = preg_replace('/[^\d]/', '', $beginTime_YMDts);
$endTime_YMDint = preg_replace('/[^\d]/', '', $endTime_YMDts);
// Reset the time status to blank for UI if set to "no limit"
if ($noLimitActive != "") {
    $beginTime_YMDint = preg_replace('/[^\d]/', '', $creation_time);
    $beginTime_userPref = "";
}

## Build the SQL query
// Page view logging only
if (isset($_GET['logtype']) && $_GET['logtype'] == 'page_view') {
    if ($filter_user == '' && $filter_record == '' && $dag_users == '') {
        $logging_sql = "SELECT ts*1 as ts, user, '0' as legacy, full_url, event, page, event_id, record, form_name
					   FROM redcap_log_view WHERE project_id = $project_id $filter_logtype ";
    } else {
        $logging_sql = "SELECT ts*1 as ts, user, '0' as legacy, full_url, event, page, event_id, record, form_name
					   FROM redcap_log_view WHERE project_id = $project_id $filter_logtype $filter_user $dag_users ";
    }
    if ($beginTime_YMDts != "") $logging_sql .= " AND ts >= '".db_escape($beginTime_YMDts)."' ";
    if ($endTime_YMDts != "") $logging_sql .= " AND ts <= '".db_escape($endTime_YMDts)."' ";
// Regular logging view
} else {
    if ($filter_logtype == '' && $filter_user == '' && $filter_record == '' && $dag_users == '') {
        $logging_sql = "SELECT * FROM ".Logging::getLogEventTable($project_id)." WHERE project_id = $project_id ";
    } else {
        $logging_sql = "SELECT * FROM ".Logging::getLogEventTable($project_id)."
					   WHERE project_id = $project_id $filter_logtype $filter_user $filter_record $dag_users ";
    }
    if ($beginTime_YMDint != "") $logging_sql .= " AND ts >= '".db_escape($beginTime_YMDint)."' ";
    if ($endTime_YMDint != "") $logging_sql .= " AND ts <= '".db_escape($endTime_YMDint)."' ";
    $logging_sql .= " ORDER BY log_event_id DESC";
}
if (!isset($_GET['download_all']) && !isset($_GET['filters_download_all'])) {
    $logging_sql .= " LIMIT $begin_limit,100";
}