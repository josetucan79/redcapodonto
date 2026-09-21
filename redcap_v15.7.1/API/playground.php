<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (!$api_enabled) System::redirectHome();
$defaults = array(
	'api_call'           => 'exp_proj',
	'api_fmt'            => 'json',
	'api_return'         => 'json',
	'api_type'           => 'flat',
	'api_overwrite'      => 'normal',
	'api_forceAutoNumber'=> 'false',
	'api_return_content' => 'count',
	'api_name_label'     => 'raw',
	'api_header_label'   => 'raw',
	'api_checkbox_label' => 'false',
	'api_survey_field'   => 'false',
	'api_dag'            => 'false',
	'api_return_content' => 'count',
	'api_all_records'    => 'false',
	'code_tab'           => 'php',
	'api_returnMetadataOnly' => 'false',
	'api_exportFiles' => 'false',
	'api_return_alt'  => 'false'
);

foreach($defaults as $k => $v)
{
	$_SESSION[$k] = isset($_SESSION[$k]) ? $_SESSION[$k] : $v;
}

// prevent dangerous APIs when switching projects
if($Proj->project['status'] > 0 && in_array($_SESSION['api_call'], APIPlayground::dangerousAPIs()))
{
	$_SESSION['api_call'] = $defaults['api_call'];
}

unset($_SESSION['api_exp_file_path']);

$gets = array(
	'api_fmt',
	'api_call',
	'api_return',
	'api_event',
	'api_inst',
	'api_record',
	'api_field_name',
	'api_type',
	'api_name_label',
	'api_header_label',
	'api_checkbox_label',
	'api_survey_field',
	'api_dag',
	'api_report_id',
	'api_overwrite',
	'api_forceAutoNumber',
	'api_date_format',
	'api_return_content',
	'api_all_records',
	'code_tab',
	'api_filter_logic',
	'api_date_range_begin',
	'api_date_range_end',
	'api_returnMetadataOnly',
	'api_exportFiles',
	'logtype',
	'usr',
	'record',
	'dag',
	'beginTime',
	'endTime',
	'api_csvdelimiter',
    'api_group_id',
    'arm_num',
    'api_new_record',
	'api_doc_id',
	'api_folder_id',
    'api_folder_name',
    'api_rid',
    'api_return_alt'
);

foreach($gets as $k)
{
	if(isset($_GET[$k]))
	{
		$_SESSION[$k] = rawurldecode(urldecode($_GET[$k]));
		redirect(APP_PATH_WEBROOT . "API/playground.php?pid=$project_id");
	}
}

$jq_gets = array(
	'arm_nums',
	'api_events',
	'api_insts',
	'api_records',
	'api_field_names',
	'api_csvdelimiter',
    'group_id',
    'api_users',
    'api_user_roles'
);

foreach($jq_gets as $k)
{
	if(isset($_GET[$k]))
	{
		// handle jquery multi-select data
		if($_GET[$k] == 'null' || $_GET[$k] == '')
		{
			$a = array();
		}
		elseif(strpos( $_GET[$k], ',') !== false)
		{
			$a = explode(',', $_GET[$k]);
		}
		else
		{
			$a = array($_GET[$k]);
		}
		if (empty($a)) {
			unset($_SESSION[$k]);
		} else {
			$_SESSION[$k] = $a;
		}
		header("Location: playground.php?pid=$project_id");
		exit;
	}
}

if(isset($_POST['api_data']))
{
	if($_SESSION['api_fmt'] == 'csv')
	{
		$_SESSION['api_data'] = str_replace(array("\n", "\r", "\r\n", "\t"), array('&#10;', '&#13;', '&#13;&#10;', '&#09;'), $_POST['api_data']);
	}
	else
	{
		$_SESSION['api_data'] = str_replace(array("\n", "\r", "\r\n", "\t", '  '), ' ', $_POST['api_data']);
	}

	header("Location: playground.php?pid=$project_id");
	exit;
}

$token = UserRights::getAPIToken($userid, $project_id);
$pg = new APIPlayground($token, $lang);

if(isset($_FILES['api_file']))
{
	unset($_SESSION['api_file']);

	if($_FILES['api_file']['error'] === UPLOAD_ERR_OK)
	{
		$path = sys_get_temp_dir() . DS . str_replace(array("\\","/"), array("",""), basename($_FILES['api_file']['name']));
		if(!move_uploaded_file($_FILES['api_file']['tmp_name'], $path))
		{
			echo 'Cannot move uploaded file';
			exit;
		}

		$_SESSION['api_file_path'] = $path;
		$_SESSION['api_file_mime'] = $_FILES['api_file']['type'];
		$_SESSION['api_file_name'] = $_FILES['api_file']['name'];

		header("Location: playground.php?pid=$project_id");
		exit;
	}

	echo $pg->getUploadErrorMessages($_FILES['api_file']['error']);
	exit;
}

include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

renderPageTitle('<i class="fas fa-laptop-code"></i> ' . $lang['setup_143']);
if (Design::isDraftPreview()) {
	print "<div class='yellow draft-preview-banner mt-2 mb-2'>
		<i class='fa-solid fa-triangle-exclamation text-danger draft-preview-icon me-2'></i>" .
		RCView::lang_i("draft_preview_16", [
			"<a style='color:inherit !important;' href='".APP_PATH_WEBROOT."Design/online_designer.php?pid=".PROJECT_ID."'>",
			"</a>"
		], false) . "
	</div>";
}

$instr = RCView::p(array('style' => 'margin-top:20px;max-width:800px;'),
				$lang['system_config_528'] . ' ' .
				RCView::a(array('href' => APP_PATH_WEBROOT_PARENT . 'api/help/', 'style' => 'text-decoration:underline;', 'target' => '_blank'),
								$lang['edit_project_142']) .
				$lang['period'] . ' ');
echo $instr;

if (empty($token))
{
	// need token
	$requestAuto = ($api_token_request_type == 'auto_approve_all' || ($api_token_request_type == 'auto_approve_selected' && User::getUserInfo($userid)['api_token_auto_request'] == '1')) ? 1 : 0;
	$req = RCView::div(array('class' => 'chklisthdr'), $lang['api_139'] . ' "' . RCView::escape($app_title) . '"');
	$req .= RCView::div(array('style' => 'margin:5px 0 0;'), ($requestAuto ? $lang['edit_project_183'] : $lang['edit_project_88']));
	$todo_type = 'token access';
	if(ToDoList::checkIfRequestExist($project_id, UI_ID, $todo_type) > 0){
		$reqAPIBtn = RCView::button(array('class' => 'api-req-pending'), $lang['api_03']);
		$reqP = RCView::p(array('class' => 'api-req-pending-text'), $lang['edit_project_179']);
	}else{
		$reqAPIBtn = RCView::button(array('class' => 'jqbuttonmed', 'onclick' => "requestToken($requestAuto,0);"), ($requestAuto ? $lang['api_138'] : ($super_user ? $lang['api_08'] : $lang['api_03'])));
		$reqP = '';
	}
	$req .= RCView::div(array('class' => 'chklistbtn'), $reqAPIBtn.$reqP);
//	if ($super_user && !defined("AUTOMATE_ALL")) {
//		$req .= RCView::br();
//		$approveLink = APP_PATH_WEBROOT . 'ControlCenter/user_api_tokens.php?action=createToken&api_username=' . $userid .
//			'&api_pid=' . $project_id . '&goto_proj=1';
//		$req .= RCView::button(array('onclick' =>"window.location.href='$approveLink';", 'class' => 'jqbuttonmed'), RCView::escape($lang['api_08'])) .
//			RCView::SP . RCView::span(array('style' => 'color: red;'), $lang['edit_project_77']);
//	}
	$req = RCView::div(array('id' => 'apiReqBoxId', 'class' => 'redcapAppCtrl'), $req);
	echo RCView::div(array(), $req);
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;
}

// api call opts
$api_prod_warn = '';
if($Proj->project['status'] > 0)
{
	$api_prod_warn = RCView::span(array('style'=>'font-size:10px;color:green;font-weight:bold;'), RCView::br() . $lang['api_72']);
}

$api_calls = $pg->getAPICalls($Proj);
foreach($api_calls as $group => $calls)
{
	foreach($calls as $k => $call)
	{
		// only used in docs
		if($k == 'imp_proj')
		{
			unset($api_calls[$group][$k]);
		}
	}
}

$api_call = isset( $_SESSION['api_call'] ) ? $_SESSION['api_call'] : '';
$api_call_opts = RCView::select(array('id'=>'api_call'), $api_calls, $api_call, 300);
$td_call_label = RCView::td(array('style'=>'text-align:right;font-weight:bold;padding-top:2px;', 'valign'=>'top'), $lang['api_12']);
$td_call_select = RCView::td(array('style'=>'', 'valign'=>'top'), $api_call_opts . $api_prod_warn);
$tr_call = RCView::tr(array(), $td_call_label . $td_call_select);

// data format opts
$afs = array();
foreach( $pg->getAPIFormats() as $k)
{
	$afs[$k] = strtoupper($k);
}
$api_fmt_opts = RCView::select(array('id'=>'api_fmt'), $afs, $_SESSION['api_fmt']);
$td_fmt_label = RCView::td(array('style'=>'text-align:right'), $lang['api_13']);
$td_fmt_select = RCView::td(array(), $api_fmt_opts);
$tr_fmt = RCView::tr(array(), $td_fmt_label . $td_fmt_select);

$api_csvdelimiter_opts = RCView::select(array('id'=>'api_csvdelimiter'), DataExport::getCsvDelimiters(), (isset($_SESSION['api_csvdelimiter']) ? $_SESSION['api_csvdelimiter'] : ""));
$td_csvdelimiter_label = RCView::td(array('style'=>'text-align:right'), $lang['data_export_tool_233'].$lang['colon']);
$td_csvdelimiter_select = RCView::td(array(), $api_csvdelimiter_opts);
$tr_csvdelimiter = RCView::tr(array(), $td_csvdelimiter_label . $td_csvdelimiter_select);

// multiple arms
$arms = $pg->getArms($project_id);
$arms_size = count($arms) <= 5 ? count($arms) : 5;
$select_opts = array('id'=>'arm_nums', 'multiple'=>'multiple', 'size'=>$arms_size);
if(!$longitudinal)
{
	$select_opts['disabled'] = 'disabled';
}
$arm_opts = RCView::select($select_opts, $arms, (isset($_SESSION['arm_nums']) ? $_SESSION['arm_nums'] : ""));
$td_arms_label = RCView::td(array('style'=>'text-align:right'), $lang['api_22']);
$td_arms_select = RCView::td(array(), $arm_opts);
$tr_arms = RCView::tr(array(), $td_arms_label . $td_arms_select);

// Single Arm
$arm_opt = array('id'=>'arm_num');
if(!$longitudinal)
{
    $arm_opt['disabled'] = 'disabled';
}
$arms = array('' => '--') + $arms;
$arm_opts = RCView::select($arm_opt, $arms, (isset($_SESSION['arm_num']) ? $_SESSION['arm_num'] : ""));
$td_arms_label = RCView::td(array('style'=>'text-align:right'), $lang['api_22']);
$td_arms_select = RCView::td(array(), $arm_opts);
$tr_arm = RCView::tr(array(), $td_arms_label . $td_arms_select);

// error return
$api_return_opts = RCView::select(array('id'=>'api_return'), $afs, $_SESSION['api_return']);
$td_return_fmt_label = RCView::td(array('style'=>'text-align:right'), $lang['api_23']);
$td_return_fmt_select = RCView::td(array(), $api_return_opts);
$tr_return_fmt = RCView::tr(array(), $td_return_fmt_label . $td_return_fmt_select);

// single instruments
$insts = array_merge(array('' => '--'), $pg->getInstruments($project_id));

$inst_opt = RCView::select(array('id'=>'api_inst'), $insts, (isset($_SESSION['api_inst']) ? $_SESSION['api_inst'] : ""));
$td_inst_label = RCView::td(array('style'=>'text-align:right'), $lang['api_24']);
$td_inst_select = RCView::td(array(), $inst_opt);
$tr_inst = RCView::tr(array(), $td_inst_label . $td_inst_select);

// multiple instruments
$insts = $pg->getInstruments($project_id);

$insts_size = count($insts) <= 5 ? count($insts) : 5;
$inst_opts = RCView::select(array('id'=>'api_insts', 'multiple'=>'multiple', 'size'=>$insts_size, 'style'=>'padding: 1px 3px;'), $insts, (isset($_SESSION['api_insts']) ? $_SESSION['api_insts'] : ""));
$td_insts_label = RCView::td(array('style'=>'text-align:right'), $lang['api_29']);
$td_insts_select = RCView::td(array(), $inst_opts);
$tr_insts = RCView::tr(array(), $td_insts_label . $td_insts_select);

$event_names = $Proj->getUniqueEventNames();

// single event
$events = array('' => '--');
foreach($event_names as $e)
{
	$events[$e] = $e;
}
$select_opts = array('id'=>'api_event');
if(!$longitudinal)
{
	$select_opts['disabled'] = 'disabled';
}
$event_opts = RCView::select($select_opts, $events, (isset($_SESSION['api_event']) ? $_SESSION['api_event'] : ""));
$td_event_label = RCView::td(array('style'=>'text-align:right'), $lang['api_25']);
$td_event_select = RCView::td(array(), $event_opts);
$tr_event = RCView::tr(array(), $td_event_label . $td_event_select);

// multiple events
$events = array();
foreach($event_names as $e)
{
	$events[$e] = $e;
}
$events_size = count($events) <= 5 ? count($events) : 5;
$select_opts = array('id'=>'api_events', 'multiple'=>'multiple', 'size'=>$events_size, 'style'=>'padding: 1px 3px;');
if(!$longitudinal)
{
	$select_opts['disabled'] = 'disabled';
}
$event_opts = RCView::select($select_opts, $events, (isset($_SESSION['api_events']) ? $_SESSION['api_events'] : ""));
$td_events_label = RCView::td(array('style'=>'text-align:right'), $lang['api_32']);
$td_events_select = RCView::td(array(), $event_opts);
$tr_events = RCView::tr(array(), $td_events_label . $td_events_select);

$record_list = Records::getRecordList($project_id, $user_rights['group_id'], true, false, null, 5000);

// single folder_id - Query files for top-level folder
$folder_ids = array('' => $lang['docs_1131']);
$dagsql = ($user_rights['group_id'] == "") ? "" : "and (dag_id is null or dag_id = ".$user_rights['group_id'].")";
$rolesql = ($user_rights['role_id'] == "") ? "" : "and (role_id is null or role_id = ".$user_rights['role_id'].")";
$sql = "select folder_id, name from redcap_docs_folders 
		where project_id = $project_id $dagsql $rolesql and deleted = 0
		order by parent_folder_id, abs(name), name";
$q = db_query($sql);
while ($row = db_fetch_assoc($q)) {
	$folder_ids[$row['folder_id']] = $row['folder_id']." - ".strip_tags(label_decode($row['name']));
}
$file_repo_opts = RCView::select(array('id'=>'api_folder_id'), $folder_ids, (isset($_SESSION['api_folder_id']) ? $_SESSION['api_folder_id'] : ""));
$tr_folder_id_label = RCView::td(array('style'=>'text-align:right'), ($_SESSION['api_call'] == 'create_folder_file_repo' ? $lang['api_210'] : $lang['api_205']));
$tr_folder_id_select = RCView::td(array(), $file_repo_opts);
$tr_folder_id = RCView::tr(array(), $tr_folder_id_label . $tr_folder_id_select);

// new file repo folder name
$file_repo_opts = RCView::text(array('id'=>'api_folder_name', 'value'=>($_SESSION['api_folder_name']??"")));
$tr_folder_id_label = RCView::td(array('style'=>'text-align:right'), $lang['api_211']);
$tr_folder_id_select = RCView::td(array(), $file_repo_opts);
$tr_folder_name = RCView::tr(array(), $tr_folder_id_label . $tr_folder_id_select);

// single doc_id - Query files for top-level folder
$doc_ids = array('' => '--');
$sql = "select e.doc_id, d.docs_name
		from redcap_docs_to_edocs de, redcap_edocs_metadata e, redcap_docs d
		left join redcap_docs_folders_files ff on ff.docs_id = d.docs_id
		left join redcap_docs_folders f on ff.folder_id = f.folder_id
		where d.project_id = $project_id and d.export_file = 0
		and de.docs_id = d.docs_id and de.doc_id = e.doc_id and e.date_deleted_server is null
		and e.delete_date is null and ff.folder_id is null
		order by d.docs_id";
$q = db_query($sql);
while ($row = db_fetch_assoc($q)) {
	$doc_ids[$row['doc_id']] = $row['doc_id']." - ".strip_tags(label_decode($row['docs_name']));
}
$file_repo_opts = RCView::select(array('id'=>'api_doc_id'), $doc_ids, (isset($_SESSION['api_doc_id']) ? $_SESSION['api_doc_id'] : ""));
$tr_doc_id_label = RCView::td(array('style'=>'text-align:right'), $lang['api_196']);
$tr_doc_id_select = RCView::td(array(), $file_repo_opts);
$tr_doc_id = RCView::tr(array(), $tr_doc_id_label . $tr_doc_id_select);

// single record
$records = array('' => '--');
foreach($record_list as $r)
{
	$records[$r] = $r;
}
$record_opts = RCView::select(array('id'=>'api_record'), $records, (isset($_SESSION['api_record']) ? $_SESSION['api_record'] : ""));
$td_record_label = RCView::td(array('style'=>'text-align:right'), $lang['api_26']);
$td_record_select = RCView::td(array(), $record_opts);
$tr_record = RCView::tr(array(), $td_record_label . $td_record_select);

$new_record_input = RCView::input(array('id'=>'api_new_record', 'name'=>'api_new_record', 'type'=>'text', 'value' => (isset($_SESSION['api_new_record']) ? $_SESSION['api_new_record'] : "")));
$td_new_record_label = RCView::td(array('style'=>'text-align:right'), $lang['api_191']);
$td_new_record_input = RCView::td(array(), $new_record_input);
$tr_rename_to = RCView::tr(array(), $td_new_record_label . $td_new_record_input);

// multiple records
$records = array();
foreach($record_list as $r)
{
	$records[$r] = $r;
}
$records_size = count($records) <= 5 ? count($records) : 5;
$record_opts = RCView::select(array('id'=>'api_records', 'multiple'=>'multiple', 'size'=>$records_size, 'style'=>'padding: 1px 3px;'),
				$records, (isset($_SESSION['api_records']) ? $_SESSION['api_records'] : ""));
$td_records_label = RCView::td(array('style'=>'text-align:right'), $lang['api_31']);
$td_records_select = RCView::td(array(), $record_opts);
$tr_records = RCView::tr(array(), $td_records_label . $td_records_select);

// single field name
$field_names = array(''=>'--');
foreach($pg->getFieldNames($project_id) as $n)
{
	$field_names[$n] = $n;
}
$field_name_opts = RCView::select(array('id'=>'api_field_name'), $field_names, (isset($_SESSION['api_field_name']) ? $_SESSION['api_field_name'] : ""));
$td_field_name_label = RCView::td(array('style'=>'text-align:right'), $lang['api_27']);
$td_field_name_select = RCView::td(array(), $field_name_opts);
$tr_field_name = RCView::tr(array(), $td_field_name_label . $td_field_name_select);

// multiple field names
$field_names = $field_names_no_form_status = array();
foreach($pg->getFieldNames($project_id) as $n)
{
	$field_names[$n] = $n;
    if ($n != $Proj->metadata[$n]['form_name']."_complete") {
        $field_names_no_form_status[$n] = $n;
    }
}
$field_names_size = count($field_names) <= 5 ? count($field_names) : 5;
$field_name_opts = RCView::select(array('id'=>'api_field_names', 'multiple'=>'multiple', 'size'=>$field_names_size, 'style'=>'padding: 1px 3px;'),
								$field_names, (isset($_SESSION['api_field_names']) ? $_SESSION['api_field_names'] : ""));
$td_field_names_label = RCView::td(array('style'=>'text-align:right'), $lang['api_28']);
$td_field_names_select = RCView::td(array(), $field_name_opts);
$tr_field_names = RCView::tr(array(), $td_field_names_label . $td_field_names_select);

$field_names_size = count($field_names_no_form_status) <= 5 ? count($field_names_no_form_status) : 5;
$field_name_opts = RCView::select(array('id'=>'api_field_names', 'multiple'=>'multiple', 'size'=>$field_names_size, 'style'=>'padding: 1px 3px;'),
                                $field_names_no_form_status, (isset($_SESSION['api_field_names']) ? $_SESSION['api_field_names'] : ""));
$td_field_names_label = RCView::td(array('style'=>'text-align:right'), $lang['api_28']);
$td_field_names_select = RCView::td(array(), $field_name_opts);
$tr_field_names_no_form_status = RCView::tr(array(), $td_field_names_label . $td_field_names_select);

// type opts
$type_names = array();
foreach($pg->getTypeNames() as $n)
{
	$type_names[$n] = $n;
}
$type_name_opts = RCView::select(array('id'=>'api_type'), $type_names, $_SESSION['api_type']);
$td_type_name_label = RCView::td(array('style'=>'text-align:right'), $lang['api_30']);
$td_type_name_select = RCView::td(array(), $type_name_opts);
$tr_type_name = RCView::tr(array(), $td_type_name_label . $td_type_name_select);

$raw_label_types = $pg->getRawLabelTypes();

// raw or with labels
$label_names = array();
foreach($raw_label_types as $n)
{
	$label_names[$n] = $n;
}
$label_name_opts = RCView::select(array('id'=>'api_name_label'), $label_names, $_SESSION['api_name_label']);
$td_label_name_label = RCView::td(array('style'=>'text-align:right'), $lang['api_33']);
$td_label_name_select = RCView::td(array(), $label_name_opts);
$tr_label_name = RCView::tr(array(), $td_label_name_label . $td_label_name_select);

// date logic (for export records)
$td_date_begin_label = RCView::td(array('style'=>'text-align:right'), $lang['api_147']);
$td_date_begin_select = RCView::td(array(), RCView::text(array('id'=>'api_date_range_begin', 'value'=>(isset($_SESSION['api_date_range_begin']) ? $_SESSION['api_date_range_begin'] : ""))));
$tr_date_begin_name = RCView::tr(array(), $td_date_begin_label . $td_date_begin_select);
$td_date_end_label = RCView::td(array('style'=>'text-align:right'), $lang['api_148']);
$td_date_end_select = RCView::td(array(), RCView::text(array('id'=>'api_date_range_end', 'value'=>(isset($_SESSION['api_date_range_end']) ? $_SESSION['api_date_range_end'] : ""))));
$tr_date_end_name = RCView::tr(array(), $td_date_end_label . $td_date_end_select);

// filter logic (for export records)
$td_filter_logic_label = RCView::td(array('style'=>'text-align:right'), $lang['api_127']);
$td_filter_logic_select = RCView::td(array(), RCView::text(array('id'=>'api_filter_logic', 'value'=>(isset($_SESSION['api_filter_logic']) ? $_SESSION['api_filter_logic'] : ""))));
$tr_filter_logic_name = RCView::tr(array(), $td_filter_logic_label . $td_filter_logic_select);

// raw or with labels header
$header_names = array();
foreach($raw_label_types as $n)
{
	$header_names[$n] = $n;
}
$header_name_opts = RCView::select(array('id'=>'api_header_label'), $header_names, $_SESSION['api_header_label']);
$td_header_name_label = RCView::td(array('style'=>'text-align:right'), $lang['api_34']);
$td_header_name_select = RCView::td(array(), $header_name_opts);
$tr_header_name = RCView::tr(array(), $td_header_name_label . $td_header_name_select);

$bool_types = $pg->getBooleanTypes();

// export checkbox labels
$checkbox_names = array();
foreach($bool_types as $n)
{
	$checkbox_names[$n] = $n;
}
$checkbox_opts = RCView::select(array('id'=>'api_checkbox_label'), $checkbox_names, $_SESSION['api_checkbox_label']);
$td_checkbox_label = RCView::td(array('style'=>'text-align:right'), $lang['api_35']);
$td_checkbox_select = RCView::td(array(), $checkbox_opts);
$tr_checkbox_label = RCView::tr(array(), $td_checkbox_label . $td_checkbox_select);

// export survey fields
$survey_field_names = array();
foreach($bool_types as $n)
{
	$survey_field_names[$n] = $n;
}
$survey_field_opts = RCView::select(array('id'=>'api_survey_field'), $survey_field_names, $_SESSION['api_survey_field']);
$td_survey_field_label = RCView::td(array('style'=>'text-align:right'), $lang['api_36']);
$td_survey_field_select = RCView::td(array(), $survey_field_opts);
$tr_survey_field_label = RCView::tr(array(), $td_survey_field_label . $td_survey_field_select);

// returnMetadataOnly
$returnMetadataOnly_field_names = array();
foreach($bool_types as $n)
{
	$returnMetadataOnly_field_names[$n] = $n;
}
$returnMetadataOnly_field_opts = RCView::select(array('id'=>'api_returnMetadataOnly'), $returnMetadataOnly_field_names, $_SESSION['api_returnMetadataOnly']);
$td_returnMetadataOnly_field_label = RCView::td(array('style'=>'text-align:right'), $lang['api_129']);
$td_returnMetadataOnly_field_select = RCView::td(array(), $returnMetadataOnly_field_opts);
$tr_returnMetadataOnly_field_label = RCView::tr(array(), $td_returnMetadataOnly_field_label . $td_returnMetadataOnly_field_select);

// api_exportFiles
$returnXmlExportFiles = array();
foreach($bool_types as $n)
{
	$returnXmlExportFiles[$n] = $n;
}
$returnXmlExportFiles_opts = RCView::select(array('id'=>'api_exportFiles'), $returnXmlExportFiles, $_SESSION['api_exportFiles']);
$returnXmlExportFiles_field_label = RCView::td(array('style'=>'text-align:right'), $lang['api_149']);
$returnXmlExportFiles_field_select = RCView::td(array(), $returnXmlExportFiles_opts);
$tr_returnXmlExportFiles_field_label = RCView::tr(array(), $returnXmlExportFiles_field_label . $returnXmlExportFiles_field_select);


// export dags
$dag_names = array();
foreach($bool_types as $n)
{
	$dag_names[$n] = $n;
}
$dag_opts = RCView::select(array('id'=>'api_dag'), $dag_names, $_SESSION['api_dag']);
$td_dag_label = RCView::td(array('style'=>'text-align:right'), $lang['api_37']);
$td_dag_select = RCView::td(array(), $dag_opts);
$tr_dag = RCView::tr(array(), $td_dag_label . $td_dag_select);

// export report id
$report_names = array(''=>'--');
foreach(DataExport::getReportNames(null, !$user_rights['reports']) as $r)
{
	$report_names[$r['report_id']] = $r['title'];
}
$report_opts = RCView::select(array('id'=>'api_report_id'), $report_names, (isset($_SESSION['api_report_id']) ? $_SESSION['api_report_id'] : ""));
$td_report_label = RCView::td(array('style'=>'text-align:right'), $lang['api_38']);
$td_report_select = RCView::td(array(), $report_opts);
$tr_report = RCView::tr(array(), $td_report_label . $td_report_select);

// multiple dags for delete DAG call
$group_names = $Proj->getUniqueGroupNames();
$groups = array();
foreach($group_names as $unique_group_name)
{
    $groups[$unique_group_name] = $unique_group_name;
}

$groups_size = count($groups) <= 5 ? count($groups) : 5;
$select_opts = array('id'=>'group_id', 'multiple'=>'multiple', 'size'=>$groups_size);
$groups_opts = RCView::select($select_opts, $groups, (isset($_SESSION['group_id']) ? $_SESSION['group_id'] : ""));
$td_groups_label = RCView::td(array('style'=>'text-align:right'), $lang['api_37']);
$td_groups_select = RCView::td(array(), $groups_opts);
$tr_groups = RCView::tr(array(), $td_groups_label . $td_groups_select);

$dagSwitcher = new DAGSwitcher();
$userDags = $dagSwitcher->getUserDAGs(USERID);
$userDags = $userDags[USERID] ?? [];
$uniqueGroupNames = array();
$currentDagId = $user_rights['group_id'];
foreach ($userDags as $dagId) {
    if ($currentDagId != $dagId) {
        if (!empty($dagId)) {
            $uniqueGroupNames[] = $Proj->getUniqueGroupNames($dagId);
        } else {
            $uniqueGroupNames[] = '';
        }
    }
}
$count = 0;
// single dag selection from DAGs selected in DAG Switcher
$userDagOptions = [];
foreach($userDags as $groupId)
{
    if ($user_rights['group_id'] != $groupId) {
        $label = ($groupId == 0) ? $lang['data_access_groups_ajax_23'] : $Proj->getGroups($groupId);
        $value = ($groupId == 0) ? '' : $Proj->getUniqueGroupNames($groupId);
        $userDagOptions[$value] = $label;

        if ($count == 0 && !in_array($_SESSION['api_group_id'], $uniqueGroupNames)) {
            $_SESSION['api_group_id'] = $value;
        }
        $count++;
    }
}
$user_dag_opt = RCView::select(array('id'=>'api_group_id'), $userDagOptions, (isset($_SESSION['api_group_id']) ? $_SESSION['api_group_id'] : ""));
$td_user_dag_label = RCView::td(array('style'=>'text-align:right'), $lang['api_37']);
$td_user_dag_select = RCView::td(array(), $user_dag_opt);
$tr_user_dag = RCView::tr(array(), $td_user_dag_label . $td_user_dag_select);
// overwrite opts
$overwrite_names = array();
foreach($pg->getOverwriteOptions() as $o)
{
	$overwrite_names[$o] = $o;
}
$overwrite_opts = RCView::select(array('id'=>'api_overwrite'), $overwrite_names, $_SESSION['api_overwrite']);
$td_overwrite_label = RCView::td(array('style'=>'text-align:right'), $lang['api_39']);
$td_overwrite_select = RCView::td(array(), $overwrite_opts);
$tr_overwrite = RCView::tr(array(), $td_overwrite_label . $td_overwrite_select);

// forceAutoNumber
$tr_forceAutoNumber = '';
if ($Proj->project['auto_inc_set']) {
	$forceAutoNumber_names = array();
	foreach($pg->getBooleanTypes() as $o)
	{
		$forceAutoNumber_names[$o] = $o;
	}
	$forceAutoNumber_opts = RCView::select(array('id'=>'api_forceAutoNumber'), $forceAutoNumber_names, $_SESSION['api_forceAutoNumber']);
	$td_forceAutoNumber_label = RCView::td(array('style'=>'text-align:right'), $lang['api_146']);
	$td_forceAutoNumber_select = RCView::td(array(), $forceAutoNumber_opts);
	$tr_forceAutoNumber = RCView::tr(array(), $td_forceAutoNumber_label . $td_forceAutoNumber_select);
}

// data field textarea
$data_textarea = "<textarea id='api_data' name='api_data' class='x-form-field notesbox' style='width:500px;'>".(isset($_SESSION['api_data']) ? $_SESSION['api_data'] : "")."</textarea>";
$td_data_label = RCView::td(array('valign'=>'top', 'style'=>'padding-top:5px;text-align:right'), $lang['api_40']);
$data_form = RCView::form(array('id'=>'data_form', 'method'=>'post', 'action'=>"playground.php?pid=$project_id"), $data_textarea);
$td_data_textarea = RCView::td(array(), $data_form);
$tr_data = RCView::tr(array(), $td_data_label . $td_data_textarea);

// date format
$date_format_opts = RCView::select(array('id'=>'api_date_format'), $pg->getDateFormatOptions(), (isset($_SESSION['api_date_format']) ? $_SESSION['api_date_format'] : ""));
$td_date_format_label = RCView::td(array('style'=>'text-align:right'), $lang['api_42']);
$td_date_format_select = RCView::td(array(), $date_format_opts);
$tr_date_format = RCView::tr(array(), $td_date_format_label . $td_date_format_select);

// file field
$file_name = '';
if(isset($_SESSION['api_file_path']))
{
	$file_name = basename($_SESSION['api_file_path']) . '<br />';
}
$file_input = RCView::input(array('id'=>'api_file', 'name'=>'api_file', 'type'=>'file'), (isset($_SESSION['api_file']) ? $_SESSION['api_file'] : ""));
$td_file_label = RCView::td(array('style'=>'text-align:right'), $lang['api_45']);
$update_href = RCView::a(array('id'=>'update_file', 'href'=>'javascript:;'), '<small>' . $lang['api_41'] . '</small>');
$file_form = RCView::form(array('id'=>'file_form', 'method'=>'post', 'action'=>"playground.php?pid=$project_id", 'enctype'=>'multipart/form-data'),
				$file_input);
$td_file_input = RCView::td(array(), $file_name . $file_form . $update_href);
$tr_file = RCView::tr(array(), $td_file_label . $td_file_input);

// export all records
$all_records_names = array();
foreach($bool_types as $n)
{
	$all_records_names[$n] = $n;
}
$all_records_opts = RCView::select(array('id'=>'api_all_records'), $all_records_names, $_SESSION['api_all_records']);
$td_all_records_label = RCView::td(array('style'=>'text-align:right'), $lang['api_47']);
$td_all_records_select = RCView::td(array(), $all_records_opts);
$tr_all_records = RCView::tr(array(), $td_all_records_label . $td_all_records_select);

// return content
$return_content_opts = RCView::select(array('id'=>'api_return_content'), $pg->getReturnContentOptions(), $_SESSION['api_return_content']);
$td_return_content_label = RCView::td(array('style'=>'text-align:right'), $lang['api_43']);
$td_return_content_select = RCView::td(array(), $return_content_opts);
$tr_return_content = RCView::tr(array(), $td_return_content_label . $td_return_content_select);

// multiple users for delete User call
$usersList = REDCap::getUsers();
$users = array();
foreach($usersList as $username)
{
    if (!User::isAdmin($username)) {
        $users[$username] = $username;
    }
}

$users_size = count($users) <= 5 ? count($users) : 5;
$select_opts = array('id'=>'api_users', 'multiple'=>'multiple', 'size' => $users_size);
$users_opts = RCView::select($select_opts, $users, (isset($_SESSION['api_users']) ? $_SESSION['api_users'] : ""));
$td_users_label = RCView::td(array('style'=>'text-align:right'), $lang['messaging_127']);
$td_users_select = RCView::td(array(), $users_opts);
$tr_users = RCView::tr(array(), $td_users_label . $td_users_select);

$allRoles = UserRights::getRoles();
$roles_size = count($allRoles) <= 5 ? count($allRoles) : 5;
$roles = array();
foreach ($allRoles as $roleId => $roleInfo) {
    $roles[$roleInfo['unique_role_name']] = $roleInfo['role_name'] ." (".$roleInfo['unique_role_name'].")";
}
$select_opts = array('id'=>'api_user_roles', 'multiple'=>'multiple', 'size'=>$roles_size);
$roles_opts = RCView::select($select_opts, $roles, (isset($_SESSION['api_user_roles']) ? $_SESSION['api_user_roles'] : ""));
$td_roles_label = RCView::td(array('style'=>'text-align:right'), $lang['api_166']);
$td_roles_select = RCView::td(array(), $roles_opts);
$tr_roles = RCView::tr(array(), $td_roles_label . $td_roles_select);
// filter fields
$logging_filter_html = Logging::getFilterHTML($_SESSION, true);

$rids = array(''=>'--');
$projRands = Randomization::getAllRandomizationAttributes($project_id);
foreach ($projRands as $thisRid => $thisRandAttr)
{
    $rids[$thisRid] = "$thisRid \"".strip_tags($thisRandAttr['targetField'])."\"";
}
$rid_opts = RCView::select(array('id'=>'api_rid'), $rids, (isset($_SESSION['api_rid']) ? $_SESSION['api_rid'] : ""));
$td_rid_label = RCView::td(array('style'=>'text-align:right'), $lang['random_197']);
$td_rid_select = RCView::td(array(), $rid_opts);
$tr_rid = RCView::tr(array(), $td_rid_label . $td_rid_select);

$return_alt_names = array();
foreach($bool_types as $n)
{
	$return_alt_names[$n] = $n;
}
$return_alt_opts = RCView::select(array('id'=>'api_return_alt'), $return_alt_names, $_SESSION['api_return_alt']);
$td_return_alt_label = RCView::td(array('style'=>'text-align:right'), $lang['random_198']);
$td_return_alt_select = RCView::td(array(), $return_alt_opts);
$tr_return_alt = RCView::tr(array(), $td_return_alt_label . $td_return_alt_select);


$tbody_content = $tr_call . $tr_fmt;

switch($_SESSION['api_call'])
{
case 'exp_events':
	$tbody_content .= $tr_arms . $tr_return_fmt;
	break;
case 'imp_events':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'del_events':
	$tbody_content .= $tr_events . $tr_return_fmt;
	break;
case 'exp_users':
	$tbody_content .= $tr_return_fmt;
	break;
case 'imp_users':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'exp_inst_event_maps':
	$tbody_content .= $tr_arms . $tr_return_fmt;
	break;
case 'imp_inst_event_maps':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'exp_arms':
	$tbody_content .= $tr_arms . $tr_return_fmt;
	break;
case 'imp_arms':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'del_arms':
	$tbody_content .= $tr_arms . $tr_return_fmt;
	break;
case 'exp_surv_parts':
	$tbody_content .= $tr_inst . $tr_event . $tr_return_fmt;
	break;
case 'exp_surv_ret_code':
	$tbody_content .= $tr_inst . $tr_event . $tr_record . $tr_return_fmt;
	break;
case 'exp_surv_queue_link':
	$tbody_content .= $tr_record . $tr_return_fmt;
	break;
case 'exp_surv_link':
	$tbody_content .= $tr_inst . $tr_event . $tr_record . $tr_return_fmt;
	break;
case 'exp_surv_access_code':
	$tbody_content .= $tr_inst . $tr_event . $tr_record . $tr_return_fmt;
	break;
case 'exp_instr':
	$tbody_content .= $tr_return_fmt;
	break;
case 'exp_repeating_forms_events':
	$tbody_content .= $tr_return_fmt;
	break;
case 'imp_repeating_forms_events':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'exp_metadata':
	$tbody_content .= $tr_field_names_no_form_status . $tr_insts . $tr_return_fmt;
	break;
case 'imp_metadata':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'exp_field_names':
	$tbody_content .= $tr_field_name . $tr_return_fmt;
	break;
case 'exp_next_id':
	$tbody_content .= $tr_return_fmt;
	break;
case 'exp_proj':
	$tbody_content .= $tr_return_fmt;
	break;
case 'exp_proj_xml':
	$tbody_content = $tr_call . $tr_returnMetadataOnly_field_label . $tr_records . $tr_field_names . $tr_events .
		$tr_survey_field_label .
		$tr_dag . $tr_filter_logic_name . $tr_returnXmlExportFiles_field_label . $tr_return_fmt;
	break;
case 'imp_proj_sett':
	$tbody_content .= $tr_data;
	break;
case 'imp_proj':
	$tbody_content .= $tr_data . $tr_return_fmt;
	break;
case 'exp_records':
	$tbody_content .= $tr_type_name . $tr_records . $tr_field_names . $tr_insts . $tr_events .
		$tr_label_name . $tr_header_name . $tr_checkbox_label . $tr_survey_field_label .
		$tr_dag . $tr_filter_logic_name . $tr_date_begin_name . $tr_date_end_name . $tr_csvdelimiter . $tr_return_fmt;
	break;
case 'exp_reports':
	$tbody_content .= $tr_report . $tr_label_name . $tr_header_name . $tr_checkbox_label . $tr_csvdelimiter . $tr_return_fmt;
	break;
case 'imp_records':
	$tbody_content .= $tr_type_name . $tr_overwrite . $tr_forceAutoNumber . $tr_data . $tr_date_format . $tr_return_content . $tr_return_fmt;
        break;
case 'del_records':
	$tbody_content .= $tr_records . $tr_arm . $tr_inst . $tr_event . $tr_return_fmt;
	break;
case 'rename_record':
    $tbody_content .= $tr_arm . $tr_record . $tr_rename_to . $tr_return_fmt;
    break;
case 'randomize':
    $tbody_content .= $tr_record . $tr_rid . $tr_return_alt . $tr_return_fmt;
    break;
case 'imp_file':
	$tbody_content .= $tr_record . $tr_field_name . $tr_event . $tr_file . $tr_return_fmt;
	break;
case 'imp_file_repo':
	$tbody_content .= $tr_file . $tr_return_fmt;
	break;
case 'del_file':
	$tbody_content .= $tr_record . $tr_field_name . $tr_event . $tr_return_fmt;
	break;
case 'del_file_repo':
	$tbody_content .= $tr_doc_id . $tr_return_fmt;
	break;
case 'exp_file':
	$tbody_content .= $tr_record . $tr_field_name . $tr_event . $tr_return_fmt;
	break;
case 'exp_file_repo':
	$tbody_content .= $tr_doc_id . $tr_return_fmt;
	break;
case 'create_folder_file_repo':
	$tbody_content .= $tr_folder_name . $tr_folder_id . $tr_return_fmt;
	break;
case 'exp_list_file_repo':
	$tbody_content .= $tr_folder_id . $tr_return_fmt;
	break;
case 'exp_instr_pdf':
	$tbody_content .= $tr_record . $tr_event . $tr_inst . $tr_all_records . $tr_return_fmt;
	break;
case 'exp_dags':
    $tbody_content .= $tr_return_fmt;
    break;
case 'imp_dags':
    $tbody_content .= $tr_data . $tr_return_fmt;
    break;
case 'del_dags':
    $tbody_content .= $tr_groups . $tr_return_fmt;
    break;
case 'switch_dag':
    $tbody_content .= $tr_user_dag . $tr_return_fmt;
    break;
case 'exp_user_dag_maps':
    $tbody_content .= $tr_return_fmt;
    break;
case 'imp_user_dag_maps':
    $tbody_content .= $tr_data . $tr_return_fmt;
    break;
case 'exp_logging':
    $tbody_content .= $logging_filter_html . $tr_return_fmt;
    break;
case 'del_users':
    $tbody_content .= $tr_users . $tr_return_fmt;
    break;
case 'exp_user_roles':
    $tbody_content .= $tr_return_fmt;
    break;
case 'imp_user_roles':
    $tbody_content .= $tr_data . $tr_return_fmt;
    break;
case 'del_user_roles':
    $tbody_content .= $tr_roles . $tr_return_fmt;
    break;
case 'exp_user_role_maps':
    $tbody_content .= $tr_return_fmt;
    break;
case 'imp_user_role_maps':
    $tbody_content .= $tr_data . $tr_return_fmt;
    break;

}

$tbody = RCView::tbody(array(), $tbody_content);
$table = RCView::div(array('style'=>'margin:0 0 10px;'), $lang['api_77']) . RCView::table(array('id'=>'api_playground_params'), $tbody);
echo RCView::table(array('style'=>'width:750px;margin-top:20px;'), RCView::tbody(array(), RCView::tr(array(), RCView::td(array('class'=>'blue'), $table))));

// request
$raw_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_14']);
$raw_img = RCView::img(array('src'=>'arrow_up.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$raw_a = RCView::a(array('id'=>'raw', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px', 'name'=>'data'), $raw_img . $raw_span);
$raw_li = RCView::li(array('class'=>'active'), $raw_a);

$data_ul = RCView::ul(array(), $raw_li);
$data_div = RCView::div(array('id'=>'sub-nav', 'class'=>'d-print-none', 'style'=>'margin:30px 0 15px 0;'), $data_ul);
echo $data_div;
echo RCView::div(array('style'=>'margin-bottom:5px;'), $lang['api_75']);

echo RCView::textarea(array('style'=>'font-size:1.1em; font-family:monospace', 'rows'=>7, 'cols'=>96, 'readonly'=>'readonly'), $pg->getRawData());

// response
$resp_span = RCView::span(array('style'=>'vertical-align:middle'), $lang['api_16']);
$resp_img = RCView::img(array('src'=>'arrow_down.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$resp_a = RCView::a(array('id'=>'resp', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $resp_img . $resp_span);
$resp_li = RCView::li(array('class'=>'active'), $resp_a);

$data_ul = RCView::ul(array(), $resp_li);
$data_div = RCView::div(array('id'=>'sub-nav', 'class'=>'d-print-none', 'style'=>'margin:25px 0 15px 0;'), $data_ul);
echo $data_div;

echo RCView::div(array('style'=>'margin-bottom:10px;'), $lang['api_76']);
echo RCView::div(array('style'=>'width: 155px; float:left; margin:-15px 0 0 0'), RCView::br() . RCView::button(array('id'=>'exec_req', 'class'=>'jqbuttonmed'), RCView::span(array('class'=>'ui-button-text'), RCView::img(array('src'=>'arrow_up.png', 'style'=>'vertical-align:middle; position:relative; top:-1px', 'height'=>14, 'width'=>14)) . RCView::span(array('style'=>'vertical-align:middle'), $lang['api_73'])))) . RCView::div(array(), RCView::img(array('id'=>'wait', 'src'=>'progress_circle.gif', 'style'=>'display:none; margin:3px 0 0 0; width:16px; height:16px')) . RCView::img(array('src'=>'pixel.gif', 'style'=>'margin:3px 0 0 0; width:16px; height:16px'))) . RCView::br();

echo RCView::div(array('id'=>'exec_resp', 'style'=>'display:none;'), '&nbsp;');

// code views
$php_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_17']);
$php_img = RCView::img(array('src'=>'php.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$php_a = RCView::a(array('id'=>'php', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px', 'name'=>'langs'), $php_img . $php_span);
$php_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'php' ? 'active' : ''), $php_a);

$perl_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_18']);
$perl_img = RCView::img(array('src'=>'perl.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$perl_a = RCView::a(array('id'=>'perl', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $perl_img . $perl_span);
$perl_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'perl' ? 'active' : ''), $perl_a);

$python_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_19']);
$python_img = RCView::img(array('src'=>'python.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$python_a = RCView::a(array('id'=>'python', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $python_img . $python_span);
$python_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'python' ? 'active' : ''), $python_a);

$ruby_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_20']);
$ruby_img = RCView::img(array('src'=>'ruby.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$ruby_a = RCView::a(array('id'=>'ruby', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $ruby_img . $ruby_span);
$ruby_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'ruby' ? 'active' : ''), $ruby_a);

$java_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_21']);
$java_img = RCView::img(array('src'=>'java.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$java_a = RCView::a(array('id'=>'java', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $java_img . $java_span);
$java_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'java' ? 'active' : ''), $java_a);

$csharp_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_230']);
$csharp_img = RCView::img(array('src'=>'csharp.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$csharp_a = RCView::a(array('id'=>'csharp', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $csharp_img . $csharp_span);
$csharp_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'csharp' ? 'active' : ''), $csharp_a);

$r_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_70']);
$r_img = RCView::img(array('src'=>'r.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$r_a = RCView::a(array('id'=>'r', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $r_img . $r_span);
$r_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'r' ? 'active' : ''), $r_a);

$curl_span = RCView::span(array('style'=>'vertical-align:middle'), '&nbsp;' . $lang['api_71']);
$curl_img = RCView::img(array('src'=>'curl.png', 'style'=>'vertical-align:middle; height:14px; width:14px'));
$curl_a = RCView::a(array('id'=>'curl', 'href'=>'javascript:;', 'style'=>'font-size:13px; color:#393733; padding:6px 9px 5px 10px'), $curl_img . $curl_span);
$curl_li = RCView::li(array('class'=>$_SESSION['code_tab'] == 'curl' ? 'active' : ''), $curl_a);

$code_ul = RCView::ul(array(), $php_li . $perl_li . $python_li . $ruby_li . $java_li . $r_li . $csharp_li . $curl_li);
$code_div = RCView::div(array('id'=>'sub-nav', 'class'=>'d-print-none', 'style'=>'margin:20px 0 15px 0;'), $code_ul);
echo $code_div;
echo RCView::div(array('class'=>'clear'), '&nbsp;');
echo RCView::div(array('style'=>'margin-bottom:5px;'), $lang['api_74']);
echo RCView::textarea(array('style'=>'font-size:1.1em; font-family:monospace', 'rows'=>12, 'cols'=>96, 'readonly'=>'readonly'), $pg->getCode());
echo RCView::br() . RCView::br() . RCView::a(array('href'=>"get_api_code.php?lang=$_SESSION[code_tab]"), RCView::img(array('src'=>'download.png', 'style'=>'vertical-align:middle'))) . ' ' . RCView::a(array('href'=>"get_api_code.php?lang=$_SESSION[code_tab]"), $lang['api_68'] . $pg->getLangName($_SESSION['code_tab']) . " " . $lang['api_192']);

$langs_js = '';
foreach(APIPlayground::getLangs() as $l)
{
	$langs_js .= "\$('a#$l').click(function(){window.location.href='?pid=$project_id&code_tab=$l#langs';});\n";
}

$single_js = '';
$selects = array(
	'api_call',
	'api_fmt',
	'api_return',
	'api_event',
	'api_record',
	'api_inst',
	'api_field_name',
	'api_type',
	'api_name_label',
	'api_header_label',
	'api_checkbox_label',
	'api_survey_field',
	'api_dag',
	'api_report_id',
	'api_overwrite',
	'api_forceAutoNumber',
	'api_date_format',
	'api_return_content',
	'api_all_records',
	'api_returnMetadataOnly',
	'api_exportFiles',
    'logtype',
    'usr',
    'record',
    'dag',
    'api_group_id',
    'arm_num',
	'api_doc_id',
	'api_folder_id',
    'api_rid',
    'api_return_alt'
);
foreach($selects as $s)
{
	// $single_js .= "\$('select#$s').change(function(){debugger; console.log('?pid=$project_id&$s='+\$('select#$s :selected').val());window.location.href='?pid=$project_id&$s='+\$('select#$s :selected').val();});";
	$single_js .= "\$('select#$s').change(function(){window.location.href='?pid=$project_id&$s='+\$('select#$s :selected').val();});";
}

$multi_js = '';
$selects = array(
	'arm_nums',
	'api_insts',
	'api_records',
	'api_events',
	'api_field_names',
	'api_csvdelimiter',
    'group_id',
    'api_users',
    'api_user_roles'
);
foreach($selects as $s)
{
	$multi_js .= "\$('select#$s').change(function(){window.location.href='?pid=$project_id&$s='+\$('select#$s').val();});";
}

$text_js = '';
$textboxes = array(
	'api_filter_logic',
	'api_date_range_begin',
	'api_date_range_end',
    'beginTime',
    'endTime',
    'api_new_record',
    'api_folder_name'
);
foreach($textboxes as $s)
{
	$text_js .= "\$('input[type=text]#$s').change(function(){window.location.href='?pid=$project_id&$s='+\$('input[type=text]#$s').val();});";
}

$dates_js = "$(function() {
	$('#beginTime, #endTime').datetimepicker({
		yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: 'yy-mm-dd',
		hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
		showOn: 'button', buttonImage: app_path_images+'date.png', buttonImageOnly: true, timeFormat: 'HH:mm', constrainInput: false
	});
});";

// js
echo <<<EOF
<script type="text/javascript">
$langs_js
$single_js
$multi_js
$text_js
$dates_js

/* data textarea */
$('#api_data').blur(function(){
	showProgress(1);
	$('form#data_form').submit();
});

/* file upload */
$('a#update_file').click(function(){
	showProgress(1);
	$('form#file_form').submit();
});

/* execute api request */
$('button#exec_req').click(function(){
  $('img#wait').show();
  $.ajax({
      url: 'playground_api_call.php?pid=$project_id',
      success: function(data) { eval(data); }
  });
});

$('textarea').resizable();

$('select[multiple]').keydown(function( event ) {
	if (event.which == 38 || event.which == 40) {
		event.preventDefault();
	}
});

</script>
EOF;

echo RCView::br() . RCView::br() . RCView::br();
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
