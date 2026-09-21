<?php

// Required files
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// If ID is not in query_string, then return error
// Also, must be a REDCap admin to delete files
if (!is_numeric($_POST['id']) || !is_numeric($_POST['res_id']) || !SUPER_USER) exit("{$lang['global_01']}!");


//Download file from the "edocs" web server directory
$sql = "select m.* from redcap_edocs_metadata m, redcap_data_quality_resolutions r
		where m.project_id = $project_id and m.doc_id = ".checkNull($_POST['id'])."
		and r.res_id = ".checkNull($_POST['res_id'])." and r.upload_doc_id = m.doc_id limit 1";
$q = db_query($sql);
if (!db_num_rows($q)) exit("<b>{$lang['global_01']}:</b> {$lang['file_download_03']}");
$this_file = db_fetch_assoc($q);

// Delete the file
$sql = "update redcap_edocs_metadata
		set delete_date = '".NOW."'
		where project_id = $project_id and doc_id = ".$this_file['doc_id'];
$q = db_query($sql);
$sql = "update redcap_data_quality_resolutions
		set upload_doc_id = null
		where res_id = ".checkNull($_POST['res_id']);
$q = db_query($sql);

## Logging
// Obtain record, event, field, rule from res_id
$dq = new DataQuality();
$queryAttr = $dq->getDataResAttributesFromResId($_POST['res_id']);
// Set event_id in query string just for logging purposes
$_GET['event_id'] = $queryAttr['event_id'];
// Set data values as json_encoded
$logDataValues = json_encode(array('res_id'=>$_POST['res_id'],'doc_id'=>$_POST['id'],'record'=>$queryAttr['record'],'event_id'=>$queryAttr['event_id'],
					'field'=>$queryAttr['field_name'],'rule_id'=>$queryAttr['rule_id']));
// Lot it
Logging::logEvent($sql,"redcap_edocs_metadata","MANAGE",$queryAttr['record'],$logDataValues,"Delete uploaded document for data query response");
print 	RCView::div(array('class'=>'green'),
			'<i class="fas fa-check"></i> '.$lang['dataqueries_327']
		);