<?php
use Vanderbilt\REDCap\Classes\MyCap\Task;
// Call config file
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Make sure server has ZipArchive ability (i.e. is on PHP 5.2.0+)
if (!Files::hasZipArchive()) {
	exit('ERROR: ZipArchive is not installed. It must be installed to use this feature.');
}

// If calling draft mode, then make sure project is in draft mode
if (isset($_GET['draft']) && !($status > 0 && $draft_mode > 0)) unset($_GET['draft']);

// Get form fields
$forms = (isset($_GET['draft'])) ? $Proj->forms_temp : $Proj->forms;
// If form exists in draft mode but not live, then return different error
if ($status > 0 && $draft_mode > 0 && isset($Proj->forms_temp[$_GET['page']]) && !isset($Proj->forms[$_GET['page']])) {
	include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	print RCView::div(array('class'=>'yellow mt-5', 'style'=>'max-width:650px;'),
			$lang['design_943'] . " \"".RCView::b($Proj->forms_temp[$_GET['page']]['menu'])."\" " . $lang['design_944'] . " " .
			RCView::a(array('href'=>APP_PATH_WEBROOT.'Design/online_designer.php?pid='.$project_id, 'style'=>'text-decoration:underline;'), $lang['design_25']) . $lang['period']
	);
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;
}
// Is valid form?
if (!isset($forms[$_GET['page']])) exit($lang['global_01']);

// Set name of temp file for zip
$inOneHour = date("YmdHis", mktime(date("H")+1,date("i"),date("s"),date("m"),date("d"),date("Y")));

## Google Cloud Storage doesn't allow zipping of files, must be done in system temp
if($edoc_storage_option == '3') {
	$target_zip = sys_get_temp_dir() . "/{$inOneHour}_pid{$project_id}_".generateRandomHash(6).".zip";
}
else {
	$target_zip = APP_PATH_TEMP . "{$inOneHour}_pid{$project_id}_".generateRandomHash(6).".zip";
}

$download_filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($forms[$_GET['page']]['menu'], ENT_QUOTES)))), 0, 20)
				   . "_".(isset($_GET['draft']) ? "draft_" : "").date("Y-m-d_Hi").".zip";
$zip_parent_folder = "attachments";
// Generate data dictionary file for *just* this form
$data_dictionary = addBOMtoUTF8(MetaData::getDataDictionary('csv', true, array_keys($forms[$_GET['page']]['fields']), array(), false, isset($_GET['draft'])));

// If using WebDAV storage, then connect to WebDAV beforehand
if ($edoc_storage_option == '1') {
	if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
	$wdc = new WebdavClient();
	$wdc->set_server($webdav_hostname);
	$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
	$wdc->set_user($webdav_username);
	$wdc->set_pass($webdav_password);
	$wdc->set_protocol(1); //use HTTP/1.1
	$wdc->set_debug(false);
	if (!$wdc->open()) {
		exit($lang['global_01'].': '.$lang['file_download_11']);
	}
	if (substr($webdav_path,-1) != '/') {
		$webdav_path .= '/';
	}
}

// Create zip file
$zip = new ZipArchive;
// Start writing to zip file
if ($zip->open($target_zip, ZipArchive::CREATE) !== TRUE) exit("ERROR!");
// Add OriginID.txt to zip file
$zip->addFromString("OriginID.txt", SERVER_NAME);
// Add data dictionary to zip file
$zip->addFromString("instrument.csv", $data_dictionary);
// Add any attachments
$metadata = (isset($_GET['draft'])) ? $Proj->metadata_temp : $Proj->metadata;
$metadataTable = (isset($_GET['draft'])) ? "redcap_metadata_temp" : "redcap_metadata";
$edocs = $video_urls = array();
foreach ($metadata as $this_field=>$attr) {
	// Only look at descriptive fields for this form
	if (!($attr['form_name'] == $_GET['page'] && $attr['element_type'] == 'descriptive')) continue;
	// Add edoc_id to array
	if (is_numeric($attr['edoc_id'])) {
		$edocs[$attr['edoc_id']]['field'] = $this_field;
	}
	// Add video URL to array
	elseif ($attr['video_url'] != '') {
		$video_urls[$this_field] = $attr['video_url'];
	}
}
// If attachments exist, then get their file metadata attributes
if (!empty($edocs)) {
	// Get file metadata from table
	$sql = "select doc_id, stored_name, doc_name from redcap_edocs_metadata
			where project_id = $project_id and doc_id in (".prep_implode(array_keys($edocs)).")";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		$edocs[$row['doc_id']]['stored_name'] = $row['stored_name'];
		$edocs[$row['doc_id']]['doc_name'] = $row['doc_name'];
	}
	// Loop through documents and add them to zip
	foreach ($edocs as $this_edoc_id=>$attr) {
		// Set attachment filename in the zip
		$attachment_zip_filename = "$zip_parent_folder/{$attr['field']}/{$attr['doc_name']}";
		// If not using local storage for edocs, then obtain file contents before adding to zip
		if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
			// LOCAL: Add from "edocs" folder (use default or custom path for storage)
			if (file_exists(EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $attr['stored_name'])) {
				// Make sure file exists first before adding it, otherwise it'll cause it all to fail if missing
				$zip->addFile(EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $attr['stored_name'], $attachment_zip_filename);
			}
		} elseif ($edoc_storage_option == '2') {
			// S3
			// Open connection to create file in memory and write to it
			try {
				$s3 = Files::s3client();
				$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$attr['stored_name'], 'SaveAs'=>APP_PATH_TEMP . $attr['stored_name']));
				// Make sure file exists first before adding it, otherwise it'll cause it all to fail if missing
				if (file_exists(APP_PATH_TEMP . $attr['stored_name'])) {
					// Get file's contents from temp directory and add file contents to zip file
					$zip->addFromString($attachment_zip_filename, file_get_contents(APP_PATH_TEMP . $attr['stored_name']));
					// Now remove file from temp directory
					unlink(APP_PATH_TEMP . $attr['stored_name']);
				}
			} catch (Aws\S3\Exception\S3Exception $e) {
			}
		} elseif ($edoc_storage_option == '4') {
			// Azure
			$blobClient = new AzureBlob();
            $data = $blobClient->getBlob($attr['stored_name']);
			$zip->addFromString($attachment_zip_filename, $data);
			unset($data);
		} elseif ($edoc_storage_option == '5') {
            // Google
            $googleClient = Files::googleCloudStorageClient();
            $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);
            $googleClient->registerStreamWrapper();
            $data = file_get_contents('gs://'.$GLOBALS['google_cloud_storage_api_bucket_name'].'/' . $attr['stored_name']);
            $zip->addFromString($attachment_zip_filename, $data);
            unset($data);
        } else {
			// WebDAV
			$contents = '';
			$wdc->get($webdav_path . $attr['stored_name'], $contents); //$contents is produced by webdav class
			// Add file contents to zip file
			if ($contents == null) $contents = '';
			$zip->addFromString($attachment_zip_filename, $contents);
		}
	}
}
// Add video_urls, if any
foreach ($video_urls as $this_field=>$this_url) {
	// Add to zip
	$zip->addFromString("$zip_parent_folder/$this_field/video_url.URL", "[InternetShortcut]\nURL=".$this_url);
}

// If we're dealing with a survey, retrieve the survey settings
if (isset($Proj->forms[$_GET['page']]['survey_id']) && isset($Proj->surveys[$Proj->forms[$_GET['page']]['survey_id']]))
{
    $surveyId = $Proj->forms[$_GET['page']]['survey_id'];
    $surveyInfo = $Proj->surveys[$surveyId];

    // Column headers for the exported CSV file, reflects the columns in the redcap_surveys database table, excluding those that use unique keys
    $surveyHeaders = array("title","instructions","offline_instructions","acknowledgement","stop_action_acknowledgement",
        "stop_action_delete_response","question_by_section","display_page_number","question_auto_numbering","survey_enabled",
        "save_and_return","save_and_return_code_bypass","hide_title","view_results","min_responses_view_results","check_diversity_view_results",
        "end_survey_redirect_url","survey_expiration","promis_skip_question","survey_auth_enabled_single","edit_completed_response",
        "hide_back_button","show_required_field_text","confirmation_email_subject","confirmation_email_content","confirmation_email_from",
        "confirmation_email_from_display","confirmation_email_attach_pdf","text_to_speech","text_to_speech_language","end_survey_redirect_next_survey","theme",
        "text_size","font_family","theme_text_buttons","theme_bg_page","theme_text_title","theme_bg_title","theme_text_sectionheader",
        "theme_bg_sectionheader","theme_text_question","theme_bg_question","enhanced_choices","repeat_survey_enabled","repeat_survey_btn_text",
        "repeat_survey_btn_location","response_limit","response_limit_include_partials","response_limit_custom_text",
        "survey_time_limit_days","survey_time_limit_hours","survey_time_limit_minutes","email_participant_field","end_of_survey_pdf_download"
    );

    // Get the doc id for possible files uploaded for survey settings
    $surveyEmailAttach = $surveyInfo["confirmation_email_attachment"];
    $surveyLogo = $surveyInfo["logo"];
    $surveyEdocs = array();

    // Get information in order to download and store edocs in the ZIP export
    if ($surveyEmailAttach > 0) {
        $surveyEdocs[$surveyEmailAttach]['store_folder'] = "survey_attachments/confirmation_email_attachment";
    }
    if ($surveyLogo > 0) {
        $surveyEdocs[$surveyLogo]['store_folder'] = "survey_attachments/logo";
    }
    $sql = "select doc_id, stored_name, doc_name from redcap_edocs_metadata
			where project_id = $project_id and doc_id in (".prep_implode(array_keys($surveyEdocs)).")";
    $q = db_query($sql);
    while ($row = db_fetch_assoc($q)) {
        $surveyEdocs[$row['doc_id']]['stored_name'] = $row['stored_name'];
        $surveyEdocs[$row['doc_id']]['doc_name'] = $row['doc_name'];
    }
    foreach ($surveyEdocs as $sDocID => $attr) {
        if ($attr['stored_name'] == "" || $attr['store_folder'] == "" || $attr['doc_name'] == "") continue;
        $attachment_zip_filename = "{$attr['store_folder']}/{$attr['doc_name']}";
        // If not using local storage for edocs, then obtain file contents before adding to zip
        if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
            // LOCAL: Add from "edocs" folder (use default or custom path for storage)
            if (file_exists(EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $attr['stored_name'])) {
                // Make sure file exists first before adding it, otherwise it'll cause it all to fail if missing
                $zip->addFile(EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $attr['stored_name'], $attachment_zip_filename);
            }
        } elseif ($edoc_storage_option == '2') {
            // S3
            // Open connection to create file in memory and write to it
            try {
                $s3 = Files::s3client();
                $object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$attr['stored_name'], 'SaveAs'=>APP_PATH_TEMP . $attr['stored_name']));
                // Make sure file exists first before adding it, otherwise it'll cause it all to fail if missing
                if (file_exists(APP_PATH_TEMP . $attr['stored_name'])) {
                    // Get file's contents from temp directory and add file contents to zip file
                    $zip->addFromString($attachment_zip_filename, file_get_contents(APP_PATH_TEMP . $attr['stored_name']));
                    // Now remove file from temp directory
                    unlink(APP_PATH_TEMP . $attr['stored_name']);
                }
            } catch (Aws\S3\Exception\S3Exception $e) {
            }
        } elseif ($edoc_storage_option == '4') {
            // Azure
            $blobClient = new AzureBlob();
            $data = $blobClient->getBlob($attr['stored_name']);
            $zip->addFromString($attachment_zip_filename, $data);
            unset($data);
        } elseif ($edoc_storage_option == '5') {
            // Google
            $googleClient = Files::googleCloudStorageClient();
            $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);
            $googleClient->registerStreamWrapper();
            $data = file_get_contents('gs://'.$GLOBALS['google_cloud_storage_api_bucket_name'].'/' . $attr['stored_name']);
            $zip->addFromString($attachment_zip_filename, $data);
            unset($data);
        } else {
            // WebDAV
            $contents = '';
            $wdc->get($webdav_path . $attr['stored_name'], $contents); //$contents is produced by webdav class
            // Add file contents to zip file
            if ($contents == null) $contents = '';
            $zip->addFromString($attachment_zip_filename, $contents);
        }
    }

    // Unset any survey settings that are keys which need to be changed upon import later
    foreach ($surveyInfo as $sKey => $sInfo) {
        if (!in_array($sKey,$surveyHeaders)) {
            unset($surveyInfo[$sKey]);
        }
    }

    $fp = fopen('php://memory', "x+");
    // Add headers
	fputcsv($fp, array_keys($surveyInfo), ',', '"', '');
    fputcsv($fp, $surveyInfo, ',', '"', '');

    // Open file for reading and output to user
    fseek($fp, 0);
    $surveySettings = stream_get_contents($fp);
    // Replace CR+LF with just LF for better compatibility with Excel on Macs
    $surveySettings = str_replace("\r\n", "\n", $surveySettings);
    $zip->addFromString("survey_settings.csv", $surveySettings);
}
// If we're dealing with a mycap task, retrieve the mycap task settings
if ($Proj->project['mycap_enabled'] && isset($myCapProj->tasks[$_GET['page']]['task_id'])) {
    $taskId = $myCapProj->tasks[$_GET['page']]['task_id'];
    $taskInfo = Task::getAllTasksSettings(PROJECT_ID, $taskId);

    if (!$Proj->longitudinal) {
        $taskSchedules = Task::getTaskSchedulesByEventId($taskId, $Proj->firstEventId);
        unset($taskSchedules['ts_id'], $taskSchedules['task_id'], $taskSchedules['event_id']);
        $taskInfo = array_merge($taskInfo, $taskSchedules);
    }

    unset($taskInfo['form_name'], $taskInfo['enabled_for_mycap']);
    // Column headers for the exported CSV file, reflects the columns in the redcap_mycap_tasks database table, excluding those that use unique keys

    $fp = fopen('php://memory', "x+");
    // Add headers
    fputcsv($fp, array_keys($taskInfo), ',', '"', '');
    fputcsv($fp, $taskInfo, ',', '"', '');

    // Open file for reading and output to user
    fseek($fp, 0);
    $taskSettings = stream_get_contents($fp);
    // Replace CR+LF with just LF for better compatibility with Excel on Macs
    $surveySettings = str_replace("\r\n", "\n", $taskSettings);
    $zip->addFromString("mycap_task_settings.csv", $taskSettings);
}

// If the instrument contains stop actions or the inline attribute for video links, add them as separate CSV file
$sql = "select field_name, stop_actions, video_url, video_display_inline from $metadataTable 
        where project_id = $project_id and (stop_actions is not null or video_url is not null)
        order by field_order";
$dd_extra = queryToCsv($sql);
if (!empty(csvToArray($dd_extra))) {
    $zip->addFromString("instrument_extra.csv", $dd_extra);
}

// Done adding to zip file
$zip->close();
// Logging

$log_descrip = "Download instrument ZIP file";
Logging::logEvent("", "redcap_metadata", "MANAGE", $_GET['page'], "form_name = ".$_GET['page'], $log_descrip);
// Download file and then delete it from the server
header('Pragma: anytextexeptno-cache', true);
header('Content-Type: application/octet-stream"');
header('Content-Disposition: attachment; filename="'.$download_filename.'"');
header('Content-Length: ' . filesize($target_zip));
ob_start();ob_end_flush();
readfile_chunked($target_zip);
unlink($target_zip);