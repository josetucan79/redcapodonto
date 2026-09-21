<?php

// For some strange reason, the class JmesPath\Env does not exist (even though its included in composer.json), so include necessary classes here
if (!class_exists('JmesPath\Env')) {
	include_once APP_PATH_DOCROOT . 'Libraries/vendor/mtdowling/jmespath.php/src/Env.php';
	include_once APP_PATH_DOCROOT . 'Libraries/vendor/mtdowling/jmespath.php/src/AstRuntime.php';
	include_once APP_PATH_DOCROOT . 'Libraries/vendor/mtdowling/jmespath.php/src/FnDispatcher.php';
	include_once APP_PATH_DOCROOT . 'Libraries/vendor/mtdowling/jmespath.php/src/TreeInterpreter.php';
	include_once APP_PATH_DOCROOT . 'Libraries/vendor/mtdowling/jmespath.php/src/Parser.php';
	include_once APP_PATH_DOCROOT . 'Libraries/vendor/mtdowling/jmespath.php/src/Lexer.php';
}

use Google\Cloud\Storage\StorageClient;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\FileStorageNameVisitor;

/**
 * FILES Class
 * Contains methods used with regard to uploaded files
 */
class Files
{
    // When to permanently remove deleted edocs from the server
    const EDOCS_DELETION_DAYS_OLD = 30;

	/**
	 * DETERMINE IF WE'RE ON A VERSION OF PHP THAT SUPPORTS ZIPARCHIVE (PHP 5.2.0)
	 * Returns boolean.
	 */
	public static function hasZipArchive()
	{
		return (class_exists('ZipArchive'));
	}


	/**
	 * DETERMINE IF PROJECT HAS ANY "FILE UPLOAD" FIELDS IN METADATA
	 * Returns boolean.
	 */
	public static function hasFileUploadFields()
	{
		global $Proj;
		return $Proj->hasFileUploadFields;
	}


	/**
	 * CALCULATE SERVER SPACE USAGE OF FILES UPLOADED
	 * Returns usage in bytes
	 */
	public static function getEdocSpaceUsage()
	{
		// Default
		$total_edoc_space_used = 0;
		// Get space used by edoc file uploading on data entry forms. Count using table values (since we cannot easily call external server itself).
		$sql = "select if(sum(doc_size) is null, 0, sum(doc_size)) from redcap_edocs_metadata where date_deleted_server is null";
		$total_edoc_space_used += db_result(db_query($sql), 0);
		// Additionally, get space used by send-it files (for location=1 only, because loc=3 is edocs duplication). Count using table values (since we cannot easily call external server itself).
		$sql = "select if(sum(doc_size) is null, 0, sum(doc_size)) from redcap_sendit_docs
				where location = 1 and expire_date > '".NOW."' and date_deleted is null";
		$total_edoc_space_used += db_result(db_query($sql), 0);
		// Return total
		return $total_edoc_space_used;
	}


    /**
     * RETURN THE PROJECT_ID OF AN EDOC FILE
     */
    public static function getEdocProjectId($edoc_id)
    {
        if (!is_numeric($edoc_id)) return null;
        // Download file from the "edocs" web server directory
        $sql = "select project_id from redcap_edocs_metadata where doc_id = ?";
        $q = db_query($sql, $edoc_id);
        if (!db_num_rows($q)) return null;
        $this_file = db_fetch_assoc($q);
        return $this_file['project_id'];
    }


    /**
     * RETURN THE ORIGINAL FILENAME OF AN EDOC FILE FROM THE EDOC_ID
     */
    public static function getEdocName($edoc_id, $returnStoredName=false, $project_id=null)
    {
        if (!is_numeric($edoc_id)) return false;
        // Return user-facing filename or the stored filename of the file on the server?
		$col = $returnStoredName ? 'stored_name' : 'doc_name';
        // Download file from the "edocs" web server directory
        $sql = "select $col from redcap_edocs_metadata where doc_id = " . db_escape($edoc_id);
		if (isinteger($project_id)) {
			$sql .= " and project_id = $project_id";
		}
        $q = db_query($sql);
        if (!db_num_rows($q)) return false;
        $this_file = db_fetch_assoc($q);
        return $this_file[$col];
    }

	/**
	 * RETURN THE ORIGINAL FILENAME OF AN EDOC FILE AND THE FILE SIZE (IN BYTES) FROM THE EDOC_ID
	 */
	public static function getEdocNameAndSize($edoc_id)
	{
		if (!is_numeric($edoc_id)) return false;
		$sql = "select doc_name, doc_size from redcap_edocs_metadata where doc_id = " . db_escape($edoc_id);
		$q = db_query($sql);
		if (!db_num_rows($q)) return false;
		$this_file = db_fetch_assoc($q);
		return array($this_file['doc_name'], $this_file['doc_size']);
	}

	/**
	 * Gets edoc info (all columns of the redcap_edocs_metadata table + doc_name_truncated) for the given edoc id
	 * @param int $edoc_id 
	 * @param mixed $project_id Project id or null (non-project scope) or false (default, retrieves by edoc id only)
	 * @param bool $include_deleted Return info for deleted files; default = false
	 * @param int $char_limit Number of characters to limited the truncated filename to (default: 34)
	 * @return array|null Associative array or null if there is no entry for the given edoc id
	 */
	public static function getEdocInfo($edoc_id, $project_id = false, $include_deleted = false, $char_limit = 34) {
		if (!isinteger($edoc_id)) return null;
		$pid_clause = "";
		if ($project_id === null) {
			$pid_clause = "AND ISNULL(`project_id`)";
		}
		else if (isinteger($project_id)) {
			$pid_clause = "AND `project_id` = $project_id";
		}
		$deleted_clause = $include_deleted ? "" : "AND ISNULL(`delete_date`)";
		$sql = "SELECT * 
				FROM redcap_edocs_metadata 
				WHERE `doc_id` = $edoc_id $pid_clause $deleted_clause";
		$q = db_query($sql);
		if (db_num_rows($q) < 1) {
			return null;
		}
		$edoc_info = db_fetch_assoc($q);
		$edoc_info["doc_name_truncated"] = self::truncateFileName($edoc_info["doc_name"], $char_limit);
		return $edoc_info;
	}


    /**
     * RETURN FALSE IF THE FILE HAS BEEN DELETED BY USER, AND IF TRUE THEN RETURN TIME OF DELETION
     */
    public static function wasEdocDeleted($edoc_id)
    {
        if (!is_numeric($edoc_id)) return false;
        $sql = "select delete_date from redcap_edocs_metadata where doc_id = " . db_escape($edoc_id);
        $q = db_query($sql);
        if (db_num_rows($q) == 0) return false;
        $delete_date = db_result($q, 0);
        return ($delete_date == '') ? false : $delete_date;
    }


	/**
	 * RETURN THE CONTENTS AS A STRING OF AN EDOC FILE FROM EDOC STORAGE LOCATION
	 * Returns array of "mime_type" (string), "doc_name" (string), and "contents" (string) or FALSE if failed
	 */
	public static function getEdocContentsAttributes($edoc_id)
	{
		global $edoc_storage_option;

		if (!is_numeric($edoc_id)) return false;

		// Download file from the "edocs" web server directory
		$sql = "select * from redcap_edocs_metadata where doc_id = ".db_escape($edoc_id);
		$q = db_query($sql);
		if (!db_num_rows($q)) return false;
		$this_file = db_fetch_assoc($q);
        $project_id = $this_file['project_id'];

		if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
			//Download from "edocs" folder (use default or custom path for storage)
			$local_file = EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $this_file['stored_name'];
			if (file_exists($local_file) && is_file($local_file)) {
				return array($this_file['mime_type'], $this_file['doc_name'], file_get_contents($local_file));
			}
		} elseif ($edoc_storage_option == '2') {
			// S3
			try {
				$s3 = Files::s3client();
				$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$this_file['stored_name']));
				return array($this_file['mime_type'], $this_file['doc_name'], $object['Body']);
			} catch (Aws\S3\Exception\S3Exception $e) {
			}
		} elseif ($edoc_storage_option == '4') {
			// Azure
			$blobClient = new AzureBlob();
			$file_content = $blobClient->getBlob($this_file['stored_name']);
			return array($this_file['mime_type'], $this_file['doc_name'], $file_content);
		} elseif ($edoc_storage_option == '5') {
            // Google
            $googleClient = Files::googleCloudStorageClient();
            $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);
            $googleClient->registerStreamWrapper();


            $data = file_get_contents('gs://'.$GLOBALS['google_cloud_storage_api_bucket_name'].'/' . $this_file['stored_name']);

            return array($this_file['mime_type'], $this_file['doc_name'], $data);
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
				return false;
			}
			if (substr($webdav_path,-1) != '/') {
				$webdav_path .= '/';
			}
			$http_status = $wdc->get($webdav_path . $this_file['stored_name'], $contents); //$contents is produced by webdav class
			$wdc->close();
			return array($this_file['mime_type'], $this_file['doc_name'], $contents);
		}
	}


	/**
	 * COPIES A FILE FROM EDOC STORAGE LOCATION TO REDCAP'S TEMP DIRECTORY
	 * Returns full file path in temp directory, or FALSE if failed to move it to temp.
	 */
	public static function copyEdocToTemp($edoc_id, $prependHashToFilename=false, $prependTimestampToFilename=false)
	{
		global $edoc_storage_option;

		if (!isinteger($edoc_id)) return false;

		// Get filenames from edoc_id
		$q = db_query("select doc_name, stored_name, project_id from redcap_edocs_metadata where delete_date is null and doc_id = ".db_escape($edoc_id));
		if (!db_num_rows($q)) return false;
        $edoc_orig_filename = basename(db_result($q, 0, 'doc_name'));
		$stored_filename = db_result($q, 0, 'stored_name');
        $project_id = db_result($q, 0, 'project_id');

		// Set full file path in temp directory. Replace any spaces with underscores for compatibility.		
		$filename_tmp = APP_PATH_TEMP
					  . ($prependTimestampToFilename ? date('YmdHis') . "_" : '')
					  . ($prependHashToFilename ? substr(sha1(rand()), 0, 8) . '_' : '')
					  . str_replace(" ", "_", $edoc_orig_filename);

		if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
			// LOCAL
			if (file_put_contents($filename_tmp, file_get_contents(EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $stored_filename))) {
				return $filename_tmp;
			}
			return false;
		} elseif ($edoc_storage_option == '2') {
			// S3
			try {
				$s3 = Files::s3client();
				$object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$stored_filename, 'SaveAs'=>$filename_tmp));
				return $filename_tmp;
			} catch (Aws\S3\Exception\S3Exception $e) {
				return false;
			}
		} elseif ($edoc_storage_option == '4') {
			// Azure
			$blobClient = new AzureBlob();
			$data = $blobClient->getBlob($stored_filename);
			file_put_contents($filename_tmp, $data);
			return $filename_tmp;
		} elseif ($edoc_storage_option == '5') {
            // Google
            $googleClient = Files::googleCloudStorageClient();
            $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);
            $googleClient->registerStreamWrapper();


            $data = file_get_contents('gs://'.$GLOBALS['google_cloud_storage_api_bucket_name'].'/' . $stored_filename);

            file_put_contents($filename_tmp, $data);
            return $filename_tmp;
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
				sleep(1);
				return false;
			}
			if (substr($webdav_path,-1) != '/') {
				$webdav_path .= '/';
			}
			$http_status = $wdc->get($webdav_path . $stored_filename, $contents); //$contents is produced by webdav class
			$wdc->close();
			if (file_put_contents($filename_tmp, $contents)) {
				return $filename_tmp;
			}
			return false;
		}
		return false;
	}


	/**
	 * DETERMINE IF PROJECT HAS AT LEAST ONE FILE ALREADY UPLOADED FOR A "FILE UPLOAD" FIELD
	 * Returns boolean.
	 */
	public static function hasUploadedFiles()
	{
		global $user_rights;
		// If has no file upload fields, then return false
		if (!self::hasFileUploadFields()) return false;
		// If we've stored this in the session already then fetch it to prevent running the query again
		if (isset($_SESSION['hasUploadedFilesInData'][PROJECT_ID])) {
			return $_SESSION['hasUploadedFilesInData'][PROJECT_ID];
		}
		// If user is in a DAG, limit to only records in their DAG
		$group_sql = "";
		if ($user_rights['group_id'] != "") {
			$group_sql  = "and d.record in (" . prep_implode(Records::getRecordListSingleDag(PROJECT_ID, $user_rights['group_id'])) . ")";
		}
		// Check if there exists at least one uploaded file
		$sql = "select 1 from ".\Records::getDataTable(PROJECT_ID)." d, redcap_metadata m where m.project_id = ".PROJECT_ID."
				and m.project_id = d.project_id and d.field_name = m.field_name $group_sql
				and m.element_type = 'file' and d.value != '' limit 1";
		$q = db_query($sql);
		$_SESSION['hasUploadedFilesInData'][PROJECT_ID] = $hasUploadedFilesInData = (db_num_rows($q) > 0);
		// Return true if one exists
		return $hasUploadedFilesInData;
	}


	/**
	 * RETURN HASH OF DOC_ID FOR A FILE IN THE EDOCS_METADATA TABLE
	 * This is used for verifying files, especially when uploaded when the record does not exist yet.
	 * Also to protect from people randomly discovering other people's uploaded files by modifying the URL.
	 */
	public static function docIdHash($doc_id, $projectSALT=null)
	{
		global $salt, $__SALT__;
		$projectSALT = ($projectSALT !== null) ? $projectSALT : (isset($__SALT__) ? $__SALT__ : "");
		return sha1($salt . $doc_id . $projectSALT);
	}

    // Return array of parsed file types for the Restricted Upload File Types setting
    public static function getRestrictedUploadFileTypes()
    {
        $restricted_upload_file_types = strtolower(str_replace(array("\r\n",",",";","\n\n"), array("\n","\n","\n","\n"), $GLOBALS['restricted_upload_file_types']));
        $types = array();
        foreach (explode("\n", $restricted_upload_file_types) as $this_item)
        {
            $this_item = trim($this_item);
            if ($this_item != '') $types[] = $this_item;
        }
        return $types;
    }

    // Return boolean if filename is allowed based on Restricted Upload File Types setting
    public static function fileTypeAllowed($filename)
    {
        $ext = strtolower(getFileExt($filename));
        return ($ext !== "" && !in_array($ext, self::getRestrictedUploadFileTypes()));
    }


	/**
	 * UPLOAD FILE INTO EDOCS FOLDER (OR OTHER SERVER VIA WEBDAV) AND RETURN EDOC_ID# (OR "0" IF FAILED)
	 * Determine if file uploaded as normal FILE input field or as base64 data image via POST, in which $base64data will not be null.
	 */
	public static function uploadFile($file, $project_id = null)
	{
		global $edoc_storage_option;

        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) return 0;

		// Get basic file values
		$doc_name  = trim(Files::sanitizeFileName(strip_tags(html_entity_decode(stripslashes( $file['name']), ENT_QUOTES))));
		$mime_type = mime_content_type($file['tmp_name']);
		$doc_size  = $file['size'];
		$tmp_name  = $file['tmp_name'];

		if($project_id == null && defined("PROJECT_ID")){
			$project_id = PROJECT_ID;
		}

        // If not an allowed file extension, then prevent uploading the file and return "0" to denote error
        if (!Files::fileTypeAllowed($doc_name)) {
            unlink($tmp_name);
            return 0;
        }

		// Default result of success
		$result = 0;
		$file_extension = getFileExt($doc_name);
		$stored_name = date('YmdHis') . "_pid" . ($project_id ? $project_id : "0") . "_" . generateRandomHash(6) . getFileExt($doc_name, true);

		if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
			// LOCAL: Upload to "edocs" folder (use default or custom path for storage)
			if (@move_uploaded_file($tmp_name, EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $stored_name)) {
				$result = 1;
			}
			if ($result == 0 && @rename($tmp_name, EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $stored_name)) {
				$result = 1;
			}
			if ($result == 0 && file_put_contents(EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $stored_name, file_get_contents($tmp_name))) {
				$result = 1;
				unlink($tmp_name);
			}

		} elseif ($edoc_storage_option == '2') {
			// S3
			try {
				$s3 = Files::s3client();
				$s3->putObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$stored_name, 'Body'=>file_get_contents($tmp_name), 'ACL'=>'private'));
				$result = 1;
				unlink($tmp_name);
			} catch (Aws\S3\Exception\S3Exception $e) {
				
			}

		} elseif ($edoc_storage_option == '4') {
			// Azure
			$blobClient = new AzureBlob();
			$result = $blobClient->createBlockBlob($GLOBALS['azure_container'], $stored_name, file_get_contents($tmp_name));
			if ($result) {
				$result = 1;
				unlink($tmp_name);
			}
		} elseif ($edoc_storage_option == '5') {
            // Google Cloud Storage
            $googleClient = Files::googleCloudStorageClient();
            $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);

            // if pid sub-folder is enabled then upload the file under pid folder
            if($GLOBALS['google_cloud_storage_api_use_project_subfolder']){
                $stored_name = $project_id . '/' . $stored_name;
            }

            $result = $bucket->upload(file_get_contents($tmp_name), array('name' => $stored_name));
            if ($result) {
                $result = 1;
                unlink($tmp_name);
            }
        }  else {

			// WebDAV
			if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
			$wdc = new WebdavClient();
			$wdc->set_server($webdav_hostname);
			$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
			$wdc->set_user($webdav_username);
			$wdc->set_pass($webdav_password);
			$wdc->set_protocol(1); // use HTTP/1.1
			$wdc->set_debug(false); // enable debugging?
			if (!$wdc->open()) {
				sleep(1);
				return 0;
			}
			if (substr($webdav_path,-1) != '/') {
				$webdav_path .= '/';
			}
			// Check the file size
			$max_file_size = 2147483648; // 2GB in bytes
			if (filesize($tmp_name) > $max_file_size ) {
				$http_status = $wdc->put_file( $webdav_path . $stored_name,  $tmp_name );
			}
			else {
				$fp      = fopen($tmp_name, 'rb');
				$content = fread($fp, filesize($tmp_name));
				fclose($fp);
				$target_path = $webdav_path . $stored_name;
				$http_status = $wdc->put($target_path,$content);
			}
			$result = 1;
			unlink($tmp_name);
			$wdc->close();
		}

		// Return doc_id (return "0" if failed)
		if ($result == 0) {
			// For base64 data images stored in temp directory, remove them when done
			if ($base64data != null) unlink($tmp_name);
			// Return error
			return 0;
		} else {
			// Add file info the redcap_edocs_metadata table for retrieval later
			$q = db_query("INSERT INTO redcap_edocs_metadata (stored_name, mime_type, doc_name, doc_size, file_extension, project_id, stored_date)
						  VALUES ('" . db_escape($stored_name) . "', '" . db_escape($mime_type) . "', '" . db_escape($doc_name) . "',
						  '" . db_escape($doc_size) . "', '" . db_escape($file_extension) . "',
						  " . ($project_id ? $project_id : "null") . ", '".NOW."')");
			return (!$q ? 0 : db_insert_id());
		}

	}


	// Return array of mime types
    public static function get_mime_types()
	{
        return array(
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',
            // images
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'webp' => 'image/webp',
            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',
            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            // ms office
            'rtf' => 'application/rtf',
            'doc' => 'application/msword',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );
	}


	// Determine the file extension based on the Mime Type passed.
	// Return false if not found
    public static function get_file_extension_by_mime_type($mimetype)
	{
		$mimetype = trim(strtolower($mimetype));
		$mime_types = self::get_mime_types();
		return array_search($mimetype, $mime_types);
	}


	// Determine the Mime Type of a file (this is a soft check that is initially based on filename
	// and is not as strict as PHP's mime_content_type)
    public static function mime_content_type($filename)
	{
		$mime_types = self::get_mime_types();
        $parts = explode('.',$filename);
        $ext = array_pop($parts);
        $ext = strtolower($ext);
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
			$semicolonPos = strpos($mimetype, ";");
			if ($semicolonPos !== false) {
				$mimetype = trim(substr($mimetype, 0, $semicolonPos));
			}
            return $mimetype;
        }
        else {
            return 'application/octet-stream';
        }
    }


	// When a script shuts down, delete a file or set it to be deleted by a cron
    public static function delete_file_on_shutdown($handler, $filename, $deleteNow=false)
	{
		// Delete the file now (and if fails, then set cron to delete it)
		if ($deleteNow) {
			// If cannot delete file (if may still be open somehow), then put in db table to delete by cron later
			fclose($handler);
			unlink($filename);
		}
		// Set file to be deleted when this script ends
		else {
			register_shutdown_function('Files::delete_file_on_shutdown', $handler, $filename, true);
		}
	}


	/**
	 * DELETE TEMP FILES AND EXPIRED SEND-IT FILES ONLY AT CERTAIN TIMES
	 * FOR EACH GIVEN WEB REQUEST IN INIT_GLOBAL.PHP AND INIT_PROJECT.PHP
	 */
	public static function manage_temp_files()
	{
		// Clean up any temporary files sitting on the web server (for various reasons)
		// Only force this once every 10000 requests to allow each web server to flush temp
		// if using load balancing, which won't be as easily cleared by the cron.
		self::remove_temp_deleted_files(defined("LOG_VIEW_ID") && LOG_VIEW_ID % 10000 == 0, true);
	}

	/**
	 * Safely deletes a file by renaming it to a unique temporary name before deleting.
	 *
	 * This function addresses the issue of filesystem tunneling in Windows, where 
	 * deleting a file and creating a new file with the same name shortly after 
	 * can result in the new file inheriting the old file's metadata (e.g., creation date).
	 * By renaming the file to a unique name before deleting, we ensure that the 
	 * new file will not inherit the old file's metadata.
	 *
	 * @param string $filename The name of the file to be deleted.
	 * @param int $seed An optional seed value for generating a unique suffix.
	 * @return bool True on successful deletion, false on failure.
	 */
	public static function safeDelete($filename, $seed = 0) {
		// Exit immediately if the file does not exist
		if (!file_exists($filename)) return false;
	
		// Generate a deterministic hash using the filename and seed
		$hash = sha1($filename . $seed);
    
		// Get the directory name
		$dirname = pathinfo($filename, PATHINFO_DIRNAME);
		
		// Create a new name for the file
		$newName = $dirname . DIRECTORY_SEPARATOR . $hash . '.delete';
	
		// Check if the new name already exists
		if (file_exists($newName)) {
			// Recursively call safeDelete with an incremented seed
			return static::safeDelete($filename, $seed + 1);
		}

		// Rename the file
		if (rename($filename, $newName)) {
			// Delete the renamed file
			if (unlink($newName)) {
				return true;
			}
		}
		return false;
	}


	/**
	 * DELETE TEMP FILES AND EXPIRED SEND-IT FILES (RUN ONCE EVERY 20 MINUTES)
	 */
	public static function remove_temp_deleted_files($forceAction=false, $deleteTempOnly=false)
	{
		global $temp_files_last_delete, $edoc_storage_option;

		// Make sure variable is set
		if ($temp_files_last_delete == "" || !isset($temp_files_last_delete)) return;
        // Trim path
        $GLOBALS['cache_files_filesystem_path'] = trim($GLOBALS['cache_files_filesystem_path']);
		// Set X number of minutes to delete temp files
		$checkEveryXMin = 30;
		// Only delete temp files that are X minutes old or more
		$checkAgeXMin = 60;
        // Timestamp of X min ago
        $x_min_ago = date("YmdHis", mktime(date("H"),date("i")-$checkAgeXMin,date("s"),date("m"),date("d"),date("Y")));
        $x_min_ago_unix = time()-($checkAgeXMin*60);

		// If temp files have not been checked/deleted in the past X minutes, then run procedure to delete them.
		if ($forceAction || strtotime(NOW)-strtotime($temp_files_last_delete) > $checkEveryXMin*60)
		{
			// Initialize counter for number of docs deleted
			$docsDeleted = 0;

			## DELETE ALL FILES IN TEMP DIRECTORY IF OLDER THAN X MINUTES OLD
			// Make sure temp dir is writable and exists
			if (($edoc_storage_option != '3' && is_dir(APP_PATH_TEMP) && is_writeable(APP_PATH_TEMP))
				// If using Google Cloud Storage, ensure that the temp and edocs buckets aren't the same
				// (so we don't accidentally delete permanent files).
				|| !($edoc_storage_option == '3' && APP_PATH_TEMP == EDOC_PATH))
			{
				// Put temp file names into array
				$dh = opendir(APP_PATH_TEMP);
				$files = array();
                if ($dh) {
                    while (false != ($filename = readdir($dh))) {
                        $files[] = $filename;
                    }
                }
				// Loop through all filed in temp dir
				foreach ($files as $value) {
					if ($value == '.' || $value == '..' || $value == 'index.html') continue;
					// Is it a directory? If so, delete all old files in it.
					if (is_dir(APP_PATH_TEMP.$value)) {
						$subdirfiles = getDirFiles(APP_PATH_TEMP.$value);
						foreach ($subdirfiles as $value2) {
							if (is_dir(APP_PATH_TEMP.$value.DS.$value2)) {
                                // If this is a redcap version directory currently being used by the Easy Upgrade process, skip it
                                if ($value == "redcap" && substr($value2, 0, 8) == "redcap_v") {
                                    preg_match_all("/(redcap_v)([0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2})/", $value2, $matches);
                                    if (isset($matches[0])) {
                                        break;
                                    }
                                }
								$subsubdirfiles = getDirFiles(APP_PATH_TEMP.$value.DS.$value2);
								foreach ($subsubdirfiles as $value3) {
									$value3full = APP_PATH_TEMP.$value.DS.$value2.DS.$value3;
									if (file_exists($value3full) && is_file($value3full)) {
										clearstatcache(true, $value3full);
										$value3time = filemtime($value3full);
										if (is_numeric($value3time) && $value3time < $x_min_ago_unix) {
											// Delete the file
											unlink($value3full);
										}
									}
								}
							} else {
								$value2full = APP_PATH_TEMP.$value.DS.$value2;
								if (file_exists($value2full) && is_file($value2full)) {
									clearstatcache(true, $value2full);
									$value2time = filemtime($value2full);
									if (is_numeric($value2time) && $value2time < $x_min_ago_unix) {
										// Delete the file
										unlink($value2full);
									}
								}
							}
						}
					} else {
						// Delete ANY files that begin with a 14-digit timestamp
						$file_time = substr($value, 0, 14);
						// If filename contains a timestamp and is more than one hour old, delete it
						if (is_numeric($file_time) && $file_time < $x_min_ago) {
							// Delete the file
							unlink(APP_PATH_TEMP . $value);
						}
                        // If filename does NOT contain a timestamp, check the file's "modification time" attribute to determine if we should delete it
                        else {
                            // Init file
                            clearstatcache(true, APP_PATH_TEMP . $value);
                            $fileDeleted = false; // default

                            // RAPID RETRIEVAL ONLY: If this is a .rr file used with Rapid Retrieval, then delete it if the project's last logged activity
                            // is greater than the cache file time (filename will be in format PIDXXX-adskjasdfljasfddadsfasdedasf.rr)
                            if ($GLOBALS['cache_storage_system'] == 'file' && $GLOBALS['cache_files_filesystem_path'] == '' && self::isRapidRetrievalCacheFile($value))
                            {
                                // Obtain PID from filename
                                list ($pid, $nothing) = explode("-", substr($value, 3), 2);
                                if (isinteger($pid)) {
                                    // Get the last_logged_event for this project
                                    $last_logged_event = Project::getLastLoggedEvent($pid, true);
                                    if ($last_logged_event != null) {
                                        // If file is older than its project's last_logged_event, delete it
                                        // Use access time to overcome file system tunnelling in windows
										$access_time_unix = fileatime(APP_PATH_TEMP . $value);
                                        // If file is older than its project's last_logged_event, delete it
                                        if (is_numeric($access_time_unix) && $access_time_unix < strtotime($last_logged_event)) {
                                            $fileDeleted = unlink(APP_PATH_TEMP . $value);
                                        }
                                    }
                                }
                            }

                            // REGULAR TEMP FILES AND EXPIRED RAPID RETRIEVAL FILES: If file is older than X minutes, delete it (based on the file's creation time)
                            if (!$fileDeleted)
                            {
                                $modification_time_unix = filemtime(APP_PATH_TEMP . $value); // Rapid Retrieval uses the modification time as TTL
                                if (is_numeric($modification_time_unix) && $modification_time_unix < $x_min_ago_unix) {
                                    $fileDeleted = unlink(APP_PATH_TEMP . $value);
                                }
                            }
						}
					}
				}
			}

            // RAPID RETRIEVAL (ALTERNATE DIRECTORY): If this is a .rr file used with Rapid Retrieval, then delete it if the project's last logged activity
            // is greater than the cache file time (filename will be in format PIDXXX-adskjasdfljasfddadsfasdedasf.rr)
            if ($GLOBALS['cache_storage_system'] == 'file' && $GLOBALS['cache_files_filesystem_path'] != '')
            {
                $cacheDir = rtrim($GLOBALS['cache_files_filesystem_path'], DS).DS; // make sure it ends with a slash
                $files = getDirFiles($cacheDir);
                foreach ($files as $value) {
                    if (!Files::isRapidRetrievalCacheFile($value)) continue;
                    $fileDeleted = false; // default
                    // Obtain PID from filename
                    list ($pid, $nothing) = explode("-", substr($value, 3), 2);
                    if (isinteger($pid)) {
                        clearstatcache(true, $cacheDir . $value);
                        // Get the last_logged_event for this project
                        $last_logged_event = Project::getLastLoggedEvent($pid, true);
                        if ($last_logged_event != null) {
							// use access time to overcome file system tunnelling in windows
                            $access_time_unix = fileatime($cacheDir . $value);
                            // If file is older than its project's last_logged_event, delete it
                            if (is_numeric($access_time_unix) && $access_time_unix < strtotime($last_logged_event)) {
                                $fileDeleted = unlink($cacheDir . $value);
                            }
                        }
                        if (!$fileDeleted) {
                            // Delete if file is older than the Rapid Retrieval set expiration time
                            $modification_time_unix = filemtime($cacheDir . $value); // Rapid Retrieval uses the modification time as TTL
                            if (is_numeric($modification_time_unix) && $modification_time_unix < $x_min_ago_unix) {
                                $fileDeleted = unlink($cacheDir . $value);
                            }
                        }
                    }
                }
            }

            ## SET ALL EXPIRED PDF CACHED IMAGES IN redcap_pdf_image_cache FOR DELETION
            $q = db_query("select image_doc_id from redcap_pdf_image_cache where expiration < '".NOW."'");
            while ($row = db_fetch_assoc($q))
            {
                // Set as deleted in the edocs table
                db_query("update redcap_edocs_metadata set delete_date = '".NOW."' where doc_id = '".db_escape($row['image_doc_id'])."'");
                // Now remove its row from the redcap_pdf_image_cache table
                if (db_query("delete from redcap_pdf_image_cache where image_doc_id = '".db_escape($row['image_doc_id'])."'")) {
                    $docsDeleted++;
                }
            }

            // If only deleting temp files, stop here
            if ($deleteTempOnly) return $docsDeleted;


			## DELETE ANY SEND-IT OR EDOC FILES THAT ARE FLAGGED FOR DELETION
            $docid_deleted = array();

			// Loop through list of expired Send-It files (only location=1, which excludes edocs and file repository files)
			// and Edoc files that were deleted by user over 30 days ago.
			$sql = "(select 'sendit' as type, document_id, doc_name, null as project_id from redcap_sendit_docs where location = 1 and expire_date < '".NOW."'
					and date_deleted is null)
					UNION
					(select 'edocs' as type, doc_id as document_id, stored_name as doc_name, project_id from redcap_edocs_metadata where
					delete_date is not null and date_deleted_server is null and delete_date < DATE_ADD('".NOW."', INTERVAL -".Files::EDOCS_DELETION_DAYS_OLD." DAY))";
			$q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                if (self::deleteFilePermanently($row['doc_name'], $row['project_id'])) {
                    $docid_deleted[$row['type']][] = $row['document_id'];
                }
            }

			// For all Send-It files deleted here, add date_deleted timestamp to table
			if (isset($docid_deleted['sendit']))
			{
				db_query("update redcap_sendit_docs set date_deleted = '".NOW."' where document_id in (" . implode(",", $docid_deleted['sendit']) . ")");
				$docsDeleted += db_affected_rows();
			}
			// For all Edoc files deleted here, add date_deleted_server timestamp to table
			if (isset($docid_deleted['edocs']))
			{
				db_query("update redcap_edocs_metadata set date_deleted_server = '".NOW."' where doc_id in (" . implode(",", $docid_deleted['edocs']) . ")");
				$docsDeleted += db_affected_rows();
			}

			## Now that all temp/send-it files have been deleted, reset time flag in config table
			db_query("update redcap_config set value = '".NOW."' where field_name = 'temp_files_last_delete'");

			// Return number of docs deleted
			return $docsDeleted;
		}
	}

    // Delete an edoc file (from wherever files are stored - local, S3, etc.)
    // $stored_name = stored name of file from redcap_edocs_metadata table
    public static function deleteFilePermanently($stored_name, $project_id)
    {
        global $edoc_storage_option;
        $success = false;

        // Delete from local web server folder
        if ($edoc_storage_option == '0' || $edoc_storage_option == '3')
        {
            // Delete file and add to list of files deleted
            if (file_exists(EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $stored_name)) {
                unlink(EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $stored_name);
            }
            $success = true; // Mark as true regardless because otherwise it'll keep trying to delete a file that no longer exists
        }
        // Delete from S3
        elseif ($edoc_storage_option == '2')
        {
            $s3 = Files::s3client();
            // Delete file, and if successfully deleted, then add to list of files deleted
            try {
                $s3->deleteObject(array('Bucket' => $GLOBALS['amazon_s3_bucket'], 'Key' => $stored_name));
                $success = true;
            } catch (Exception $e) { }
        }
        // Delete from Azure
        elseif ($edoc_storage_option == '4')
        {
            $blobClient = new AzureBlob();
            try {
                // Delete file, and if successfully deleted, then add to list of files deleted
                $blobClient->deleteBlob($stored_name);
                $success = true;
            } catch (Exception $e) { }
        }
        // Delete from external server via webdav
        elseif ($edoc_storage_option == '1')
        {
            // Call webdav class and open connection to external server
            if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
            $wdc = new WebdavClient();
            $wdc->set_server($webdav_hostname);
            $wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
            $wdc->set_user($webdav_username);
            $wdc->set_pass($webdav_password);
            $wdc->set_protocol(1);  // use HTTP/1.1
            $wdc->set_debug(false); // enable debugging?
            $wdc->open();
            if (substr($webdav_path,-1) != "/" && substr($webdav_path,-1) != "\\") {
                $webdav_path .= '/';
            }
            // Delete file
            $http_status = $wdc->delete($webdav_path . $stored_name);
            $success = true;
        }

        // Return success status
        return $success;
    }

	// Return boolean if the filename provided has the format of a Rapid Retrieval cache file
	public static function isRapidRetrievalCacheFile($filename)
	{
        return (strpos($filename, "PID") === 0 && strpos($filename, "-") !== false && getFileExt($filename) == FileStorageNameVisitor::EXTENSION);
	}

	// Obtain image width and height of an uploaded image. Return null if not an image
	public static function getImgWidthHeight($filepath)
	{
		$valid_img_types = array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP);
		$img_height = $img_width = null;
		$imgfile_size = getimagesize($filepath);
		if ($imgfile_size && in_array($imgfile_size[2], $valid_img_types)) {
			$img_height = $imgfile_size[1];
			$img_width = $imgfile_size[0];
		}
		return array($img_width, $img_height);
	}

	// Obtain image width and height of an edoc file by doc_id
	public static function getImgWidthHeightByDocId($doc_id, $useTemp=false)
	{
		global $edoc_storage_option;
        if (!isinteger($doc_id)) return [null, null];
		if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
            // Local storage
            $sql = "select stored_name, project_id from redcap_edocs_metadata 
				    where doc_id = '" . db_escape($doc_id). "' and delete_date is null";
            $q = db_query($sql);
            if (db_num_rows($q) == 0) return [null, null];
            $project_id = db_result($q, 0, 'project_id');
            $stored_name = db_result($q, 0, 'stored_name');
            $filename = EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $stored_name;
            return self::getImgWidthHeight($filename);
        } elseif ($useTemp) {
            // Other non-local storage (copy to TEMP and then delete)
            list ($mimeType, $docName, $fileContent) = Files::getEdocContentsAttributes($doc_id);
            $filename_pre = APP_PATH_TEMP . date('YmdHis') . "_file_repo_view_" . substr(sha1(rand()), 0, 10);
            $filename = $filename_pre . getFileExt($docName, true);
            file_put_contents($filename, $fileContent);
            $imgWidthHeight = self::getImgWidthHeight($filename);
            unlink($filename);
            return $imgWidthHeight;
        } else {
            return [null, null];
        }
	}

	// Validate a base64 encoded image string. Return boolean regarding if a valid image. 
	public static function check_base64_image($base64) 
	{
		$img = base64_decode($base64);
		if (!$img) return false;
		$img_filename = APP_PATH_TEMP . date('YmdHis') . "_tmpfile" . substr(sha1(rand()), 0, 6);
		file_put_contents($img_filename, $img);
		$info = getimagesize($img_filename);
		unlink($img_filename);
		if ($info[0] > 0 && $info[1] > 0 && $info['mime']) {
			return true;
		}
		return false;
	}

    // If the feature "File Upload Version History" is enabled for the whole SYSTEM
    public static function fileUploadVersionHistoryEnabledSystem()
    {
        return ($GLOBALS['file_upload_versioning_global_enabled'] == '1');
    }

    // If the feature "File Upload Version History" is enabled for a given PROJECT
    public static function fileUploadVersionHistoryEnabledProject($project_id)
    {
        $Proj = new Project($project_id);
        return (self::fileUploadVersionHistoryEnabledSystem() && $Proj->project['file_upload_versioning_enabled'] == '1');
    }

    // If the feature "Password verification for File Upload fields with duplicate storage on external server" is enabled for the whole SYSTEM
    public static function fileUploadPasswordVerifyExternalStorageEnabledSystem()
    {
        return ($GLOBALS['file_upload_vault_filesystem_type'] != '');
    }

    // If the feature "Password verification for File Upload fields with duplicate storage on external server" is enabled for a given PROJECT
    public static function fileUploadPasswordVerifyExternalStorageEnabledProject($project_id)
    {
        $Proj = new Project($project_id);
        return (self::fileUploadPasswordVerifyExternalStorageEnabledSystem() && $Proj->project['file_upload_vault_enabled'] == '1');
    }

	// If the feature "Password verification for File Upload fields with duplicate storage on external server" is enabled for a given PROJECT,
    // then store file on that server
	public static function writeUploadedFileToVaultExternalServer($filename, $file_contents)
	{
		// Add slash to end of root path
		$pathLastChar = substr($GLOBALS['file_upload_vault_filesystem_path'], -1);
		if ($pathLastChar != "/" && $pathLastChar != "\\") {
			$GLOBALS['file_upload_vault_filesystem_path'] .= "/";
		}
		// Not enabled for project
		if ($GLOBALS['file_upload_vault_filesystem_type'] == '') {
			return null;
		}
		// AZURE BLOB
		elseif ($GLOBALS['file_upload_vault_filesystem_type'] == 'AZURE_BLOB')
		{
			try {
				$blobClient = new AzureBlob();
				$result = $blobClient->createBlockBlob($GLOBALS['file_upload_vault_filesystem_container'], $filename, $file_contents);
				return true;
			} catch (Exception $e) {
				return false;
			}
		}
		// S3
		elseif ($GLOBALS['file_upload_vault_filesystem_type'] == 'S3')
		{
			try {
				$s3 = Files::s3client();
				$s3->putObject(array('Bucket'=>$GLOBALS['file_upload_vault_filesystem_container'], 'Key'=>$filename, 'Body'=>$file_contents, 'ACL'=>'private'));
				return true;
			} catch (Aws\S3\Exception\S3Exception $e) {
				return false;
			}
		}
		// WEBDAV
		elseif ($GLOBALS['file_upload_vault_filesystem_type'] == 'WEBDAV')
		{
			try {
				$settings = array(
					'baseUri' => $GLOBALS['file_upload_vault_filesystem_host'],
					'userName' => $GLOBALS['file_upload_vault_filesystem_username'],
					'password' => $GLOBALS['file_upload_vault_filesystem_password']
                );
                if ($GLOBALS['file_upload_vault_filesystem_authtype'] == 'AUTH_NTLM') {
                    $settings['authType'] = Sabre\DAV\Client::AUTH_NTLM;
                } elseif ($GLOBALS['file_upload_vault_filesystem_authtype'] == 'AUTH_BASIC') {
                    $settings['authType'] = Sabre\DAV\Client::AUTH_BASIC;
                } else {
                    $settings['authType'] = Sabre\DAV\Client::AUTH_DIGEST;
                }
				$client = new Sabre\DAV\Client($settings);
				$adapter = new League\Flysystem\WebDAV\WebDAVAdapter($client, $GLOBALS['file_upload_vault_filesystem_path']);
				// Instantiate the filesystem
                $filesystem = new League\Flysystem\Filesystem($adapter);
				// Write the file
				$response = $filesystem->write($filename, $file_contents);
				// Return boolean regarding success
				return $response;
			} catch (Exception $e) {
				return false;
			}
		}
		// SFTP
		elseif ($GLOBALS['file_upload_vault_filesystem_type'] == 'SFTP')
		{
			try {
                $settings = array(
                    'host' => $GLOBALS['file_upload_vault_filesystem_host'],
                    'port' => 22,
                    'username' => $GLOBALS['file_upload_vault_filesystem_username'],
                    'password' => $GLOBALS['file_upload_vault_filesystem_password'],
                    'root' => $GLOBALS['file_upload_vault_filesystem_path'],
                    'timeout' => 10
                );
                if ($GLOBALS['file_upload_vault_filesystem_private_key_path'] != '') {
                    $settings['privateKey'] = $GLOBALS['file_upload_vault_filesystem_private_key_path'];
                }
                $adapter = new League\Flysystem\Sftp\SftpAdapter($settings);
                // Instantiate the filesystem
                $filesystem = new League\Flysystem\Filesystem($adapter);
				// Write the file
				$response = $filesystem->write($filename, $file_contents);
				// Return boolean regarding success
				return $response;
			} catch (Exception $e) {
				return false;
			}
		}
	}

	// Determine if an edoc file has been deleted already
	public static function edocWasDeleted($doc_id)
	{
		if (!isinteger($doc_id)) return false;
		$sql = "select 1 from redcap_edocs_metadata where doc_id = $doc_id and delete_date is not null and delete_date < '".NOW."'";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}

	// Store an entire record as a PDF in the File Repository. Return boolean on whether successful.
	public static function archiveRecordAsPDF($project_id, $record, $arm)
	{
		$Proj = new Project($project_id);
		$recordFilename = str_replace(" ", "_", trim(preg_replace("/[^0-9a-zA-Z- ]/", "", $record)));
		$pdf_filename = APP_PATH_TEMP . "pid" . $Proj->project_id . "_id" . $recordFilename . "_" . date('Y-m-d_His') . ".pdf";
		// Obtain the compact PDF of the response
		$pdf_contents = REDCap::getPDF($record, null, null, false, null, true);
		// Temporarily store file in temp
		file_put_contents($pdf_filename, $pdf_contents);
		// Add PDF to edocs_metadata table
		$pdfFile = array('name'=>basename($pdf_filename), 'type'=>'application/pdf',
						 'size'=>filesize($pdf_filename), 'tmp_name'=>$pdf_filename);
		$pdf_edoc_id = Files::uploadFile($pdfFile);
		if (file_exists($pdf_filename)) unlink($pdf_filename);
		if ($pdf_edoc_id == 0) return false;
		// Add to table
		$arm_id = $Proj->events[$arm]['id'];
		$sql = "insert into redcap_locking_records_pdf_archive (doc_id, project_id, record, arm_id) values
				($pdf_edoc_id, $project_id, '".db_escape($record)."', '".db_escape($arm_id)."')";
		$q = db_query($sql);
		// Store file on external server
		$storedFileExternal = Files::writeRecordLockingPdfToExternalServer(basename($pdf_filename), $pdf_contents);
		// Return boolean on success
		return ($q && $storedFileExternal !== false);
	}

	// If project has External Storage enabled for the PDF Snapshot, then store file on that server
	public static function writeFilePdfAutoArchiverToExternalServer($filename, $file_contents) 
	{
		// Add slash to end of root path
		$pathLastChar = substr($GLOBALS['pdf_econsent_filesystem_path'], -1);
		if ($pathLastChar != "/" && $pathLastChar != "\\") {
			$GLOBALS['pdf_econsent_filesystem_path'] .= "/";
		}
		// Not enabled for project
		if ($GLOBALS['pdf_econsent_filesystem_type'] == '') {
			return false;
		}
		// AZURE BLOB
		elseif ($GLOBALS['pdf_econsent_filesystem_type'] == 'AZURE_BLOB')
		{
			try {
				$blobClient = new AzureBlob();
				$result = $blobClient->createBlockBlob($GLOBALS['pdf_econsent_filesystem_container'], $filename, $file_contents);
				return true;
			} catch (Exception $e) {
				return false;
			}
		}
		// S3
		elseif ($GLOBALS['pdf_econsent_filesystem_type'] == 'S3')
		{
			try {
				$s3 = Files::s3client();
				$s3->putObject(array('Bucket'=>$GLOBALS['pdf_econsent_filesystem_container'], 'Key'=>$filename, 'Body'=>$file_contents, 'ACL'=>'private'));
				return true;
			} catch (Aws\S3\Exception\S3Exception $e) {
				return false;
			}
		}
		// WEBDAV
		elseif ($GLOBALS['pdf_econsent_filesystem_type'] == 'WEBDAV')
		{
			try {
				$settings = array(
					'baseUri' => $GLOBALS['pdf_econsent_filesystem_host'],
					'userName' => $GLOBALS['pdf_econsent_filesystem_username'],
					'password' => $GLOBALS['pdf_econsent_filesystem_password']
				);
                if ($GLOBALS['pdf_econsent_filesystem_authtype'] == 'AUTH_NTLM') {
                    $settings['authType'] = Sabre\DAV\Client::AUTH_NTLM;
                } elseif ($GLOBALS['pdf_econsent_filesystem_authtype'] == 'AUTH_BASIC') {
                    $settings['authType'] = Sabre\DAV\Client::AUTH_BASIC;
                } else {
                    $settings['authType'] = Sabre\DAV\Client::AUTH_DIGEST;
                }
				$client = new Sabre\DAV\Client($settings);
				$adapter = new League\Flysystem\WebDAV\WebDAVAdapter($client, $GLOBALS['pdf_econsent_filesystem_path']);
				// Instantiate the filesystem
                $filesystem = new League\Flysystem\Filesystem($adapter);
				// Write the file
				$response = $filesystem->write($filename, $file_contents);
				// Return boolean regarding success
				return $response;
			} catch (Exception $e) {
				return false;
			}
		}
		// SFTP
		elseif ($GLOBALS['pdf_econsent_filesystem_type'] == 'SFTP')
		{
			try {
                $settings = array(
                    'host' => $GLOBALS['pdf_econsent_filesystem_host'],
                    'port' => 22,
                    'username' => $GLOBALS['pdf_econsent_filesystem_username'],
                    'password' => $GLOBALS['pdf_econsent_filesystem_password'],
                    'root' => $GLOBALS['pdf_econsent_filesystem_path'],
                    'timeout' => 10
                );
                if ($GLOBALS['pdf_econsent_filesystem_private_key_path'] != '') {
                    $settings['privateKey'] = $GLOBALS['pdf_econsent_filesystem_private_key_path'];
                }
                $adapter = new League\Flysystem\Sftp\SftpAdapter($settings);
                // Instantiate the filesystem
                $filesystem = new League\Flysystem\Filesystem($adapter);
				// Write the file
				$response = $filesystem->write($filename, $file_contents);
				// Return boolean regarding success
				return $response;
			} catch (Exception $e) {
                if (isDev()) {
                    print_array($e->getMessage());
                    exit;
                }
				return false;
			}
		}
	}

	// If project has External Storage enabled for the Record-locking PDF confirmation, then store file on that server
	public static function writeRecordLockingPdfToExternalServer($filename, $file_contents)
	{
		// Add slash to end of root path
		$pathLastChar = substr($GLOBALS['record_locking_pdf_vault_filesystem_path'], -1);
		if ($pathLastChar != "/" && $pathLastChar != "\\") {
			$GLOBALS['record_locking_pdf_vault_filesystem_path'] .= "/";
		}
		// Not enabled for project
		if ($GLOBALS['record_locking_pdf_vault_filesystem_type'] == '') {
			return null;
		}
		// AZURE BLOB
		elseif ($GLOBALS['record_locking_pdf_vault_filesystem_type'] == 'AZURE_BLOB')
		{
			try {
				$blobClient = new AzureBlob();
				$result = $blobClient->createBlockBlob($GLOBALS['record_locking_pdf_vault_filesystem_container'], $filename, $file_contents);
				return true;
			} catch (Exception $e) {
				return false;
			}
		}
		// S3
		elseif ($GLOBALS['record_locking_pdf_vault_filesystem_type'] == 'S3')
		{
			try {
				$s3 = Files::s3client();
				$s3->putObject(array('Bucket'=>$GLOBALS['record_locking_pdf_vault_filesystem_container'], 'Key'=>$filename, 'Body'=>$file_contents, 'ACL'=>'private'));
				return true;
			} catch (Aws\S3\Exception\S3Exception $e) {
				return false;
			}
		}
		// WEBDAV
		elseif ($GLOBALS['record_locking_pdf_vault_filesystem_type'] == 'WEBDAV')
		{
			try {
				$settings = array(
					'baseUri' => $GLOBALS['record_locking_pdf_vault_filesystem_host'],
					'userName' => $GLOBALS['record_locking_pdf_vault_filesystem_username'],
					'password' => $GLOBALS['record_locking_pdf_vault_filesystem_password']
				);
                if ($GLOBALS['record_locking_pdf_vault_filesystem_authtype'] == 'AUTH_NTLM') {
                    $settings['authType'] = Sabre\DAV\Client::AUTH_NTLM;
                } elseif ($GLOBALS['record_locking_pdf_vault_filesystem_authtype'] == 'AUTH_BASIC') {
                    $settings['authType'] = Sabre\DAV\Client::AUTH_BASIC;
                } else {
                    $settings['authType'] = Sabre\DAV\Client::AUTH_DIGEST;
                }
				$client = new Sabre\DAV\Client($settings);
				$adapter = new League\Flysystem\WebDAV\WebDAVAdapter($client, $GLOBALS['record_locking_pdf_vault_filesystem_path']);
				// Instantiate the filesystem
                $filesystem = new League\Flysystem\Filesystem($adapter);
				// Write the file
				$response = $filesystem->write($filename, $file_contents);
				// Return boolean regarding success
				return $response;
			} catch (Exception $e) {
				return false;
			}
		}
		// SFTP
		elseif ($GLOBALS['record_locking_pdf_vault_filesystem_type'] == 'SFTP') {
			try {
                $settings = array(
                    'host' => $GLOBALS['record_locking_pdf_vault_filesystem_host'],
                    'port' => 22,
                    'username' => $GLOBALS['record_locking_pdf_vault_filesystem_username'],
                    'password' => $GLOBALS['record_locking_pdf_vault_filesystem_password'],
                    'root' => $GLOBALS['record_locking_pdf_vault_filesystem_path'],
                    'timeout' => 10
                );
                if ($GLOBALS['record_locking_pdf_vault_filesystem_private_key_path'] != '') {
                    $settings['privateKey'] = $GLOBALS['record_locking_pdf_vault_filesystem_private_key_path'];
                }
                $adapter = new League\Flysystem\Sftp\SftpAdapter($settings);
                // Instantiate the filesystem
                $filesystem = new League\Flysystem\Filesystem($adapter);
				// Write the file
				$response = $filesystem->write($filename, $file_contents);
				// Return boolean regarding success
				return $response;
			} catch (Exception $e) {
				return false;
			}
		}
	}

	// When using AWS S3 for file storage, obtain the region name (eu-west-3) from the endpoint (s3.eu-west-3.amazonaws.com, s3-eu-west-3.amazonaws.com)
	public static function getAwsS3RegionFromEndpoint($endpoint) 
	{
		$region = trim($endpoint);
		// If region is blank, then default to us-east-1
		if ($region == '') return 'us-east-1';
		// First, clean the endpoint of an prefixes
		$region = str_replace(array("https://", "http://", "www."), array("", "", ""), $endpoint);
		// Remove the amazonaws.com ending
		$region = str_replace(".amazonaws.com", "", $region);
		// Remove s3. and s3- from the beginning
		$region = str_replace(array("s3.", "s3-"), array("", ""), $region);
		// Return the region name
		return $region;
	}	

	// When using AWS S3 for file storage, obtain the region name (eu-west-3) from the redcap_config table
	public static function getS3Region()
	{
		$region = trim($GLOBALS['amazon_s3_endpoint']);
		// If region is blank, then default to us-east-1
		return ($region == '' ? 'us-east-1' : $region);
	}

    // When using S3 for file storage, obtain the endpoint (if set) from the redcap_config table
    public static function getS3Endpoint()
    {
        $endpoint = trim($GLOBALS['amazon_s3_endpoint_url']);
        // If region is blank, then default to us-east-1
        return ($endpoint == '' ? null : $endpoint);
    }

	// When using AWS S3 for file storage, instantiate and return the S3 client
	public static function s3client()
	{
		try {
			$credentials = new Aws\Credentials\Credentials($GLOBALS['amazon_s3_key'], $GLOBALS['amazon_s3_secret']);
			$s3 = new Aws\S3\S3Client(array('version'=>'latest', 'endpoint'=>self::getS3Endpoint(), 'region'=>self::getS3Region(), 'credentials'=>$credentials));
			return $s3;
		} catch (Aws\S3\Exception\S3Exception $e) {
			// Failed
			return false;
		}
	}

    public static function googleCloudStorageClient(){
        try{
            $googleClient = new StorageClient(['keyFile' => json_decode($GLOBALS['google_cloud_storage_api_service_account'], true), 'projectId' => $GLOBALS['google_cloud_storage_api_project_id']]);
            return $googleClient;
        }catch (\Exception $e){
            return false;
        }
    }
    
	// Truncate a file name to X characters while still maintaining the file extension
	public static function truncateFileName($filename, $charLimit, $truncateMarkFromEnd=9)
	{
		$origLength = mb_strlen($filename);
		if ($origLength > $charLimit) {
			$filename = trim(mb_substr($filename, 0, $charLimit - $truncateMarkFromEnd))."...".trim(mb_substr($filename, $origLength - $truncateMarkFromEnd));
		}
		return $filename;
	}

    // Delete file by doc_id
    public static function deleteFileByDocId($doc_id, $project_id)
    {
        if (!isinteger($doc_id) || !isinteger($project_id)) return false;
        $sql = "UPDATE redcap_edocs_metadata
				SET delete_date = '".NOW."'
				WHERE doc_id = $doc_id and delete_date is null and project_id = $project_id";
        return db_query($sql);
    }


    // Validate doc_id for a given project
    public static function validateDocId($doc_id, $project_id)
    {
        if (!isinteger($doc_id) || !isinteger($project_id)) return false;
        $sql = "select count(*) from redcap_edocs_metadata
				WHERE doc_id = $doc_id and project_id = $project_id";
		$q = db_query($sql);
        return (db_result($q, 0) > 0);
    }

    // Get FA icon class by file extension
    public static function getFontAwesomeClass($file_extension)
    {
        $file_extension = strtolower($file_extension);
        $icon_classes = array(
            // Image
            'png' => 'fa-file-image',
            'jpeg' => 'fa-file-image',
            'jpe' => 'fa-file-image',
            'jpg' => 'fa-file-image',
            'gif' => 'fa-file-image',
            'bmp' => 'fa-file-image',
            'tif' => 'fa-file-image',
            'image' => 'fa-file-image',
            // Audio
            'mp3' => 'fa-file-audio',
            'wav' => 'fa-file-audio',
            'wma' => 'fa-file-audio',
            // Video
            "3g2" => 'fa-file-video',
            "3gp" => 'fa-file-video',
            "aaf" => 'fa-file-video',
            "asf" => 'fa-file-video',
            "avchd" => 'fa-file-video',
            "avi" => 'fa-file-video',
            "drc" => 'fa-file-video',
            "flv" => 'fa-file-video',
            "m2v" => 'fa-file-video',
            "m3u8" => 'fa-file-video',
            "m4p" => 'fa-file-video',
            "m4v" => 'fa-file-video',
            "mkv" => 'fa-file-video',
            "mng" => 'fa-file-video',
            "mov" => 'fa-file-video',
            "mp2" => 'fa-file-video',
            "mp4" => 'fa-file-video',
            "mpe" => 'fa-file-video',
            "mpeg" => 'fa-file-video',
            "mpg" => 'fa-file-video',
            "mpv" => 'fa-file-video',
            "mxf" => 'fa-file-video',
            "nsv" => 'fa-file-video',
            "ogg" => 'fa-file-video',
            "ogv" => 'fa-file-video',
            "qt" => 'fa-file-video',
            "rm" => 'fa-file-video',
            "rmvb" => 'fa-file-video',
            "roq" => 'fa-file-video',
            "svi" => 'fa-file-video',
            "vob" => 'fa-file-video',
            "webm" => 'fa-file-video',
            "wmv" => 'fa-file-video',
            "yuv" => 'fa-file-video',
            // Documents
            'pdf' => 'fa-file-pdf',
            'xls' => 'fa-file-excel',
            'xlsx' => 'fa-file-excel',
            'doc' => 'fa-file-word',
            'docx' => 'fa-file-word',
            'ppt' => 'fa-file-powerpoint',
            'pptx' => 'fa-file-powerpoint',
            'json' => 'fa-file-code',
            'php' => 'fa-file-code',
            'js' => 'fa-file-code',
            'html' => 'fa-file-code',
            'sql' => 'fa-file-code',
            'xml' => 'fa-file-code',
            // Archives
            'application/gzip' => 'fa-file-zipper',
            'application/zip' => 'fa-file-zipper'
        );
        if ($file_extension == 'csv') {
            $class = "fa-solid fa-file-csv";
        } else {
            $class = "fa-regular " . ($icon_classes[$file_extension] ?? 'fa-file-lines');
        }
        return $class;
    }

    // Remove invalid characters from filename or folder name
    public static function sanitizeFileName($filename)
    {
        $special_chars = array( '?', '/', '\\', '<', '>', ':', '?', '"', '*', '|', '~', '`', '!', '«', '»', '”', '“', chr( 0 ) );
        $chars = array(
            'ª' => 'a',
            'º' => 'o',
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'AE',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ð' => 'D',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ý' => 'Y',
            'Þ' => 'TH',
            'ß' => 's',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'd',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ý' => 'y',
            'þ' => 'th',
            'ÿ' => 'y',
            'Ø' => 'O',
            // Decompositions for Latin Extended-A.
            'Ā' => 'A',
            'ā' => 'a',
            'Ă' => 'A',
            'ă' => 'a',
            'Ą' => 'A',
            'ą' => 'a',
            'Ć' => 'C',
            'ć' => 'c',
            'Ĉ' => 'C',
            'ĉ' => 'c',
            'Ċ' => 'C',
            'ċ' => 'c',
            'Č' => 'C',
            'č' => 'c',
            'Ď' => 'D',
            'ď' => 'd',
            'Đ' => 'D',
            'đ' => 'd',
            'Ē' => 'E',
            'ē' => 'e',
            'Ĕ' => 'E',
            'ĕ' => 'e',
            'Ė' => 'E',
            'ė' => 'e',
            'Ę' => 'E',
            'ę' => 'e',
            'Ě' => 'E',
            'ě' => 'e',
            'Ĝ' => 'G',
            'ĝ' => 'g',
            'Ğ' => 'G',
            'ğ' => 'g',
            'Ġ' => 'G',
            'ġ' => 'g',
            'Ģ' => 'G',
            'ģ' => 'g',
            'Ĥ' => 'H',
            'ĥ' => 'h',
            'Ħ' => 'H',
            'ħ' => 'h',
            'Ĩ' => 'I',
            'ĩ' => 'i',
            'Ī' => 'I',
            'ī' => 'i',
            'Ĭ' => 'I',
            'ĭ' => 'i',
            'Į' => 'I',
            'į' => 'i',
            'İ' => 'I',
            'ı' => 'i',
            'Ĳ' => 'IJ',
            'ĳ' => 'ij',
            'Ĵ' => 'J',
            'ĵ' => 'j',
            'Ķ' => 'K',
            'ķ' => 'k',
            'ĸ' => 'k',
            'Ĺ' => 'L',
            'ĺ' => 'l',
            'Ļ' => 'L',
            'ļ' => 'l',
            'Ľ' => 'L',
            'ľ' => 'l',
            'Ŀ' => 'L',
            'ŀ' => 'l',
            'Ł' => 'L',
            'ł' => 'l',
            'Ń' => 'N',
            'ń' => 'n',
            'Ņ' => 'N',
            'ņ' => 'n',
            'Ň' => 'N',
            'ň' => 'n',
            'ŉ' => 'n',
            'Ŋ' => 'N',
            'ŋ' => 'n',
            'Ō' => 'O',
            'ō' => 'o',
            'Ŏ' => 'O',
            'ŏ' => 'o',
            'Ő' => 'O',
            'ő' => 'o',
            'Œ' => 'OE',
            'œ' => 'oe',
            'Ŕ' => 'R',
            'ŕ' => 'r',
            'Ŗ' => 'R',
            'ŗ' => 'r',
            'Ř' => 'R',
            'ř' => 'r',
            'Ś' => 'S',
            'ś' => 's',
            'Ŝ' => 'S',
            'ŝ' => 's',
            'Ş' => 'S',
            'ş' => 's',
            'Š' => 'S',
            'š' => 's',
            'Ţ' => 'T',
            'ţ' => 't',
            'Ť' => 'T',
            'ť' => 't',
            'Ŧ' => 'T',
            'ŧ' => 't',
            'Ũ' => 'U',
            'ũ' => 'u',
            'Ū' => 'U',
            'ū' => 'u',
            'Ŭ' => 'U',
            'ŭ' => 'u',
            'Ů' => 'U',
            'ů' => 'u',
            'Ű' => 'U',
            'ű' => 'u',
            'Ų' => 'U',
            'ų' => 'u',
            'Ŵ' => 'W',
            'ŵ' => 'w',
            'Ŷ' => 'Y',
            'ŷ' => 'y',
            'Ÿ' => 'Y',
            'Ź' => 'Z',
            'ź' => 'z',
            'Ż' => 'Z',
            'ż' => 'z',
            'Ž' => 'Z',
            'ž' => 'z',
            'ſ' => 's',
            // Decompositions for Latin Extended-B.
            'Ș' => 'S',
            'ș' => 's',
            'Ț' => 'T',
            'ț' => 't',
            // Euro sign.
            '€' => 'E',
            // GBP (Pound) sign.
            '£' => '',
            // Vowels with diacritic (Vietnamese).
            // Unmarked.
            'Ơ' => 'O',
            'ơ' => 'o',
            'Ư' => 'U',
            'ư' => 'u',
            // Grave accent.
            'Ầ' => 'A',
            'ầ' => 'a',
            'Ằ' => 'A',
            'ằ' => 'a',
            'Ề' => 'E',
            'ề' => 'e',
            'Ồ' => 'O',
            'ồ' => 'o',
            'Ờ' => 'O',
            'ờ' => 'o',
            'Ừ' => 'U',
            'ừ' => 'u',
            'Ỳ' => 'Y',
            'ỳ' => 'y',
            // Hook.
            'Ả' => 'A',
            'ả' => 'a',
            'Ẩ' => 'A',
            'ẩ' => 'a',
            'Ẳ' => 'A',
            'ẳ' => 'a',
            'Ẻ' => 'E',
            'ẻ' => 'e',
            'Ể' => 'E',
            'ể' => 'e',
            'Ỉ' => 'I',
            'ỉ' => 'i',
            'Ỏ' => 'O',
            'ỏ' => 'o',
            'Ổ' => 'O',
            'ổ' => 'o',
            'Ở' => 'O',
            'ở' => 'o',
            'Ủ' => 'U',
            'ủ' => 'u',
            'Ử' => 'U',
            'ử' => 'u',
            'Ỷ' => 'Y',
            'ỷ' => 'y',
            // Tilde.
            'Ẫ' => 'A',
            'ẫ' => 'a',
            'Ẵ' => 'A',
            'ẵ' => 'a',
            'Ẽ' => 'E',
            'ẽ' => 'e',
            'Ễ' => 'E',
            'ễ' => 'e',
            'Ỗ' => 'O',
            'ỗ' => 'o',
            'Ỡ' => 'O',
            'ỡ' => 'o',
            'Ữ' => 'U',
            'ữ' => 'u',
            'Ỹ' => 'Y',
            'ỹ' => 'y',
            // Acute accent.
            'Ấ' => 'A',
            'ấ' => 'a',
            'Ắ' => 'A',
            'ắ' => 'a',
            'Ế' => 'E',
            'ế' => 'e',
            'Ố' => 'O',
            'ố' => 'o',
            'Ớ' => 'O',
            'ớ' => 'o',
            'Ứ' => 'U',
            'ứ' => 'u',
            // Dot below.
            'Ạ' => 'A',
            'ạ' => 'a',
            'Ậ' => 'A',
            'ậ' => 'a',
            'Ặ' => 'A',
            'ặ' => 'a',
            'Ẹ' => 'E',
            'ẹ' => 'e',
            'Ệ' => 'E',
            'ệ' => 'e',
            'Ị' => 'I',
            'ị' => 'i',
            'Ọ' => 'O',
            'ọ' => 'o',
            'Ộ' => 'O',
            'ộ' => 'o',
            'Ợ' => 'O',
            'ợ' => 'o',
            'Ụ' => 'U',
            'ụ' => 'u',
            'Ự' => 'U',
            'ự' => 'u',
            'Ỵ' => 'Y',
            'ỵ' => 'y',
            // Vowels with diacritic (Chinese, Hanyu Pinyin).
            'ɑ' => 'a',
            // Macron.
            'Ǖ' => 'U',
            'ǖ' => 'u',
            // Acute accent.
            'Ǘ' => 'U',
            'ǘ' => 'u',
            // Caron.
            'Ǎ' => 'A',
            'ǎ' => 'a',
            'Ǐ' => 'I',
            'ǐ' => 'i',
            'Ǒ' => 'O',
            'ǒ' => 'o',
            'Ǔ' => 'U',
            'ǔ' => 'u',
            'Ǚ' => 'U',
            'ǚ' => 'u',
            // Grave accent.
            'Ǜ' => 'U',
            'ǜ' => 'u',
        );

        $filename = str_replace( $special_chars, '', $filename );
        $filename = str_replace( array_keys($chars), $chars, $filename );
        $filename = str_replace( array( '%20', '+' ), '-', $filename );
        $filename = preg_replace( '/[\r\n\t-]+/', '-', $filename );
        $filename = trim( $filename, '.-_' );

        return $filename;
    }

	/**
	 * Calculates the display value for file sizes (in bytes) based on the given unit
	 * @param int $bytes File size in bytes
	 * @param string $unit Desired unit
	 * @param int|"auto" $precision Desired precision (default: "auto" - 1 for kB, else 2)
	 * @return float File size in the given unit
	 * @throws Exception When an invalid unit is passed
	 */
	public static function getDisplayFileSize($bytes, $unit = "MB", $precision = "auto") {
		$fs = $bytes;
		$auto = $precision == "auto";
		$precision = is_int($precision) ? intval($precision) : 2;
		switch ($unit) {
			case "KiB": $fs = $bytes / 1024; if ($auto) $precision = 1; break;
			case "MiB": $fs = $bytes / 1048576; break;
			case "GiB": $fs = $bytes / 1073741824; break; 
			case "TiB": $fs = $bytes / 1099511627776; break; 
			case "PiB": $fs = $bytes / 1125899906842624; break; 
			case "kB":  $fs = $bytes / 1000; if ($auto) $precision = 1; break;
			case "MB":  $fs = $bytes / 1000000; break;
			case "GB":  $fs = $bytes / 1000000000; break; 
			case "TB":  $fs = $bytes / 1000000000000; break; 
			case "PB":  $fs = $bytes / 1000000000000000; break; 
			default:
				throw new Exception("Invalid unit. Use kB/kiB, MB/MiB, GB/GiB, TB/TiB only!");
		}
		return round_up($fs, $precision);
	}

	/** Metric (base 10) file sizes and units (kB - PB) */
	const filesize_metric_units = [
		1000000000000000 => "PB",
		1000000000000 => "TB",
		1000000000 => "GB",
		1000000 => "MB",
		1000 => "kB"
	];
	/** IEC (base 2) file sizes and units (KiB - PiB) */
	const filesize_iec_units = [
		1125899906842624 => "PiB",
		1099511627776 => "TiB",
		1073741824 => "GiB",
		1048576 => "MiB",
		1024 => "KiB"
	];
	/**
	 * Determines the best unit for the given file size in bytes
	 * @param int $bytes Number of bytes
	 * @param string $units 'metric' (default) or 'iec' 
	 * @param float $cutoff A value between 0.1 and 0.9, defaults to 0.5 (0.5 means that, e.g. 500,000 bytes will be give MB, while 400,000 bytes will give kB)
	 * @return string 
	 */
	public static function getBestFileSizeUnit($bytes, $units = 'metric', $cutoff = 0.5) {
		$fs = $bytes / min(0.9, max(0.1, $cutoff));
		$units = $units == 'iec' ? self::filesize_iec_units : self::filesize_metric_units;
		foreach ($units as $size => $unit) {
			if ($fs > $size) return $unit;
		}
		return $unit;
	}

	/** Image types (excluding PDF) that can be shown/previewed inline */
	const supported_image_types = ["jpeg", "jpg", "jpe", "gif", "png", "tif", "tiff", "bmp", "webp", "svg"];

	/**
	 * Checks if the file type (by extensions) is supported for inline preview
	 * @param string $ext The file extension
	 * @return string|false The mime-type of the image or false if not supported
	 */
	public static function isPreviewSupportedFileType($ext) {
		$ext = strtolower($ext);
		if ($ext == "pdf" || in_array($ext, self::supported_image_types)) return self::get_mime_types()[$ext];
		return false;
	}

    /**
     * Returns HTML for a preview button
     * @param string $ext File extension
     * @param string $class Additional classes to add
     * @return string Button HTML or empty string when the file type is not supported
     */
    private static function getPreviewButton($ext, $class = "") {
        $type = Files::isPreviewSupportedFileType($ext);
        if (!$type) return "";
        return RCView::button([
                "class" => "btn btn-secondary btn-xs rc-preview-file $class",
                "type" => "button",
                "data-file-type" => $type
            ],
            RCView::fa("fa-solid fa-magnifying-glass show-preview") .
            RCView::fa("fa-solid fa-xmark hide-preview") .
            RCView::tt("data_entry_605", "span", ["class" => "visually-hidden"]) // Toggle preview of this file
        );
    }

    public static function getFileUniqueId() {
        return "file-id-".Crypto::getGuid();
    }

	public static function getFileDownloadLink($edoc_info, $href, $extra_attrs, $add_preview = false, $desc_attachment = false) {
		// Set file size
		$fs_unit = Files::getBestFileSizeUnit($edoc_info["doc_size"]);
		$fs_value = Files::getDisplayFileSize($edoc_info["doc_size"], $fs_unit);
		// Display link for downloading file (with preview button for supported types)
		// when the @INLINE-PREVIEW action tag is present
		$preview_btn = $add_preview ? self::getPreviewButton($edoc_info["file_extension"]) : "";
		$attr = [
			"class" => ($desc_attachment ? "div_attach " : "") . "file-download-link" // div_attach = legacy
		];
		if ($preview_btn != "") {
			$attr["data-file-preview"] = self::getFileUniqueId();
		}
		return RCView::div($attr,
			($desc_attachment ? RCView::tt("design_205") : ""). // Attachment:
			RCView::getFileIcon($edoc_info["file_extension"]) .
			RCView::a(array_merge([
				"class" => ($desc_attachment ? "rc_attach " : "") . "file-link",
				"target" => "_blank",
				"href" => $href
			], $extra_attrs),
				RCView::span(["data-kind" => "file-name"], $edoc_info["doc_name"]) 
			) .
			RCView::span([
					"aria-hidden" => "true"
				], "(".RCView::span(["data-kind" => "file-size", "data-unit" => $fs_unit], $fs_value)." $fs_unit)"
			) .
			$preview_btn
		);
	}

    // Generate a download link for an edoc file
    public static function getDownloadLink($doc_id, $project_id, $useSurveyEndpoint=false)
    {
        if (!isinteger($doc_id) || !isinteger($project_id)) return false;
        $prefix = $useSurveyEndpoint ? APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=".urlencode("DataEntry/file_download.php") : APP_PATH_WEBROOT . "DataEntry/file_download.php?pid=$project_id";
        $downloadUrl = $prefix . "&id=$doc_id&doc_id_hash=" . Files::docIdHash($doc_id) . "&doc_id_hash2=" . base64_encode(encrypt(Files::docIdHash($doc_id)));
        return $downloadUrl;
    }

	/**
	 * make a data structure that is compatible
	 * with the uploadFile method.
	 * size of the file is calculated using the path.
	 * mimeType can be inferred by the file name
	 *
	 * @param string $name
	 * @param string $path
	 * @param string $mimeType
	 * @return void
	 */
	public static function makeFileStructure($name, $path, $mimeType=null) {
		if(!file_exists($path)) {
			exit("ERROR: Could not read the file $path");
		}
		$mimeType = $mimeType ?? self::get_file_extension_by_mime_type($name);
		$file =[
		  'name' => $name,// name: "Unknown-1.png"
		  'type' => $mimeType,// type: "image/png"
		  'tmp_name' => $path, // tmp_name: "/tmp/php9IO1Qy"
		  'error' => UPLOAD_ERR_OK,// error: 0
		  'size' => $zipSize = filesize($path),// size: 185269
		];
		return $file;
	}

	/**
	 * recursively try to generate a unique name
	 *
	 * @param string $destFolder
	 * @param integer $maxLength
	 * @param string $extension
	 * @param string $prefix
	 * @return string
	 */
	public static function generateUniqueFileName($destFolder, $maxLength = 20, $prefix = '', $extension = '') {
		$charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$fileName = $prefix . substr(str_shuffle($charset), 0, $maxLength);
		
		if (file_exists($destFolder . '/' . $fileName . $extension)) {
			$args = func_get_args();
			$fileName = self::generateUniqueFileName(...$args);
		}
		
		return $fileName;
	}

	/**
	 * write data to a file in a destination folder.
	 * A unique name is generated if  no name is provided.
	 *
	 * @param string $data
	 * @param string $destFolder
	 * @param string $name
	 * @return string path to the uploaded file
	 */
	public static function uploadFromString($data, $destFolder, $name=null) {
		$destFolder = rtrim($destFolder,'/'); // normalize temp dir
		if(!file_exists($destFolder)) {
			throw new Exception("Error: upload directory does not exist", 1);
		}
		if(is_null($name)) $name = self::generateUniqueFileName($destFolder);
		$fullPath = "{$destFolder}/{$name}";
		$size = file_put_contents($fullPath, $data);

		if($size===false) {
			throw new Exception("Error: could not upload file", 1);
		}
		return $fullPath;
	}

    // Return boolean if using local file storage (or alternatively Google Cloud Storage via Google App Engine hosting) and also if subfolder storage is enabled
    public static function detectProjectLevelLocalStorageGlobal()
    {
        return (
            // Using local storage?
            ($GLOBALS['edoc_storage_option'] == '0' || $GLOBALS['edoc_storage_option'] == '3')
            // Global setting enabled?
            && $GLOBALS['local_storage_use_project_subfolder'] == '1'
            // Do not allow subfolder storage for GitHub Actions
            && !isset($_SERVER['MYSQL_REDCAP_CI_HOSTNAME'])
        );
    }

    // If using local file storage, determine if we should use subfolder storage by PID
    public static function detectProjectLevelLocalStorage($project_id)
    {
        // Make sure we're using local storage and that the global setting is enabled
        if (!self::detectProjectLevelLocalStorageGlobal() || !isinteger($project_id)) return false;
        // Try to create the subfolder
        return self::createProjectLevelLocalStorageSubfolder("pid".$project_id);
    }

    // If using local file storage, create a subfolder by name
    public static function createProjectLevelLocalStorageSubfolder($subfolderName, $createDeleteTestFile=true)
    {
        // Make sure we're using local storage and that the global setting is enabled
        if (!self::detectProjectLevelLocalStorageGlobal()) return false;
        // Set full path of new folder
        $subfolderNameFull = EDOC_PATH . $subfolderName;
        // Try to create the subfolder (still return true if the folder already exists, just in case)
        $createdSubfolder = (is_dir($subfolderNameFull) || mkdir($subfolderNameFull));
        // If we're not testing the creation/deletion of a file, stop here
        if (!$createDeleteTestFile) return $createdSubfolder;
        // Try creating a test file and also deleting it
        return ($createdSubfolder && isDirWritable($subfolderNameFull, true));
    }

    // If using local file storage with subfolders, get a project's subfolder name
    public static function getLocalStorageSubfolder($project_id, $appendDirectorySeparator=false)
    {
        // Make sure we're using local storage and that the global setting is enabled
        if (!self::detectProjectLevelLocalStorageGlobal() || !isinteger($project_id)) return "";
        // Get value from projects table
        $subfolder = ""; // default
        $sql = "select local_storage_subfolder from redcap_projects where project_id = $project_id";
        $q = db_query($sql);
        if ($q && db_num_rows($q) > 0) {
            $subfolder = trim(db_result($q) ?? "");
        }
        if ($subfolder != "" && $appendDirectorySeparator) {
            $subfolder .= DS;
        }
        return $subfolder;
    }
}