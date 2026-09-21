<?php

// Check if coming from survey or authenticated form
if (isset($_GET['s']) && !empty($_GET['s']))
{
	// Call config_functions before config file in this case since we need some setup before calling config
	require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
	// Validate and clean the survey hash, while also returning if a legacy hash
	$hash = $_GET['s'] = Survey::checkSurveyHash();
	// Set all survey attributes as global variables
	Survey::setSurveyVals($hash);
	// Now set $_GET['pid'] before calling config
	$_GET['pid'] = $project_id;
	// Set flag for no authentication for survey pages
    defined("NOAUTH") or define("NOAUTH", true);
} elseif (!isset($_GET['pid']) && isset($_GET['origin']) && $_GET['origin'] == 'messaging') {
	// If viewing an image in a User Messaging thread, which is not in a project, then bypass init project
	define("FORCE_INIT_GLOBAL", true);
}

if (defined("FORCE_INIT_GLOBAL")) {
	require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
	// Ensure the file attachment belongs to a thread that the current user has access to
	if (!Messenger::fileBelongsToUserThread($_GET['id'])) {
		exit($lang['global_01']);
	}
} else {
	require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
	$draft_preview_enabled = Design::isDraftPreview();
	$Proj_metadata = $draft_preview_enabled ? $Proj->metadata_temp : $Proj->metadata;
	$Proj_forms = $draft_preview_enabled ? $Proj->forms_temp : $Proj->forms;
}

// If ID is not in query_string, then return error
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) exit($lang['global_01']);

// Confirm the hash of the doc_id
if (!isset($_GET['doc_id_hash']) || (isset($_GET['doc_id_hash']) && $_GET['doc_id_hash'] != Files::docIdHash($_GET['id']))) {
	exit($lang['global_01']);
}

// Confirm the extra encoded hash of the doc_id, if applicable
$confirmedExtraHash = (isset($_GET['doc_id_hash']) && isset($_GET['doc_id_hash2']) && $_GET['doc_id_hash2'] == base64_encode(encrypt(Files::docIdHash($_GET['id']))));

// Parameters
$instance = (isset($_GET['instance']) && is_numeric($_GET['instance']) && $_GET['instance'] > 1) ? $_GET['instance'] : 1;
$version_log = "";
$records = isset($_GET['record']) ? array($_GET['record']) : null;
$record = $_GET['record'] ?? null;
$events = isset($_GET['event_id']) ? array($_GET['event_id']) : null;
$event_id = $_GET['event_id'] ?? null;

if (!defined("FORCE_INIT_GLOBAL") && !$confirmedExtraHash)
{
	// If downloading a file via public File Repository link
	if (isset($_GET['__file']))
	{
		// Don't do any more validation if checked __file param
		if (FileRepository::getFileByHash($_GET['__file']) === false) {
			exit($lang['global_01']);
		}
	}
	// If downloading an email attachment on the secure email page
	elseif (isset($_GET['__email']))
	{
		// Don't do any more validation if checked __email param
		if (Message::getEmailContentByHash($_GET['__email']) === false) {
			exit($lang['global_01']);
		}
	}
    // If downloading a serialized data zip file for MyCap via smart variable [mycap-task-serializedresult]
    elseif (isset($_GET['serializedresult']))
    {
        // Don't do any more validation if checked serializedresult param
        if ($_GET['serializedresult'] != 1) {
            exit("{$lang['global_01']}!");
        }
    }
	// If downloading an attachment for a project, then verify
	elseif (isset($_GET['type']) && $_GET['type'] == "attachment" && !defined("NOAUTH"))
	{
		if (DataEntry::isRecordValue()) {
			// If the file is actually a data value on a record, then it's not an attachment, so stop here
			die("<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['file_download_03']}");
		}
	}
    // Older version of a file via File Version History in Data History popup
    elseif (isset($_GET['doc_version']) && is_numeric($_GET['doc_version']) && isset($_GET['doc_version_hash']))
    {
        if ($_GET['doc_version_hash'] != Files::docIdHash($_GET['id']."v".$_GET['doc_version'])) {
            exit($lang['global_01']);
        }
        $version_log = " (V{$_GET['doc_version']})";
    }
	// Surveys only: Perform double checking to make sure the survey participant has rights to this file
	elseif (isset($_GET['s']) && !empty($_GET['s']))
	{
		// If this is a descriptive field attachment, remove some attributes no longer needed
		if (isset($_GET['type']) && $_GET['type'] == "attachment") {
			unset($_GET['record'], $_GET['event_id']);
		}
		// If survey is complete and not returnable, then give specific error message that the file cannot be downloaded
		Survey::getFollowupSurveyParticipantIdHash($Proj_forms[$_GET['page']]['survey_id'], $_GET['record'], $_GET['event_id'], false, $_GET['instance']); // Add placeholder to db table if needed
		$sql = "select r.completion_time, s.save_and_return, s.edit_completed_response
				from redcap_surveys s, redcap_surveys_participants p, redcap_surveys_response r 
				where s.survey_id = p.survey_id and p.participant_id = r.participant_id and p.survey_id = '" . db_escape($Proj_forms[$_GET['page']]['survey_id']) . "' 
				and p.event_id = '" . db_escape($_GET['event_id']) . "' and r.record = '" . db_escape($_GET['record']) . "' and r.instance = '" . db_escape($_GET['instance']) . "'
				order by r.completion_time desc limit 1";
		$q = db_query($sql);
		$responseAttr = db_fetch_assoc($q);
		$surveyCompleted = ($responseAttr['completion_time'] != '');
		$allowedToReturnToCompletedResponse = ($responseAttr['save_and_return'] == '1' && $responseAttr['edit_completed_response'] == '1');
        // If URL has __response_hash__, then verify it
        $passedRespHashTest = false;
        if (isset($_GET['__response_hash__']) && !empty($_GET['__response_hash__'])) {
            $participant_id = Survey::getParticipantIdFromRecordSurveyEvent($_GET['record'], $Proj->forms[$_GET['page']]['survey_id'], $_GET['event_id'], $_GET['instance']);
            $response_id = Survey::decryptResponseHash($_GET['__response_hash__'], $participant_id);
            if (isinteger($response_id)) {
                // Confirm record name as secondary check
                $sql = "select r.record from redcap_surveys_participants p, redcap_surveys_response r
                        where r.participant_id = p.participant_id and r.response_id = ?";
                $q = db_query($sql, $response_id);
                $passedRespHashTest = (db_result($q) == $_GET['record']);
            }
        }
        // Otherwise, this is a link inside a survey being taken righ tnow
		if (!$passedRespHashTest && $surveyCompleted && !$allowedToReturnToCompletedResponse) {
			// Error message
			$HtmlPage = new HtmlPage();
			$HtmlPage->PrintHeaderExt();
			print "<b>{$lang['global_03']}{$lang['colon']}</b> {$lang['survey_1337']}";
			$HtmlPage->PrintFooterExt();
			exit;
		}
		// Make sure the survey participant has rights to this file
		DataEntry::checkSurveyFileRights();
	}
	// Non-surveys: Check form-level rights and DAGs to ensure user has access to this file
	elseif (!isset($_GET['s']) || empty($_GET['s']))
	{
		DataEntry::checkFormFileRights();
	}
}

//Download file from the "edocs" web server directory
$sql = "select * from redcap_edocs_metadata where doc_id = '" . db_escape($_GET['id']). "' and (delete_date is null or (delete_date is not null and delete_date > '".NOW."'))"; // Allow future delete dates
if (defined("PROJECT_ID")) $sql .= " and project_id = " . PROJECT_ID;
$q = db_query($sql);
if (!db_num_rows($q)) {
	die("<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['file_download_03']}");
}
$this_file = db_fetch_assoc($q);

// If downloading a file via public File Repository link
if (isset($_GET['__file'])) {
	// Log it
    $docName = Files::getEdocName($_GET['id']);
    // Get the source/referrer of the request
    $source = $_SERVER['HTTP_REFERER'];
    // If file was uploaded as an attachment in the rich text editor, use different logging text since it's not actually displayed in the File Repository anywhere
    $sql = "select 1 from redcap_edocs_metadata m, redcap_docs_to_edocs e, redcap_docs_attachments a
            where m.doc_id = ? and e.doc_id = m.doc_id and e.docs_id = a.docs_id";
    $q = db_query($sql, $_GET['id']);
    if (db_num_rows($q)) {
        Logging::logEvent($sql,"redcap_docs","MANAGE",$_GET['id'],"File name: $docName, Download origin: $source","Download file attachment", "", "[non-user]");
    } else {
        Logging::logEvent($sql,"redcap_docs","MANAGE",$_GET['id'],"File name: $docName, Download origin: $source","Download file attachment", "", "[non-user]");
    }
}


if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
	// LOCAL
	//Use custom edocs folder (set in Control Center)
	if (!is_dir(EDOC_PATH))
	{
		include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
		print  "<div class='red'>
					<b>{$lang['global_01']}!</b><br>{$lang['file_download_04']} <b>".EDOC_PATH."</b> {$lang['file_download_05']} ";
		if ($super_user) print "{$lang['file_download_06']} <a href='".APP_PATH_WEBROOT."ControlCenter/modules_settings.php' style='text-decoration:underline;font-family:verdana;font-weight:bold;'>{$lang['global_07']}</a>.";
		print  "</div>";
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

	//Download from "edocs" folder (use default or custom path for storage)
	$local_file = EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $this_file['stored_name'];
	if (file_exists($local_file) && is_file($local_file))
	{
		header('Pragma: anytextexeptno-cache', true);
        // Set CSP header (very important to prevent reflected XSS)
        header("Content-Security-Policy: script-src 'none'");
		if (isset($_GET['stream']) && strpos($this_file['mime_type'], 'audio') === 0) { // Must be audio file
			// Stream the file (e.g. audio)
			header('Content-Type: '.mime_content_type($local_file));
			header('Content-Disposition: inline; filename="'.$this_file['doc_name'].'"');
			header('Content-Length: ' . $this_file['doc_size']);
			header("Content-Transfer-Encoding: binary");
			header('Accept-Ranges: bytes');
			header('Connection: Keep-Alive');
			header('X-Pad: avoid browser bug');
			header('Content-Range: bytes 0-'.($this_file['doc_size']-1).'/'.$this_file['doc_size']);
		} else {
			// Download
			header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
			header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
		}
		ob_start();ob_end_flush();
		readfile_chunked($local_file);
	}
	else
	{
	    die('<b>'.$lang['global_01'].$lang['colon'].'</b> '.$lang['file_download_08'].' <b>"'.$local_file.
	    	'"</b> ("'.$this_file['doc_name'].'") '.$lang['file_download_09'].'!');
	}

} elseif ($edoc_storage_option == '2') {
	// S3
	try {
		$s3 = Files::s3client();
		$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$this_file['stored_name'], 'SaveAs'=>APP_PATH_TEMP . $this_file['stored_name']));
		header('Pragma: anytextexeptno-cache', true);
        // Set CSP header (very important to prevent reflected XSS)
        header("Content-Security-Policy: script-src 'none'");
		if (isset($_GET['stream'])) {
			// Stream the file (e.g. audio)
			header('Content-Type: '.mime_content_type(APP_PATH_TEMP . $this_file['stored_name']));
			header('Content-Disposition: inline; filename="'.$this_file['doc_name'].'"');
			header('Content-Length: ' . $this_file['doc_size']);
			header("Content-Transfer-Encoding: binary");
			header('Accept-Ranges: bytes');
			header('Connection: Keep-Alive');
			header('X-Pad: avoid browser bug');
			header('Content-Range: bytes 0-'.($this_file['doc_size']-1).'/'.$this_file['doc_size']);
		} else {
			// Download
			header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
			header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
		}
		ob_start();ob_end_flush();
		readfile_chunked(APP_PATH_TEMP . $this_file['stored_name']);
		// Now remove file from temp directory
		unlink(APP_PATH_TEMP . $this_file['stored_name']);
	} catch (Aws\S3\Exception\S3Exception $e) {
	}

} elseif ($edoc_storage_option == '4') {
	// Azure
    $blobClient = new AzureBlob();
    $file_data = $blobClient->getBlob($this_file['stored_name']);
    file_put_contents(APP_PATH_TEMP . $this_file['stored_name'], $file_data);
	header('Pragma: anytextexeptno-cache', true);
    // Set CSP header (very important to prevent reflected XSS)
    header("Content-Security-Policy: script-src 'none'");
	if (isset($_GET['stream'])) {
		// Stream the file (e.g. audio)
		header('Content-Type: '.mime_content_type(APP_PATH_TEMP . $this_file['stored_name']));
		header('Content-Disposition: inline; filename="'.$this_file['doc_name'].'"');
		header('Content-Length: ' . $this_file['doc_size']);
		header("Content-Transfer-Encoding: binary");
		header('Accept-Ranges: bytes');
		header('Connection: Keep-Alive');
		header('X-Pad: avoid browser bug');
		header('Content-Range: bytes 0-'.($this_file['doc_size']-1).'/'.$this_file['doc_size']);
	} else {
		// Download
		header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
		header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
	}
	ob_start();ob_end_flush();
	readfile_chunked(APP_PATH_TEMP . $this_file['stored_name']);
	// Now remove file from temp directory
	unlink(APP_PATH_TEMP . $this_file['stored_name']);

} elseif ($edoc_storage_option == '5') {
    $googleClient = Files::googleCloudStorageClient();
    $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);
    $googleClient->registerStreamWrapper();

    // PREFIX OF PROJECT_ID IS NOT REQUIRED AS PROJECT_ID HAS BEEN ADDED TO EDOC PATH ON SAVE
	// // if pid sub-folder is enabled then upload the file under pid folder
    // if($GLOBALS['google_cloud_storage_api_use_project_subfolder']){
    //     $this_file['stored_name'] = $project_id . '/' . $this_file['stored_name'];
    // }

    header('Pragma: anytextexeptno-cache', true);
    // Set CSP header (very important to prevent reflected XSS)
    header("Content-Security-Policy: script-src 'none'");
    if (isset($_GET['stream'])) {
        // Stream the file (e.g. audio)
        header('Content-Type: '.mime_content_type(APP_PATH_TEMP . $this_file['stored_name']));
        header('Content-Disposition: inline; filename="'.$this_file['doc_name'].'"');
        header('Content-Length: ' . $this_file['doc_size']);
        header("Content-Transfer-Encoding: binary");
        header('Accept-Ranges: bytes');
        header('Connection: Keep-Alive');
        header('X-Pad: avoid browser bug');
        header('Content-Range: bytes 0-'.($this_file['doc_size']-1).'/'.$this_file['doc_size']);
    } else {
        // Download
        header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
        header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
    }
    ob_start();ob_end_flush();
    readfile_chunked('gs://'.$GLOBALS['google_cloud_storage_api_bucket_name'].'/' . $this_file['stored_name']);
} else {

	//  WebDAV
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
	$http_status = $wdc->get($webdav_path . $this_file['stored_name'], $contents); //$contents is produced by webdav class
	$wdc->close();

    // Store the file in temp directory
    file_put_contents(APP_PATH_TEMP . $this_file['stored_name'], $contents);
    unset($contents);

	//Send file headers and contents
	header('Pragma: anytextexeptno-cache', true);
    // Set CSP header (very important to prevent reflected XSS)
    header("Content-Security-Policy: script-src 'none'");
	if (isset($_GET['stream'])) {
		// Stream the file (e.g. audio)
		header('Content-Type: '.$this_file['mime_type']);
		header('Content-Disposition: inline; filename="'.$this_file['doc_name'].'"');
		header('Content-Length: ' . $this_file['doc_size']);
		header("Content-Transfer-Encoding: binary");
		header('Accept-Ranges: bytes');
		header('Connection: Keep-Alive');
		header('X-Pad: avoid browser bug');
		header('Content-Range: bytes 0-'.($this_file['doc_size']-1).'/'.$this_file['doc_size']);
	} else {
		// Download
		header('Content-Type: '.$this_file['mime_type'].'; name="'.$this_file['doc_name'].'"');
		header('Content-Disposition: attachment; filename="'.$this_file['doc_name'].'"');
	}
    ob_start();ob_end_flush();
    readfile_chunked(APP_PATH_TEMP . $this_file['stored_name']);
    // Now remove file from temp directory
    unlink(APP_PATH_TEMP . $this_file['stored_name']);
}

// Update Download Count (if applicable) - Update field value where action tag is @DOWNLOAD-COUNT($_GET['field_name'])
$fields = Form::getDownloadCountTriggerFields($project_id ?? null, $_GET['field_name'] ?? null);
$incrementDownloadCount = (!empty($fields) && isset($_GET['hidden_edit']) && $_GET['hidden_edit'] == '1');
if ($incrementDownloadCount)
{
	$fields[] = $Proj->table_pk; // Add record id to return event, instance, etc.
	$fields[] = $_GET['field_name']; // Add record id to return event, instance, etc.
	$instance = isset($_GET['instance']) ? $_GET['instance'] : null;
	$data = json_decode(REDCap::getData($project_id, 'json', $records, $fields, $events), true);
	foreach ($data as $attr) {
		if ($attr['redcap_repeat_instance'] == $instance || $attr['redcap_repeat_instance'] == "") {
			$newCount = 0;
			foreach ($fields as $field) {
				if ($field != $Proj->table_pk && $field != $_GET['field_name']) { // Skip when its primary key
                    $newCount = (($attr[$field] == '' || !isinteger($attr[$field])) ? 0 : $attr[$field]) + 1;
					// Save new download count value
					$record_data = [[$Proj->table_pk => $record, $field => $newCount]];
					if ($Proj->longitudinal) $record_data[0]['redcap_event_name'] = $attr['redcap_event_name'];
					$hasRepeatingInstances = ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingForm($event_id, $attr['redcap_repeat_instrument']));
					if ($hasRepeatingInstances) {
						$record_data[0]['redcap_repeat_instrument'] = $attr['redcap_repeat_instrument'];
						$record_data[0]['redcap_repeat_instance'] = $attr['redcap_repeat_instance'];
					}
					$params = ['project_id'=>$_GET['pid'], 'dataFormat'=>'json', 'data'=>json_encode($record_data), 'loggingUser'=>(isset($_GET['__report']) ? "[public report]" : USERID)];
					$response = REDCap::saveData($params);
				}
			}
		}
	}
}

// Do logging
if (isset($_GET['type']) && $_GET['type'] == "attachment")
{
	// When downloading field image/file attachments
	defined("NOAUTH") or Logging::logEvent($sql,"redcap_edocs_metadata","MANAGE",$_GET['record'],$_GET['field_name'],"Download image/file attachment");
}
else
{
	// When downloading edoc files on a data entry form/survey
	defined("NOAUTH") or Logging::logEvent($sql,"redcap_edocs_metadata","MANAGE",(isset($_GET['record']) ? $_GET['record'] : ""),(isset($_GET['field_name']) ? $_GET['field_name'] : "").$version_log,"Download uploaded document",
													"", "", "", true, null, $instance);
}
