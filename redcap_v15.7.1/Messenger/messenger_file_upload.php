<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

if($_POST['file_name'] == ''){
	$result = 0;
	print "<script language='javascript' type='text/javascript'>
	window.parent.window.messagingUploadFileHandler($result);
	</script>";
	exit;
}
$msg_body = $_POST['file_name'];
$thread_id = $_POST['thread_id'];
$file_type = $_POST['file_type'];
$file_preview = $_POST['file_preview'];
$limit = $_POST['limit'];
$message = $_POST['message'];
$blob = $_POST['blob'];
$important = (isset($_POST['important']) && $_POST['important'] == '1') ? '1' : '0';

// Ensure user belongs to this thread
if (!Messenger::isConversationMember($thread_id, UI_ID)) {
    print "<script language='javascript' type='text/javascript'>
            window.parent.window.alert('".RCView::tt_js('global_01')."');
            </script>";
    exit;
}

// Upload the file and return the doc_id from the edocs table
$doc_id = $doc_size = 0;
$doc_name = "";
if (isset($_FILES['myfile'])) 
{
	$doc_size = $_FILES['myfile']['size'];
	if (($doc_size/1024/1024) > maxUploadSizeEdoc() || $_FILES['myfile']['error'] != UPLOAD_ERR_OK){
		$result = 2;
		print "<script language='javascript' type='text/javascript'>
		window.parent.window.messagingUploadFileTooBig($result);
		</script>";
		exit;
	}else{
		// Obtain the image height (if an image) so we can store it in the table
		list ($img_height, $img_width) = Files::getImgWidthHeight($_FILES['myfile']['tmp_name']);
		// Add the file to edocs table
		$doc_id = Files::uploadFile($_FILES['myfile']);
        if ($doc_id == 0) {
		    print "<script language='javascript' type='text/javascript'>
                    window.parent.window.messagingUploadFileHandler(0);
                    window.parent.window.alert('".RCView::tt_js('docs_1136')."');
                    </script>";
            exit;
        }
		$doc_hash = Files::docIdHash($doc_id);
		$doc_name = strip_tags(str_replace("'", "", html_entity_decode(stripslashes($_FILES['myfile']['name']), ENT_QUOTES)));
		$stored_url = Messenger::generateAttachmentData($file_preview,$doc_name,$img_height);
		$upload_msg = $message;
		$action = 'post';
		$message = Messenger::createMsgBodyObj($message,USERID,$action,'',$important);
		$sql = "insert into redcap_messages (thread_id, sent_time, author_user_id, message_body, attachment_doc_id, stored_url) values
		('".db_escape($thread_id)."', '".NOW."', '".db_escape(UI_ID)."',
		'".db_escape($message)."', '".db_escape($doc_id)."', '".db_escape($stored_url)."')";
		$q = db_query($sql);
		$message_id = db_insert_id();

		//insert unread messages for all thread participants
		Messenger::createNewMessageNotifications($message_id,$thread_id,$important);

		//get data for refreshMessages
		$channels = Messenger::getChannelsByUIID(UI_ID);
		$data = Messenger::getChannelMessagesAndMembers($thread_id,$limit);
		$messages = $data['messages'];
		$members = $data['members'];
		$is_dev = isDev();
		$is_dev = json_encode_rc($is_dev);
		$messages = json_encode_rc($messages);
		$members = json_encode_rc($members);
		$channels = json_encode_rc($channels);

	}
}

// Check if file is larger than max file upload limit
if (($doc_size/1024/1024) > maxUploadSizeEdoc() || (!isset($_POST['myfile_base64']) && $_FILES['myfile']['error'] != UPLOAD_ERR_OK))
{
	// Delete temp file
	unlink($_FILES['myfile']['tmp_name']);
	// Remove file from db table
	if ($doc_id > 0) db_query("update redcap_edocs_metadata set delete_date = '".NOW."' where doc_id = $doc_id");
	// Give error response
	print "<script language='javascript' type='text/javascript'>
            window.parent.window.messagingUploadFileHandler($result);
			window.parent.window.alert('ERROR: CANNOT UPLOAD FILE!\\n\\nThe uploaded file is ".round_up($doc_size/1024/1024)." MB in size, '+
									'thus exceeding the maximum file size limit of ".maxUploadSizeEdoc()." MB.');
		   </script>";
	exit;
}

//Update tables if file was successfully uploaded
if ($doc_id != 0) {
	$result = 1;
}else{
	$channels = Messenger::getChannelsByUIID(UI_ID);
	$data = Messenger::getChannelMessagesAndMembers($thread_id,$limit);
	$messages = $data['messages'];
	$members = $data['members'];
	$is_dev = isDev();
	$is_dev = json_encode_rc($is_dev);
	$messages = json_encode_rc($messages);
	$members = json_encode_rc($members);
	$channels = json_encode_rc($channels);
	$result = 0;
}

// Ouput javascript
print "<script language='javascript' type='text/javascript'>
		window.parent.window.messagingUploadFileHandler($result,$messages,$members,$is_dev,$channels);
	   </script>";
