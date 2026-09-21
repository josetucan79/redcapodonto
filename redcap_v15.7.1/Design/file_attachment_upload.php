<?php


// Only accept Post submission
if ($_SERVER['REQUEST_METHOD'] != 'POST') exit;

// Check if coming from survey or authenticated form
if (!isset($_GET['pid']) && isset($_GET['s']) && !empty($_GET['s']))
{
    require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
    // Validate and clean the survey hash, while also returning if a legacy hash
    $hash = $_GET['s'] = Survey::checkSurveyHash();
    // Set all survey attributes as global variables
    Survey::setSurveyVals($hash);
    // Now set $_GET['pid'] before calling config
    $_GET['pid'] = $project_id;
    // Set flag for no authentication for survey pages
    define("NOAUTH", true);
}

$embed_image = (isset($_GET['embed_image']) && $_GET['embed_image']);
$embed_attachment = (isset($_GET['embed_attachment']) && $_GET['embed_attachment']);

// Prevent public surveys from uploading images here (because rich text editor images and attachments are not allowed to be uploaded there)
// and also prevent any file attachments from being uploaded via rich text editor on any surveys. This is already preventing via client side.
if (isset($_GET['s']) && !empty($_GET['s']) && ($embed_attachment || ($_GET['s'] == strtoupper($_GET['s'])))) {
    exit;
}

if (isset($_GET['pid'])) {
    // Call config file
    require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
} elseif (!isset($_GET['pid'])) {
    require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
    // If there's no PID, then the user must be an administrator to upload the file
    if (!ACCESS_CONTROL_CENTER) {
        exit("{$lang['global_01']}{$lang['exclamationpoint']}");
    }
} else {
    require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';
}

$doc_name = str_replace("'", "", html_entity_decode(stripslashes($_FILES['file']['name']), ENT_QUOTES));
$doc_size = $_FILES['file']['size'];
$is_audio_file = (stripos($_FILES['file']['type'], "audio/") === 0) ? '2' : '0';

// Upload the file and return the doc_id from the edocs table
$doc_id = Files::uploadFile($_FILES['file']);


// Check if file is larger than max file upload limit
if ($doc_id < 1 || ($doc_size/1024/1024) > maxUploadSizeAttachment() || $_FILES['file']['error'] != UPLOAD_ERR_OK)
{
	// Delete temp file
	unlink($_FILES['file']['tmp_name']);
	// Set error message
	$msg = "ERROR: CANNOT UPLOAD FILE!";
	if ($doc_id > 0) {
		// If file was too large
		$msg .= "\\n\\nThe uploaded file is ".round_up($doc_size/1024/1024)." MB in size, thus exceeding the maximum file size limit of ".maxUploadSizeAttachment()." MB.";
	}
    if ($embed_image || $embed_attachment) exit($msg);
	// Give error response
	?>
	<script language="javascript" type="text/javascript">
	window.parent.window.document.getElementById('div_attach_doc_in_progress').style.display = 'none';
	window.parent.window.document.getElementById('div_attach_doc_fail').style.display = 'block';
	window.parent.window.alert('<?php echo $msg ?>');
	</script>
	<?php
	exit;
}


// Generate doc id hash to return to rich text editor
if ($embed_image)
{
    // Do logging of file upload
    Logging::logEvent("","redcap_edocs_metadata","doc_upload",$doc_id,"doc_id = $doc_id", "Upload document as embedded image in rich text editor");
    // Return the URL to be used by the rich text editor
    if (PAGE == 'surveys/index.php' && isset($_GET['s'])) {
        print APP_PATH_SURVEY_FULL . "index.php?__passthru=".urlencode("DataEntry/image_view.php") . "&pid=$project_id&s={$_GET['s']}&id=$doc_id&doc_id_hash=" . Files::docIdHash($doc_id);
    } else {
        print APP_PATH_WEBROOT_FULL . "redcap_v" . REDCAP_VERSION . "/DataEntry/image_view.php?" . (isset($project_id) ? "pid=$project_id&" : "") . "id=$doc_id&doc_id_hash=" . Files::docIdHash($doc_id);
    }
    exit;
}

// Generate doc id hash to return to rich text editor
if ($embed_attachment)
{
    // Do logging of file upload
    Logging::logEvent("","redcap_edocs_metadata","doc_upload",$doc_id,"doc_id = $doc_id", "Upload document as embedded attachment in rich text editor");
    // Add comment describing the source of the file upload (based on HTTP referrer)
    $source = $comment = str_replace(APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/", "", $_SERVER['HTTP_REFERER']);
    if (strpos($source, "ProjectDashController:") !== false) {
        $comment = "Project Dashboard";
    } elseif (strpos($source, "AlertsController:") !== false) {
        $comment = "Alerts & Notifications";
    } elseif (strpos($source, "Design/online_designer.php") !== false && strpos($source, "&page=") !== false) {
        $comment = "Online Designer (Field Label)";
    } elseif (strpos($source, "Design/online_designer.php") !== false) {
        $comment = "Automated Survey Invitation or Survey Queue custom text";
    } elseif (strpos($source, "MultiLanguageController:") !== false) {
        $comment = "Multi-Language Management";
    } elseif (strpos($source, "DataExport/index.php") !== false) {
        $comment = "Create Report";
    } elseif (strpos($source, "Surveys/edit_info.php") !== false || strpos($source, "Surveys/create_survey.php") !== false) {
        $comment = "Survey Settings";
    } elseif (strpos($source, "Surveys/invite_participants.php") !== false) {
        $comment = "Survey Participants";
    }
    // Return the URL to be used by the rich text editor - Get new public link
    REDCap::addFileToRepository($doc_id, PROJECT_ID, $comment, true);
    $docPublicLink = FileRepository::getPublicLink(FileRepository::getDocsIdFromDocId($doc_id))."&download=1";
    exit($docPublicLink);
}


// Do logging of file upload
Logging::logEvent("","redcap_edocs_metadata","doc_upload",$doc_id,"doc_id = $doc_id","Upload document for image/file attachment field");

// Give response using javascript
?>
<script language="javascript" type="text/javascript">
try {
	window.parent.window.document.getElementById('video_url').value = '';
	window.parent.window.document.getElementById('video_url').disabled = true;
	window.parent.window.document.getElementById('video_display_inline0').disabled = true;
	window.parent.window.document.getElementById('video_display_inline1').disabled = true;
} catch(e) { }
window.parent.window.document.getElementById('edoc_id').value = '<?php echo $doc_id ?>';
window.parent.window.document.getElementById('edoc_id_hash').value = '<?php echo Files::docIdHash($doc_id) ?>';
window.parent.window.document.getElementById('div_attach_doc_in_progress').style.display = 'none';
window.parent.window.document.getElementById('div_attach_doc_success').style.display = 'block';
var filename = window.parent.window.document.getElementById('attach_download_link').innerHTML = '<?php echo js_escape(str_replace("'", "", $_FILES['file']['name'])) ?>';
window.parent.window.document.getElementById('div_attach_upload_link').style.display = 'none';
window.parent.window.document.getElementById('div_attach_download_link').style.display = 'block';
window.parent.window.enableAttachImgOption(filename,<?php echo $is_audio_file ?>);
setTimeout(function(){
    window.parent.window.$(function(){
        window.parent.window.$('#attachment-popup').dialog('close');
    });
},2500);
</script>
