<?php

class FileRepository
{
    // Rename a file in the File Repository page
    public static function rename()
    {
        global $lang, $user_rights;
        if (!(isset($_POST['doc_id']) && isinteger($_POST['doc_id'])) || !isset($_POST['name']) || $_POST['name'] == '') exit('0');
        if (!FileRepository::userHasFileAccess($_POST['doc_id'])) exit('0');
        // Check file's DAG access
        if ($user_rights['group_id'] != '') {
            $dag_id = self::folderHasDagRestriction(self::getFolderIdByDocId($_POST['doc_id']));
            if ($dag_id != false && $dag_id != $user_rights['group_id']) {
                exit('0');
            }
        }
        // Check file's role access
        if ($user_rights['role_id'] != '') {
            $role_id = self::folderHasRoleRestriction(self::getFolderIdByDocId($_POST['doc_id']));
            if ($role_id != false && $role_id != $user_rights['role_id']) {
                exit('0');
            }
        }
        // Sanitize filename
        $_POST['name'] = strip_tags($_POST['name']);
        // Get doc_id from docs_id
        $doc_id = self::getDocIdFromDocsId($_POST['doc_id']);
        // Check if filename exists already. If so, return "2" to inform user that filename is already taken in this folder.
        $sql = "select count(*) from redcap_docs d
                left join redcap_docs_folders_files f on f.docs_id = d.docs_id                    
                left join redcap_docs_to_edocs e ON e.docs_id = d.docs_id
                left join redcap_edocs_metadata m ON m.doc_id = e.doc_id
                where d.project_id = " . PROJECT_ID . " and m.delete_date is null
                and f.folder_id " . (!isinteger($_POST['folder_id']) ? "is null" : "= ".$_POST['folder_id']) . " and d.docs_name = '" . db_escape($_POST['name']) . "'
                group by d.docs_name";
        $q = db_query($sql);
        $filecount = db_result($q, 0);
        if ($filecount >= 1) exit('2');
        // Make sure the file extension is not changed (could be used by bypass dangerious file extension prevention)
        $sql = "select doc_name from redcap_edocs_metadata WHERE doc_id = $doc_id AND project_id = ".PROJECT_ID;
        $originalName = db_result(db_query($sql), 0);
        if (getFileExt($originalName) != getFileExt($_POST['name'])) exit('3');
        // Rename it
        self::renameFile($doc_id, $_POST['name']);
        // Logging
        Logging::logEvent($sql,"redcap_docs","MANAGE",$_POST['doc_id'],$_POST['name'],"Rename file in File Repository");
    }


    // Get the edocs_metadata "doc_id" from the docs "docs_id"
    public static function getDocIdFromDocsId($docs_id)
    {
        if (!isinteger($docs_id)) return null;
        $sql = "select doc_id from redcap_docs_to_edocs where docs_id = $docs_id";
        $q = db_query($sql);
        return db_num_rows($q) ? db_result($q, 0) : null;
    }


    // Get the docs "docs_id" from the edocs_metadata "doc_id"
    public static function getDocsIdFromDocId($doc_id)
    {
        if (!isinteger($doc_id)) return null;
        $sql = "select docs_id from redcap_docs_to_edocs where doc_id = $doc_id";
        $q = db_query($sql);
        return db_num_rows($q) ? db_result($q, 0) : null;
    }


    // Delete a folder from the File Repository
    public static function deleteFolder()
    {
        if (!(isset($_POST['delete']) && isinteger($_POST['delete']))) exit('0');
        if (!FileRepository::userHasFolderAccess($_POST['delete'])) exit('0');
        // First, get folder name for logging
        $sql = "select name from redcap_docs_folders where folder_id = {$_POST['delete']} and project_id = ".PROJECT_ID;
        $folder_name = db_result(db_query($sql), 0);
        // Delete it
        $sql = "update redcap_docs_folders set deleted = 1 where folder_id = {$_POST['delete']} and project_id = ".PROJECT_ID;
        $q = db_query($sql);
        Logging::logEvent($sql, "redcap_docs_folders", "MANAGE", $_POST['delete'], $folder_name, "Delete folder from File Repository");
    }


    // Create a folder in the File Repository
    public static function createFolder()
    {
        global $lang, $Proj;
        if (!isset($_POST['name']) || $_POST['name'] == '') {
            exit(defined("API") ? RestUtility::sendResponse(400, "new folder 'name' not provided") : '0');
        }
        if ($_POST['folder_id'] != '' && !(isset($_POST['folder_id']) && isinteger($_POST['folder_id']))) {
            exit(defined("API") ? RestUtility::sendResponse(400, "folder_id is not valid") : '0');
        }
        if (!FileRepository::userHasFolderAccess($_POST['folder_id'])) {
            exit(defined("API") ? RestUtility::sendResponse(400, "The File Repository folder folder_id={$_POST['folder_id']} does not exist or else you do not have permission to that folder because it is DAG-restricted or Role-restricted.") : '0');
        }
        $_POST['name'] = substr(Files::sanitizeFileName($_POST['name']), 0, 150);
        // Make sure folder name doesn't already exist in the current folder
        $sql = "select 1 from redcap_docs_folders  where project_id = ".PROJECT_ID." 
                and name = '".db_escape($_POST['name'])."' and deleted = 0 
                and parent_folder_id ".($_POST['folder_id'] == '' ? "is null" : " = ".$_POST['folder_id'])." 
                limit 1";
        if (db_num_rows(db_query($sql))) {
            // Duplicate folder name exists
            $msg = $lang['docs_1078']." \"<b>".RCView::escape($_POST['name'])."</b>\" ".$lang['docs_1079'];
            exit(defined("API") ? RestUtility::sendResponse(400, strip_tags($msg)) : $msg);
        }
        // Apply DAG and Role restriction, if applicable
        $dags = $Proj->getGroups();
        $roles = UserRights::getRoles();
        $dag_id = (isset($_POST['dag_id']) && isset($dags[$_POST['dag_id']])) ? $_POST['dag_id'] : null;
        $role_id = (isset($_POST['role_id']) && isset($roles[$_POST['role_id']])) ? $_POST['role_id'] : null;
        $admin_only = (isset($_POST['admin_only']) && $_POST['admin_only'] == '1' && UserRights::isSuperUserNotImpersonator()) ? '1' : '0';
        if ($admin_only == '1') {
	        $dag_id = $role_id = null;
        }
        // Create folder
        $sql = "insert into redcap_docs_folders (project_id, name, parent_folder_id, dag_id, role_id, admin_only) values 
                (".PROJECT_ID.", '".db_escape($_POST['name'])."', ".checkNull($_POST['folder_id']).", ".checkNull($dag_id).", ".checkNull($role_id).", ".checkNull($admin_only).")";
        if (!db_query($sql)) {
            exit(defined("API") ? RestUtility::sendResponse(400, "unknown error occurred") : '0');
        }
        $newfolderid = db_insert_id();
        // Logging
        list ($folderName, $nothing) = self::getFolderNameAndParentFromId($_POST['folder_id']);
        if ($folderName == null) $folderName = $lang['docs_1131'];
        $logDescrip = "Create folder in File Repository";
        $logDescrip .= defined("API") ? " (API{$GLOBALS['playground']})" : "";
        $logDataValues = $_POST['name'];
        if (isinteger($dag_id)) $logDataValues .= ", {$lang['docs_91']} ".$dags[$dag_id];
        if (isinteger($role_id)) $logDataValues .= ", {$lang['docs_1085']} ".$roles[$role_id]['role_name'];
        Logging::logEvent($sql, "redcap_docs_folders", "MANAGE", $newfolderid, $logDataValues, $logDescrip);
        // Return "1" or return $newfolderid for API
        if (defined("API")) {
            return $newfolderid;
        }
        exit("1");
    }


    // Rename a folder in the File Repository
    public static function renameFolder()
    {
        global $lang, $user_rights;
        if (!(isset($_POST['folder_id']) && isinteger($_POST['folder_id']))) exit('0');
        if (!FileRepository::userHasFolderAccess($_POST['folder_id'])) exit('0');
        // Check file's DAG access
        if ($user_rights['group_id'] != '') {
            $dag_id = self::folderHasDagRestriction($_POST['folder_id']);
            if ($dag_id != false && $dag_id != $user_rights['group_id']) {
                exit('0');
            }
        }
        // Check file's role access
        if ($user_rights['role_id'] != '') {
            $role_id = self::folderHasRoleRestriction($_POST['folder_id']);
            if ($role_id != false && $role_id != $user_rights['role_id']) {
                exit('0');
            }
        }
        // Update it
        $sql = "update redcap_docs_folders set name = '".db_escape($_POST['name'])."' where folder_id = {$_POST['folder_id']} and project_id = ".PROJECT_ID;
        if (!db_query($sql)) {
            if (db_errno() == 1062) {
                exit($lang['docs_1080']." \"<b>".RCView::escape($_POST['name'])."</b>\" ".$lang['docs_1081']);
            } else {
                exit("0");
            }
        }
        list ($folderName, $nothing) = self::getFolderNameAndParentFromId($_POST['folder_id']);
        if ($folderName == null) $folderName = $lang['docs_1131'];
        Logging::logEvent($sql, "redcap_docs_folders", "MANAGE", $_POST['folder_id'], $folderName, "Rename folder in File Repository");
        exit("1");
    }


    // Restore a file in the File Repository
    public static function restore()
    {
        global $user_rights, $lang;
        if (!(isset($_POST['doc_id']) && isinteger($_POST['doc_id']))) exit('0');
        if (!FileRepository::userHasFileAccess($_POST['doc_id'])) exit('0');
        // Check file's DAG access
        if ($user_rights['group_id'] != '') {
            $dag_id = self::folderHasDagRestriction(self::getFolderIdByDocId($_POST['doc_id']));
            if ($dag_id != false && $dag_id != $user_rights['group_id']) {
                exit('0');
            }
        }
        // Check file's role access
        if ($user_rights['role_id'] != '') {
            $role_id = self::folderHasRoleRestriction(self::getFolderIdByDocId($_POST['doc_id']));
            if ($role_id != false && $role_id != $user_rights['role_id']) {
                exit('0');
            }
        }
	    // Check Admin restriction access (if applicable)
	    if (!UserRights::isSuperUserNotImpersonator() && self::folderHasAdminRestriction(self::getFolderIdByDocId($_POST['doc_id']))) {
		    exit('0');
	    }
        // Get info and delete
        $sql = "SELECT d.docs_id, e.doc_id, m.stored_name, d.docs_comment, d.docs_size, d.docs_name
                FROM redcap_docs d
                LEFT JOIN redcap_docs_to_edocs e ON e.docs_id = d.docs_id
                LEFT JOIN redcap_edocs_metadata m ON m.doc_id = e.doc_id
                WHERE d.docs_id = {$_POST['doc_id']} AND m.delete_date is not null AND m.date_deleted_server is null
                AND d.project_id = ".PROJECT_ID;
        $result = db_query($sql);
        if ($result) {
            $data = db_fetch_object($result);
            if ($data && isinteger($data->doc_id))
            {
                // Ensure that restoring/adding this file will not exceed the project-level storage limit
                $getMaxStorage = self::getMaxStorage(PROJECT_ID);
                $currentStorage = rounddown(self::getCurrentUsage(PROJECT_ID)*1024*1024); // bytes
                if (is_numeric($getMaxStorage) && $currentStorage+$data->docs_size > ($getMaxStorage*1024*1024)) {
                    exit('<code>'.$data->docs_name.'</code><br>'.$lang['docs_1113']." <b>".($getMaxStorage)." MB</b>".$lang['period']." ".$lang['docs_1112']);
                }
                // Undo the delete date
                $sql = "UPDATE redcap_edocs_metadata SET delete_date = NULL WHERE doc_id = $data->doc_id AND project_id=".PROJECT_ID;
                if (db_query($sql)) {
                    Logging::logEvent($sql, "redcap_docs", "MANAGE", $data->doc_id, $data->docs_name, "Restore file in File Repository");
                    // Set who restored the file in the comments
                    $comment = trim(trim($data->docs_comment ?? "")."\n".$lang['docs_1098']." ".USERID." ".$lang['global_15']." ".NOW.$lang['period']);
                    $sql2 = "UPDATE redcap_docs SET docs_comment = '".db_escape($comment)."' 
                             WHERE docs_id = {$_POST['doc_id']} AND project_id = ".PROJECT_ID;
                    db_query($sql2);
                    // RESTORE FOLDER TOO: If the restored file exists in a deleted folder, then we need to also restore its folder and all parent folders to make it accessible again
                    $sql = "select folder_id from redcap_docs_folders_files where docs_id = " . $data->doc_id;
                    $q = db_query($sql);
                    $folder_id = $fileFolderId = ($q && db_num_rows($q) > 0) ? db_result($q, 0, 'folder_id') : null;
                    while ($folder_id != null)
                    {
                        // Is this folder deleted and does it have a parent?
                        $sql = "select parent_folder_id, deleted from redcap_docs_folders where folder_id = $folder_id and project_id = ".PROJECT_ID;
                        $q = db_query($sql);
                        // If this folder is deleted, then restore it
                        $deleted = db_result($q, 0, 'deleted');
                        $parent_folder_id = db_result($q, 0, 'parent_folder_id');
                        if ($deleted) {
                            $sql = "update redcap_docs_folders set deleted = 0 where folder_id = $folder_id and project_id = ".PROJECT_ID;
                            $q = db_query($sql);
                        }
                        // Now get the parent folder to go and try to restore it (if deleted) - Set folder_id for next loop
                        $folder_id = $parent_folder_id;
                    };
                    // Check if filename exists already
                    $filename = self::checkFilenameExists($data->docs_name, $data->doc_id, $fileFolderId);
                    if ($filename != $data->docs_name) self::renameFile($data->doc_id, $filename);
                    exit('1');
                }
            }
        }
        exit('0');
    }


    // Edit a file's comment
    public static function editComment($doc_id, $comment)
    {
        if (!(isset($doc_id) && isinteger($doc_id))) return false;
        $sql = "UPDATE redcap_docs
				SET docs_comment = '".db_escape($comment)."'
				WHERE docs_id = $doc_id and project_id = ".PROJECT_ID;
        if (!db_query($sql)) return false;
        $edoc_id = self::getDocIdFromDocsId($doc_id);
        $docName = Files::getEdocName(self::getDocIdFromDocsId($doc_id));
        Logging::logEvent("", "redcap_docs", "MANAGE", $doc_id, $docName.", comment: $comment", "Edit comment for file in File Repository");
        return true;
    }


    // Delete multiple files from the File Repository
    public static function deleteMultiple($idsCsv)
    {
        if ($idsCsv == null) exit('0');
        $numFailed = 0;
        foreach(explode(",", $idsCsv) as $thisId) {
            $thisId = trim($thisId);
            if (!isinteger($thisId)) continue;
            // Delete each file
            if (!self::delete($thisId)) {
                $numFailed++;
            }
        }
        exit($numFailed > 0 ? '0' : '1');
    }

    // Delete file from the File Repository page PERMANENTLY (admins only)
    public static function deleteNow($doc_id)
    {
        if (!(isset($doc_id) && isinteger($doc_id))) return false;
        if (!UserRights::isSuperUserNotImpersonator()) return false;
        $edoc_id = self::getDocIdFromDocsId($doc_id);
        if (!isinteger($edoc_id)) return false;
        $sql = "UPDATE redcap_edocs_metadata
				SET date_deleted_server = '".NOW."'
				WHERE doc_id = $edoc_id and delete_date is not null";
        if (!db_query($sql)) return false;
        $project_id = Files::getEdocProjectId($edoc_id);
        $docName = Files::getEdocName($edoc_id);
        $storedName = Files::getEdocName($edoc_id, true);
        Files::deleteFilePermanently($storedName, $project_id);
        Logging::logEvent("", "redcap_docs", "MANAGE", $doc_id, $docName, "Permanently delete file from File Repository");
        return true;
    }

    // Delete a file from the File Repository
    public static function delete($id)
    {
        global $user_rights, $lang;
        if (!(isset($id) && isinteger($id))) return false;
        if (!FileRepository::userHasFileAccess($id)) return false;
		$folder_id = self::getFolderIdByDocId($id);
        // Check file's DAG access
        if ($user_rights['group_id'] != '') {
            $dag_id = self::folderHasDagRestriction($folder_id);
            if ($dag_id != false && $dag_id != $user_rights['group_id']) {
                return false;
            }
        }
        // Check file's role access
        if ($user_rights['role_id'] != '') {
            $role_id = self::folderHasRoleRestriction($folder_id);
            if ($role_id != false && $role_id != $user_rights['role_id']) {
                return false;
            }
        }
	    // Check Admin restriction access (if applicable)
	    if (!UserRights::isSuperUserNotImpersonator() && self::folderHasAdminRestriction(self::getFolderIdByDocId($id))) {
		    exit('0');
	    }
        // Get info and delete
        $sql = "SELECT d.docs_id, e.doc_id, m.stored_name, d.docs_comment, d.docs_name
                FROM redcap_docs d
                LEFT JOIN redcap_docs_to_edocs e ON e.docs_id = d.docs_id
                LEFT JOIN redcap_edocs_metadata m ON m.doc_id = e.doc_id
                WHERE d.docs_id = {$id} AND d.project_id = ".PROJECT_ID;
        $result = db_query($sql);
        if ($result) {
            $data = db_fetch_object($result);
            if ($data)
            {
                if ($data->doc_id != NULL) {
                    list ($folderName, $nothing) = self::getFolderNameAndParentFromId($folder_id);
                    if ($folderName == null) $folderName = $lang['docs_1131'];
                    // Set date deletion timestamp to NOW
                    $sql = "UPDATE redcap_edocs_metadata SET delete_date = '".NOW."' 
                            WHERE doc_id = $data->doc_id AND project_id = ".PROJECT_ID;
                    db_query($sql);
                    // Set who deleted the file in the comments
                    $comment = trim(trim($data->docs_comment ?? "")."\n".$lang['docs_1097']." ".USERID." ".$lang['global_15']." ".NOW.$lang['period']);
                    $sql2 = "UPDATE redcap_docs SET docs_comment = '".db_escape($comment)."' 
                             WHERE docs_id = {$id} AND project_id = ".PROJECT_ID;
                    db_query($sql2);
                    // Logging
                    Logging::logEvent($sql.";\n$sql2", "redcap_docs", "MANAGE", $id, "{$data->docs_name}, folder $folderName", "Delete file from File Repository");
                    return true;
                }
            }
        }
        return false;
    }


    // Upload a file to the File Repository
    public static function upload()
    {
        global $lang, $user_rights;

        // check to see if a file was uploaded
		if ($GLOBALS['file_repository_enabled'] != '1') {
			exit(defined("API") ? RestUtility::sendResponse(400, "Users are not allowed to upload files to the File Repository.") : '0');
		}

        // check to see if a file was uploaded
		if (!(isset($_FILES['file']) && is_array($_FILES['file']))) {
			exit(defined("API") ? RestUtility::sendResponse(400, "No valid file was uploaded") : '0');
		}

        // make sure there were no errors associated with the uploaded file
		if ($_FILES['file']['error'] != 0) {
			exit(defined("API") ? RestUtility::sendResponse(400, "There was a problem with the uploaded file") : '0');
		}

        // Check folder_id, if passed. Check file's DAG access and role access.
        $folder_id = (isset($_POST['folder_id']) && isinteger($_POST['folder_id'])) ? $_POST['folder_id'] : null;
        if (!self::userHasFolderAccess($folder_id)) {
			exit(defined("API") ? RestUtility::sendResponse(400, "The File Repository folder folder_id={$folder_id} does not exist or else you do not have permission to that folder because it is DAG-restricted or Role-restricted.") : '0');
		}

        // Ensure file is not too large
		$filesize = $_FILES['file']['size']; // bytes
		$filesizeMB = $_FILES['file']['size']/1024/1024; // MB
        if ($filesizeMB > maxUploadSizeFileRepository()) {
            // Delete uploaded file from server
            unlink($_FILES['file']['tmp_name']);
            // Set error msg
           $msg = '<code>'.$_FILES['file']['name'].'</code><br>'.$lang['sendit_03'] . ' (<b>' . round_up($filesizeMB) . ' MB</b>)'.$lang['period'].' ' .
                  $lang['sendit_04'] . ' ' . maxUploadSizeFileRepository() . ' MB ' . $lang['sendit_05'];
			exit(defined("API") ? RestUtility::sendResponse(400, strip_tags($msg)) : $msg);
        }

        // Ensure that adding this file will not exceed the project-level storage limit
        $getMaxStorage = self::getMaxStorage(PROJECT_ID);
        $currentStorage = rounddown(self::getCurrentUsage(PROJECT_ID)*1024*1024); // bytes
        if (is_numeric($getMaxStorage) && $currentStorage+$filesize > ($getMaxStorage*1024*1024)) {
			$msg = '<code>'.$_FILES['file']['name'].'</code><br>'.$lang['docs_1111']." <b>".($getMaxStorage)." MB</b>".$lang['period']." ".$lang['docs_1112'];
			exit(defined("API") ? RestUtility::sendResponse(400, strip_tags($msg)) : $msg);
        }

        // Store the file
        $doc_id = Files::uploadFile($_FILES['file'], PROJECT_ID);
        if ($doc_id == 0) {
            exit(defined("API") ? RestUtility::sendResponse(400, $lang['docs_1135']) : '0');
        }
        REDCap::addFileToRepository($doc_id, PROJECT_ID, $lang['docs_1124']." ".USERID.$lang['period']);
        // Get docs_id from doc_id (for logging purposes)
        $sql = "select docs_id from redcap_docs_to_edocs where doc_id = $doc_id";
        $docs_id = db_result(db_query($sql), 0);
        // If file was stored in a folder, add row to table
        $sql2 = "";
        if ($folder_id != null) {
            $sql2 = "insert into redcap_docs_folders_files (docs_id, folder_id) values ($docs_id, $folder_id)";
            db_query($sql2);
        }
        list ($folderName, $nothing) = self::getFolderNameAndParentFromId($folder_id);
        if ($folderName == null) $folderName = $lang['docs_1131'];
        // Get original filename
        $filenameOrig = db_result(db_query("select docs_name from redcap_docs where docs_id = $docs_id and project_id = " . PROJECT_ID), 0);
        // Check if filename exists already
        $filename = self::checkFilenameExists($filenameOrig, $doc_id, $folder_id);
        if ($filename != $filenameOrig) self::renameFile($doc_id, $filename);
        // Logging
        $descripAPI = defined("API") ? " (API{$GLOBALS['playground']})" : "";
        Logging::logEvent($sql.";".$sql2,"redcap_docs","MANAGE",$docs_id,"$filename, folder $folderName","Upload file to File Repository{$descripAPI}");
        // Return doc_id or 200 status for API
		exit(defined("API") ? RestUtility::sendResponse(200) : $doc_id."");
    }

    // Check for filename duplication of files in same folder. Rename new filename if the filename provided is already taken.
    public static function checkFilenameExists($filenameOrig, $doc_id, $folder_id=null)
    {
        // Get docs_id from doc_id
        $sql = "select docs_id from redcap_docs_to_edocs where doc_id = $doc_id";
        $docs_id = db_result(db_query($sql), 0);
        // Get filename parts
        $fileext = getFileExt($filenameOrig);
        if ($fileext != "") $fileext = ".".$fileext;
        list ($filebaseOrig, $nothing) = explode_right($fileext, $filenameOrig, 2);
        $filebaseOrig = trim($filebaseOrig);
        // Check if the filename duplicates an existing filename in the same folder. If so, rename by appending (2) etc to end of filename.
        $increment = 0;
        $loopbuffer = 0;
        $filename = $filenameOrig;
        if (!isinteger($folder_id)) $folder_id = null;
        do {
            $sql = "select count(*) from redcap_docs d
                    left join redcap_docs_folders_files f on f.docs_id = d.docs_id                    
			        left join redcap_docs_to_edocs e ON e.docs_id = d.docs_id
			        left join redcap_edocs_metadata m ON m.doc_id = e.doc_id
                    where d.project_id = " . PROJECT_ID . " and m.delete_date is null
                    and f.folder_id " . ($folder_id == null ? "is null" : "= $folder_id") . " and d.docs_name = '" . db_escape($filename) . "'
                    group by d.docs_name";
            $q = db_query($sql);
            $filecount = db_result($q, 0);
            if (($filecount+$loopbuffer) >= 2) {
                $increment++;
                // Set for next loop to see if unique
                $filename = "$filebaseOrig ($increment)$fileext";
                $loopbuffer = 1;
            } else {
                // Update file with new filename
                $sql = "UPDATE redcap_docs SET docs_name = '" . db_escape($filename) . "'
                        WHERE docs_id = $docs_id AND project_id = ".PROJECT_ID;
                $q = db_query($sql);
                // Update edocs_metadata table
                $sql = "UPDATE redcap_edocs_metadata SET doc_name = '" . db_escape($filename) . "'
                        WHERE doc_id = $doc_id AND project_id = ".PROJECT_ID;
                $q = db_query($sql);
                break;
            }
        } while ($increment > 0);
        return $filename;
    }

    // Rename a file in both the redcap_docs and redcap_edocs_metadata db tables. Use the doc_id from the redcap_edocs_metadata table.
    public static function renameFile($doc_id, $newname)
    {
        // Get docs_id from doc_id
        $sql = "select docs_id from redcap_docs_to_edocs where doc_id = $doc_id";
        $docs_id = db_result(db_query($sql), 0);
        // Update file with new filename
        $sql = "UPDATE redcap_docs SET docs_name = '" . db_escape($newname) . "'
                WHERE docs_id = $docs_id AND project_id = ".PROJECT_ID;
        // Update edocs_metadata table
        $sql2 = "UPDATE redcap_edocs_metadata SET doc_name = '" . db_escape($newname) . "'
                WHERE doc_id = $doc_id AND project_id = ".PROJECT_ID;
        return (db_query($sql) && db_query($sql2));
    }


    // Move files/folders to new location
    public static function move($folder_ids, $docs_ids, $current_folder_id, $new_folder_id)
    {
        extract($GLOBALS);
        if (!isinteger($new_folder_id)) $new_folder_id = null;

        // Triage folders
        $foldersToMove = [];
        foreach (explode(",", $folder_ids) as $folder_id) {
            if (!isinteger($folder_id) || !FileRepository::userHasFolderAccess($folder_id)) continue;
            // Check folder's DAG access
            if ($user_rights['group_id'] != '') {
                $dag_id = self::folderHasDagRestriction($folder_id);
                if ($dag_id != false && $dag_id != $user_rights['group_id']) continue;
            }
            // Check folder's role access
            if ($user_rights['role_id'] != '') {
                $role_id = self::folderHasRoleRestriction($folder_id);
                if ($role_id != false && $role_id != $user_rights['role_id']) continue;
            }
            // Add to array
            $foldersToMove[] = $folder_id;
        }

        // Triage docs
        $docsToMove = [];
        foreach (explode(",", $docs_ids) as $id) {
            if (!isinteger($id) || !FileRepository::userHasFileAccess($id)) continue;
            // Check file's DAG access
            if ($user_rights['group_id'] != '') {
                $dag_id = self::folderHasDagRestriction(self::getFolderIdByDocId($id));
                if ($dag_id != false && $dag_id != $user_rights['group_id']) continue;
            }
            // Check file's role access
            if ($user_rights['role_id'] != '') {
                $role_id = self::folderHasRoleRestriction(self::getFolderIdByDocId($id));
                if ($role_id != false && $role_id != $user_rights['role_id']) continue;
            }
            // Add to array
            $docsToMove[] = $id;
        }

        // Set location of docs
        if (!empty($docsToMove))
        {
            // First remove current location of docs
            $sql = "delete from redcap_docs_folders_files where docs_id in (" . prep_implode($docsToMove) . ")";
            $q = db_query($sql);
            // Now insert new location for docs
            if (isinteger($new_folder_id)) {
                foreach ($docsToMove as $docs_id) {
                    $sql = "insert into redcap_docs_folders_files (docs_id, folder_id) values ($docs_id, $new_folder_id)";
                    $q = db_query($sql);
                }
            }
        }

        if (!empty($foldersToMove))
        {
            // Deal with DAG and role associations of folders being moved. If the parent folder (even N-levels up) is DAG- or role-restricted,
            // remove the restrictions of the folders being moved under them.
            $role_sql = $dag_sql = "";
            if (self::folderHasRoleRestriction($new_folder_id)) $role_sql = ", role_id = null";
            if (self::folderHasDagRestriction($new_folder_id)) $dag_sql = ", dag_id = null";
            // Set location of folders
            $sql = "update redcap_docs_folders 
                    set parent_folder_id = " . checkNull($new_folder_id) . " $role_sql $dag_sql
                    where folder_id in (" . prep_implode($foldersToMove) . ") and project_id = ".PROJECT_ID;
            if (isinteger($new_folder_id)) $sql .= " and folder_id != $new_folder_id"; // Prevent a folder from being its own parent
            $q = db_query($sql);
        }

        list ($folderName, $nothing) = self::getFolderNameAndParentFromId($new_folder_id);
        if ($folderName == null) $folderName = $lang['docs_1131'];

        $filesMoved = count($docsToMove);
        $foldersMoved = count($foldersToMove);

        // Logging
        Logging::logEvent("","redcap_docs_folders","MANAGE",PROJECT_ID,"$filesMoved files, $foldersMoved folders, moved to folder $folderName", "Move files/folders in File Repository");

        // Return "1" on success
        print "1";
    }


    // Download multiple files from the File Repository
    public static function downloadMultiple($folder_ids, $docs_ids, $current_folder_id)
    {
        extract($GLOBALS);
        // Increase memory limit in case needed for intensive processing
        System::increaseMemory(2048);

        // Triage folders
        $subfolders = [];
        foreach (explode(",", $folder_ids) as $folder_id) {
            if (!isinteger($folder_id) || !FileRepository::userHasFolderAccess($folder_id)) continue;
            // Check folder's DAG access
            if ($user_rights['group_id'] != '') {
                $dag_id = self::folderHasDagRestriction($folder_id);
                if ($dag_id != false && $dag_id != $user_rights['group_id']) continue;
            }
            // Check folder's role access
            if ($user_rights['role_id'] != '') {
                $role_id = self::folderHasRoleRestriction($folder_id);
                if ($role_id != false && $role_id != $user_rights['role_id']) continue;
            }
            // Add to array
            $subfolders[] = $folder_id;
        }

        // Triage docs
        foreach (explode(",", $docs_ids) as $id) {
            if (!isinteger($id) || !FileRepository::userHasFileAccess($id)) continue;
            // Check file's DAG access
            if ($user_rights['group_id'] != '') {
                $dag_id = self::folderHasDagRestriction(self::getFolderIdByDocId($id));
                if ($dag_id != false && $dag_id != $user_rights['group_id']) continue;
            }
            // Check file's role access
            if ($user_rights['role_id'] != '') {
                $role_id = self::folderHasRoleRestriction(self::getFolderIdByDocId($id));
                if ($role_id != false && $role_id != $user_rights['role_id']) continue;
            }
            // Add to array
            $foldersFiles[""][] = $id;
        }

        // For sub-folders, gather all docs and sub-folders to add to array also
        $parentFolders = []; // child=>parent folder
        foreach ($subfolders as $folder_id) {
            // Add top-level folder as 0 value
            $parentFolders[$folder_id] = 0;
        }
        do {
            $next_subfolders = [];
            foreach ($subfolders as $key=>$folder_id)
            {
                // Get sub-folders
                $sql = "select folder_id from redcap_docs_folders 
                        where project_id = $project_id and parent_folder_id = $folder_id order by folder_id";
                $q = db_query($sql);
                while ($row = db_fetch_assoc($q)) {
                    $next_subfolders[] = $row['folder_id'];
                    $parentFolders[$row['folder_id']] = $folder_id;
                }
                unset($subfolders[$key]);
            }
            // Set for next batch
            $subfolders = $next_subfolders;
        } while (!empty($subfolders));

        // Get files in all the sub-folders
        foreach ($parentFolders as $folder_id=>$parent_id)
        {
            // Build folder key of all the parent folders
            $folder_key = $folder_id;
            $this_parent_id = $parent_id;
            while ($this_parent_id > 0) {
                $folder_key = $this_parent_id."-".$folder_key;
                $this_parent_id = $parentFolders[$this_parent_id] ?? 0;
            }
            // Add files in this sub-folder
            $sql = "select docs_id from redcap_docs_folders_files 
                    where folder_id = $folder_id order by docs_id";
            $q = db_query($sql);
            if (!isset($foldersFiles[$folder_key])) {
                $foldersFiles[$folder_key] = [];
            }
            while ($row = db_fetch_assoc($q)) {
                // Add file
                $foldersFiles[$folder_key][] = $row['docs_id'];
            }
        }

        // Obtain folder names of all folders
        $folderNames = [];
        if (!empty($parentFolders)) {
            $sql = "select folder_id, name from redcap_docs_folders 
                    where project_id = $project_id and folder_id in (" . prep_implode(array_keys($parentFolders)) . ")";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $folderNames[$row['folder_id']] = $row['name'];
            }
        }

        // Set the target zip file to be saved in the temp dir (set timestamp in filename as 1 hour from now so that it gets deleted automatically in 1 hour)
        $inOneHour = date("YmdHis", mktime(date("H")+1,date("i"),date("s"),date("m"),date("d"),date("Y")));
        ## Google Cloud Storage doesn't allow zipping of files, must be done in system temp
        if($edoc_storage_option == '3') {
            $target_zip = sys_get_temp_dir() . "/{$inOneHour}_pid{$project_id}_".generateRandomHash(6).".zip";
        } else {
            $target_zip = APP_PATH_TEMP . "{$inOneHour}_pid{$project_id}_".generateRandomHash(6).".zip";
        }

        ## CREATE OUTPUT ZIP FILE AND INDEX
        if (is_file($target_zip)) unlink($target_zip);
        // Create ZipArchive object
        $zip = new ZipArchive;
        // Start writing to zip file
        $folderCount = 0;
        $fileCount = 0;
        $baseFolderName = "";
        if ($zip->open($target_zip, ZipArchive::CREATE) === TRUE)
        {
            // Loop through files
            foreach ($foldersFiles as $key=>$docs_ids)
            {
                // Get current folder_id and array of parent folders
                if (strpos($key, "-") === false) {
                    $folder_id = $key;
                    $parent_ids = [];
                } else {
                    $parent_ids = explode("-", $key);
                    $folder_id = array_pop($parent_ids);
                }
                if ($folder_id == '') {
                    // Current folder
                    $folderName = $baseFolderName;
                } else {
                    // Sub-folder
                    $folderName = $folderNames[$folder_id] ?? "???";
                    if (empty($parent_ids)) {
                        // 1-level down
                        $folderName = $baseFolderName . $folderName . "/";
                    } else {
                        // N-levels down
                        $theseFolderNames = "";
                        foreach ($parent_ids as $parent_id) {
                            $theseFolderNames .= ($folderNames[$parent_id] ?? "???") . "/";
                        }
                        $folderName = $baseFolderName . $theseFolderNames . $folderName . "/";
                    }
                }
                // Add this directory
                $zip->addEmptyDir($folderName);
                $folderCount++;
                // Add files
                foreach ($docs_ids as $docs_id) {
                    $doc_id = self::getDocIdFromDocsId($docs_id);
                    if ($doc_id == null) continue;
                    list ($mimeType, $docName, $fileContent) = Files::getEdocContentsAttributes($doc_id);
                    $zip->addFromString($folderName . $docName, $fileContent);
                    $fileCount++;
                }
                unset($fileContent);
            }
            // Done adding to zip file
            $zip->close();
        }
        ## ERROR
        else
        {
            exit("ERROR: Unable to create ZIP archive at $target_zip");
        }

        // Logging (except on public reports)
        Logging::logEvent("", "redcap_edocs_metadata", "MANAGE", $project_id, "$fileCount files, $folderCount folders", "Download ZIP of File Repository files");

        // Download file and then delete it from the server
        $download_filename = "FileRepository_".substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($Proj->project['app_title'], ENT_QUOTES)))), 0, 20)."_".date("Y-m-d_Hi").".zip";
        header('Pragma: anytextexeptno-cache', true);
        header('Content-Type: application/octet-stream"');
        header('Content-Disposition: attachment; filename="'.$download_filename.'"');
        header('Content-Length: ' . filesize($target_zip));
        ob_start();ob_end_flush();
        readfile_chunked($target_zip);
        unlink($target_zip);
    }


    // Download a file from the File Repository
    public static function download()
    {
        extract($GLOBALS);

        // Increase memory limit in case needed for intensive processing
        System::increaseMemory(2048);

        // Record-locking PDF files (requires FULL data export rights only)
        if ($user_rights['data_export_tool'] == '1' && isset($_GET['lock_doc_id']) && is_numeric($_GET['lock_doc_id']))
        {
            $id = (int)$_GET['lock_doc_id'];
            // Verify the doc_id of this file
            $files = Locking::getLockedRecordPdfFiles($Proj, (isset($user_rights['group_id']) ? $user_rights['group_id'] : null), $id);
            if (empty($files)) exit($lang['global_01']);
            // Get file attr and content
            $fileAttr = Files::getEdocContentsAttributes($id);
            if (empty($fileAttr)) exit($lang['global_01']);
            list ($mimeType, $docName, $fileContent) = $fileAttr;
            // Log it
            Logging::logEvent("","redcap_docs","MANAGE",$files['record'],"record = {$files['record']},\narm_id = {$files['arm_id']}","Download Archived PDF of Locked Record");
            // Output file
            header('Content-type: application/pdf');
            header('Content-disposition: attachment; filename="'.$docName.'"');
            print $fileContent;
        }

        // PDF Archiver files (requires FULL data export rights only)
        elseif ($user_rights['data_export_tool'] == '1' && isset($_GET['doc_id']) && is_numeric($_GET['doc_id']))
        {
            $id = (int)$_GET['doc_id'];
            // Verify the doc_id of this file
            $files = PdfSnapshot::getPdfSnapshotArchiveFiles($Proj, (isset($user_rights['group_id']) ? $user_rights['group_id'] : null), $id);
            if (empty($files)) exit($lang['global_01']);
            // Get file attr and content
            $fileAttr = Files::getEdocContentsAttributes($id);
            if (empty($fileAttr)) exit($lang['global_01']);
            list ($mimeType, $docName, $fileContent) = $fileAttr;
            // Log it
            Logging::logEvent("","redcap_docs","MANAGE",$files['record'],"docs_id = $id,\nrecord = {$files['record']},\nsurvey_id = {$files['survey_id']},\nevent_id = {$files['event_id']},\ninstance = {$files['instance']}","Download PDF Snapshot File", "", "", "", true, $files['event_id'], $files['instance']);
            // Output file
            header('Content-type: application/pdf');
            header('Content-disposition: attachment; filename="'.$docName.'"');
            print $fileContent;
        }

        // ALL PDF Archive file list as CSV
        elseif ($user_rights['file_repository'] == '1' && isset($_GET['doc_id']) && $_GET['doc_id'] == 'pdf_archive_csv')
        {
            // Log it
            Logging::logEvent("","redcap_surveys_pdf_archive","MANAGE",PROJECT_ID,"project_id=".PROJECT_ID,"Download CSV containing file list of all PDF Snapshot files");
            // Output file
            $filename = "PDFArchive_".substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 20)."_".date("Y-m-d_Hi").".csv";
            header('Pragma: anytextexeptno-cache', true);
            header("Content-type: application/csv");
            header('Content-Disposition: attachment; filename=' . $filename);
            echo addBOMtoUTF8(arrayToCsv(self::getPdfArchiveFileList(), false));
        }

        // ALL PDF Archive files (requires FULL data export rights only)
        elseif ($user_rights['data_export_tool'] == '1' && isset($_GET['doc_id']) && $_GET['doc_id'] == 'pdf_archive_all')
        {
            // Make sure server has ZipArchive ability (i.e. is on PHP 5.2.0+)
            if (!Files::hasZipArchive()) {
                exit('ERROR: ZipArchive is not installed. It must be installed to use this feature.');
            }

            // Set the target zip file to be saved in the temp dir (set timestamp in filename as 1 hour from now so that it gets deleted automatically in 1 hour)
            $inOneHour = date("YmdHis", mktime(date("H")+1,date("i"),date("s"),date("m"),date("d"),date("Y")));

            // Set paths, etc.
            ## Google Cloud Storage doesn't allow zipping of files, must be done in system temp
            if($edoc_storage_option == '3') {
                $target_zip = sys_get_temp_dir() . "/{$inOneHour}_pid{$project_id}_".generateRandomHash(6).".zip";
            } else {
                $target_zip = APP_PATH_TEMP . "{$inOneHour}_pid{$project_id}_".generateRandomHash(6).".zip";
            }

            $zip_parent_folder = "PDFArchive_".substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 20)."_".date("Y-m-d_Hi");
            $download_filename = "$zip_parent_folder.zip";
            // Verify the doc_id of this file
            $files = PdfSnapshot::getPdfSnapshotArchiveFiles($Proj, (isset($user_rights['group_id']) ? $user_rights['group_id'] : null));
            if (empty($files)) exit($lang['global_01']);
            ## CREATE OUTPUT ZIP FILE AND INDEX
            if (is_file($target_zip)) unlink($target_zip);
            // Create ZipArchive object
            $zip = new ZipArchive;
            // Start writing to zip file
            if ($zip->open($target_zip, ZipArchive::CREATE) === TRUE)
            {
                foreach ($files as $file)
                {
                    // Check to ensure user has full form-level export rights for this instrument
                    if (isinteger($file['survey_id']) && $user_rights['forms_export'][$Proj->surveys[$file['survey_id']]['form_name']] != '1') continue;
                    // Get file attr and content
                    $fileAttr = Files::getEdocContentsAttributes($file['doc_id']);
                    if (empty($fileAttr)) continue;
                    list ($mimeType, $docName, $fileContent) = $fileAttr;
                    $zip->addFromString($docName, $fileContent);
                }
                // Done adding to zip file
                $zip->close();
            }
            ## ERROR
            else
            {
                exit("ERROR: Unable to create ZIP archive at $target_zip");
            }

            // Logging
            Logging::logEvent("", "redcap_edocs_metadata", "MANAGE", $project_id, "project_id = $project_id", "Download ZIP of all PDF Snapshot files");
            // Download file and then delete it from the server
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $download_filename);
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($target_zip));
            readfile($target_zip);
            unlink($target_zip);
            exit;
        }

        elseif (isset($_GET['id']) && is_numeric($_GET['id']))
        {
            $id = (int)$_GET['id'];

            /* we need to determine if the document is in the file system or the database */
            $sql = "SELECT d.docs_size, d.docs_type, d.export_file, d.docs_name, e.docs_id, m.stored_name, d.docs_file, m.gzipped
			FROM redcap_docs d
			LEFT JOIN redcap_docs_to_edocs e ON e.docs_id = d.docs_id
			LEFT JOIN redcap_edocs_metadata m ON m.doc_id = e.doc_id
			WHERE d.docs_id = $id and d.project_id = $project_id";
            $result = db_query($sql);
            if ($result)
            {
                // Get query object
                $ddata = db_fetch_object($result);

                // Get file attributes
                $gzipped = $ddata->gzipped;
                $size = $ddata->docs_size;
                $type = $ddata->docs_type;
                $export_file = $ddata->export_file;
                $name = $docs_name = $ddata->docs_name;
                $name = preg_replace("/[^a-zA-Z-._0-9]/", "_", $name);
                $name = str_replace("__","_",$name);
                $name = str_replace("__","_",$name);

                // If this file is a user file uploaded into the File Repository (i.e., not an export file or PDF Archive file), then make sure user has access to File Repository.
                // And if this is an export file, make sure user has data export privileges.
                if ((!$export_file && $user_rights['file_repository'] == '0') || ($export_file && $user_rights['data_export_tool'] == '0')) {
                    exit($lang['global_01']);
                }

                // Check file access (for user-uploaded File Repository files only)
                if (!$export_file) {
                    if (!FileRepository::userHasFileAccess($id)) exit($lang['global_01']);
                    // Check file's DAG access
                    if ($user_rights['group_id'] != '') {
                        $dag_id = self::folderHasDagRestriction(self::getFolderIdByDocId($id));
                        if ($dag_id != false && $dag_id != $user_rights['group_id']) {
                            exit($lang['global_01']);
                        }
                    }
                    // Check file's role access
                    if ($user_rights['role_id'] != '') {
                        $role_id = self::folderHasRoleRestriction(self::getFolderIdByDocId($id));
                        if ($role_id != false && $role_id != $user_rights['role_id']) {
                            exit($lang['global_01']);
                        }
                    }
                }

                // Determine type of file
                $file_extension = strtolower(substr($docs_name,strrpos($docs_name,".")+1,strlen($docs_name)));

                // Set header content-type
                $type = 'application/octet-stream';
                if (strtolower(substr($name, -4)) == ".csv") {
                    $type = 'application/csv';
                }

                if ($ddata->docs_id === NULL) {
                    /* there is no reference to edocs_metadata, so the data lives in the database table (legacy) */
                    $data = $ddata->docs_file;
                } else {
                    if ($edoc_storage_option == '1') {
                        //Download using WebDAV
                        if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
                        // Upload using WebDAV
                        $wdc = new WebdavClient();
                        $wdc->set_server($webdav_hostname);
                        $wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
                        $wdc->set_user($webdav_username);
                        $wdc->set_pass($webdav_password);
                        $wdc->set_protocol(1); // use HTTP/1.1
                        $wdc->set_debug(FALSE); // enable debugging?
                        if (!$wdc->open()) {
                            $error[] = $lang['global_01'];
                        }
                        $data = NULL;
                        $http_status = $wdc->get($webdav_path . $ddata->stored_name, $data); /* passed by reference, so file content goes to $data */
                        $wdc->close();
                    } elseif ($edoc_storage_option == '2') {
                        // S3
                        try {
                            $s3 = Files::s3client();
                            $object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$ddata->stored_name));
                            $data = $object['Body'];
                        } catch (Aws\S3\Exception\S3Exception $e) {
                            // Pull $data using readfile_chunked() for better memory management (assumes not an export file or Japanese SJIS encoded file)
                            $data = NULL;
                        }

                    } elseif ($edoc_storage_option == '4') {
                        // Azure
                        $blobClient = new AzureBlob();
                        $data = $blobClient->getBlob($ddata->stored_name);
                    }elseif ($edoc_storage_option == '5') {
                        // Google
                        $googleClient = Files::googleCloudStorageClient();
                        $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);
                        $googleClient->registerStreamWrapper();


                        $data = file_get_contents('gs://'.$GLOBALS['google_cloud_storage_api_bucket_name'].'/' . $ddata->stored_name);

                    } else {
                        /* The file lives in the file system */
                        if ($export_file || ($project_encoding == 'japanese_sjis' && function_exists('mb_detect_encoding') && mb_detect_encoding($data) == "UTF-8")) {
                            // If need to pull $data into memory
                            $data = file_get_contents(EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $ddata->stored_name);
                        } else {
                            // Pull $data using readfile_chunked() for better memory management (assumes not an export file or Japanese SJIS encoded file)
                            $data = NULL;
                        }
                    }
                }

                // GZIP decode the file (if is encoded)
                if ($export_file && $gzipped && $data != null)
                {
                    list ($data, $name) = gzip_decode_file($data, $name);
                }

                // If exporting R or Stata data file as UTF-8 encoded, then remove the BOM (causes issues in R and Stata)
                if ($export_file && isset($_GET['exporttype']) && ($_GET['exporttype'] == 'R' || $_GET['exporttype'] == 'STATA'))
                {
                    $data = removeBOM($data);
                }
                /*
                // If a SAS syntax file, replace beginning text so that even very old files work with the SAS Pathway Mapper (v4.6.3+)
                elseif ($export_file && strtolower(substr($name, -4)) == '.sas')
                {
                    // Find the position of "infile '" and cut off all text occurring before it
                    $pos = strpos($data, "infile '");
                    if ($pos !== false) {
                        // Now splice the file back together using the new string that occurs on first line (which will work with Pathway Mapper)
                        $prefix = "%macro removeOldFile(bye); %if %sysfunc(exist(&bye.)) %then %do; proc delete data=&bye.; run; %end; %mend removeOldFile; %removeOldFile(work.redcap); data REDCAP; %let _EFIERR_ = 0;\n";
                        $data = $prefix . substr($data, $pos);
                    }
                }
                */

                // Output headers for file
                header('Pragma: anytextexeptno-cache', true);
                header("Content-type: $type");
                header("Content-Disposition: attachment; filename=$name");

                //File encoding will vary by language module
                if ($project_encoding == 'japanese_sjis' && function_exists('mb_detect_encoding') && mb_detect_encoding($data) == "UTF-8") {
                    print mb_convert_encoding(removeBOM($data), "SJIS", "UTF-8");
                } else {
                    if ($data == NULL) {
                        // Use readfile_chunked() for better memory management of large files
                        ob_start();ob_end_flush();
                        readfile_chunked(EDOC_PATH . \Files::getLocalStorageSubfolder($project_id, true) . $ddata->stored_name);
                    } elseif (strlen($data) > (10*1024*1024)) {
                        // If file is more than 10MB in size, use readfile_chunked method by saving as file to temp first and then serving it from there
                        $temp_filename = APP_PATH_TEMP . date('YmdHis') . "_pid" . $project_id . "_" . substr(sha1(rand()), 0, 6) . getFileExt($name, true);
                        file_put_contents($temp_filename, $data);
                        // Use readfile_chunked() for better memory management of large files
                        ob_start();ob_end_flush();
                        readfile_chunked($temp_filename);
                    } else {
                        // File content is stored in memory as $data, so print it
                        print $data;
                    }
                }

                ## Logging
                // Default logging description
                $descr = "Download file from File Repository";
                $log_data_values = "docs_id = $id";
                // Determine type of file
                if ($export_file)
                {
                    switch ($file_extension) {
                        case "xml":
                            if (substr($name, -10) == 'REDCap.xml') {
                                $descr = "Download exported REDCap project XML file (metadata & data)";
                            } else {
                                $descr = "Download exported data file (CDISC ODM)";
                            }
                            break;
                        case "r":
                            $descr = "Download exported syntax file (R)";
                            break;
                        case "do":
                            $descr = "Download exported syntax file (Stata)";
                            break;
                        case "sas":
                            $descr = "Download exported syntax file (SAS)";
                            break;
                        case "sps":
                            $descr = "Download exported syntax file (SPSS)";
                            break;
                        case "csv":
                            $descr = (substr($name, 0, 12) == "DATA_LABELS_" || strpos($name, "_DATA_LABELS_20") !== false)
                                ? "Download exported data file (CSV labels)"
                                : "Download exported data file (CSV raw)";
                            break;
                    }
                } else {
					list ($folderName, $nothing) = self::getFolderNameAndParentFromId(self::getFolderIdByDocId($id));
					if ($folderName == null) $folderName = $lang['docs_1131'];
					$log_data_values = "$name, folder $folderName";
                }
                // Log it
                Logging::logEvent($sql,"redcap_docs","MANAGE",$id,$log_data_values,$descr);

            }
        }

        else
        {
            exit($lang['global_01']);
        }
    }


    // Determine if a folder or its parent folders (recursively) have a DAG restriction.
    // Return False if not, else return DAG ID if so.
    public static function folderHasDagRestriction($folder_id)
    {
        global $user_rights;
        if (!isinteger($folder_id)) return false;
        do {
            $sql = "select parent_folder_id, dag_id from redcap_docs_folders where folder_id = $folder_id and project_id = ".PROJECT_ID;
            $q = db_query($sql);
            if (!db_num_rows($q)) return false;
            // If this folder is DAG restricted, return the DAG ID
            $dag_id = db_result($q, 0, 'dag_id');
            if ($dag_id != '') return $dag_id;
            // Set folder_id for next loop
            $folder_id = db_result($q, 0, 'parent_folder_id');
        } while ($folder_id != null);
        // Return false if folder and none of its parents are DAG restricted
        return false;
    }


    // Determine if a folder or its parent folders (recursively) have a User Role restriction.
    // Return False if not, else return Role ID if so.
    public static function folderHasRoleRestriction($folder_id)
    {
        global $user_rights;
        if (!isinteger($folder_id)) return false;
        do {
            $sql = "select parent_folder_id, role_id from redcap_docs_folders where folder_id = $folder_id and project_id = ".PROJECT_ID;
            $q = db_query($sql);
            if (!db_num_rows($q)) return false;
            // If this folder is role restricted, return the role ID
            $role_id = db_result($q, 0, 'role_id');
            if ($role_id != '') return $role_id;
            // Set folder_id for next loop
            $folder_id = db_result($q, 0, 'parent_folder_id');
        } while ($folder_id != null);
        // Return false if folder and none of its parents are role restricted
        return false;
    }


    // Determine if a folder or its parent folders (recursively) have an Admin restriction.
    // Return boolean if so.
    public static function folderHasAdminRestriction($folder_id)
    {
        if (!isinteger($folder_id)) return false;
        do {
            $sql = "select parent_folder_id, admin_only from redcap_docs_folders where folder_id = $folder_id and project_id = ".PROJECT_ID;
            $q = db_query($sql);
            if (!db_num_rows($q)) return false;
            // If this folder is admin restricted, return true
            $admin_only = db_result($q, 0, 'admin_only');
            if ($admin_only == '1') return true;
            // Set folder_id for next loop
            $folder_id = db_result($q, 0, 'parent_folder_id');
        } while ($folder_id != null);
        // Return false if folder and none of its parents are role restricted
        return false;
    }


    // Return the folder_id of a specific file (using docs_id from redcap_docs), else return NULL
    public static function getFolderIdByDocId($docs_id)
    {
        if (!isinteger($docs_id)) return null;
        $sql = "select folder_id from redcap_docs_folders_files where docs_id = $docs_id";
        $q = db_query($sql);
        $folder_id = ($q && db_num_rows($q) > 0) ? db_result($q, 0) : null;
        return $folder_id;
    }


    // Determine if the current user in the current project has access to a specific File Repository folder.
    // If folder_id=null, it assumes the top level (i.e., not in a folder). Also checks user's DAG, if applicable.
    public static function userHasFolderAccess($folder_id=null)
    {
        global $user_rights;
        if (!$user_rights['file_repository']) return false;
        if (!isinteger($folder_id)) return true;
        $dagsql = ($user_rights['group_id'] == "") ? "" : "and (dag_id is null or dag_id = ".$user_rights['group_id'].")";
        $rolesql = ($user_rights['role_id'] == "") ? "" : "and (role_id is null or role_id = ".$user_rights['role_id'].")";
        $sql = "select 1 from redcap_docs_folders where folder_id = $folder_id $dagsql $rolesql and project_id = ".PROJECT_ID;
        $q = db_query($sql);
        return (db_num_rows($q) > 0);
    }


    // Determine if the current user in the current project has access to a specific File Repository file (by checking the file's folder access).
    public static function userHasFileAccess($docs_id)
    {
        global $user_rights;
        if (!$user_rights['file_repository'] || !isinteger($docs_id)) return false;
        // Get folder_id, if file is in a folder. folder_id=null if file in top level
        $sql = "select folder_id from redcap_docs_folders_files where docs_id = $docs_id";
        $q = db_query($sql);
        $folder_id = db_num_rows($q) ? db_result($q, 0) : null;
        return self::userHasFolderAccess($folder_id);
    }


    // Get the total file count for a given folder (including all sub-folders)
    public static function getFileCount($folder_id, $recycle_bin=0)
    {
        global $user_rights;
        if (!isinteger($folder_id)) return 0;
        $recycle_bin = ($recycle_bin == 1) ? 1 : 0;
        $totalCount = 0;
        // Count files specifically in this folder (not counting sub-folders)
        $sql = "select count(1) from redcap_docs_folders_files f, redcap_docs_to_edocs de, redcap_edocs_metadata e
                where f.folder_id = $folder_id and de.docs_id = f.docs_id and de.doc_id = e.doc_id
                and e.project_id = ".PROJECT_ID." and e.delete_date is ".($recycle_bin ? "not" : "")." null
                and e.date_deleted_server is null";
        $q = db_query($sql);
        $totalCount += db_result($q, 0);
        // Get folder_id's of all sub-folders recursively
        $subfolders = [];
        // Commented out the 2 lines below because we want to show the true file count, even if the current user can't access some folders due to DAG/role restrictions
        // $dagsql = ($user_rights['group_id'] == "") ? "" : "and (dag_id is null or dag_id = ".$user_rights['group_id'].")";
        // $rolesql = ($user_rights['role_id'] == "") ? "" : "and (role_id is null or role_id = ".$user_rights['role_id'].")";
        // Get the parent folder_id
        $sql = "select folder_id from redcap_docs_folders 
                where parent_folder_id = $folder_id and deleted = 0
                and project_id = ".PROJECT_ID;
	    if (!SUPER_USER) $sql .= " and admin_only = 0";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $totalCount += self::getFileCount($row['folder_id'], $recycle_bin);
        }
        // Return total count
        return $totalCount;
    }


    // Obtain the public share link for a file
    public static function getPublicLink($doc_id, $project_id=null)
    {
        if (!isinteger($doc_id)) return false;
        if (!isinteger($project_id)) $project_id = PROJECT_ID;
        $sql = "select s.hash from redcap_docs d, redcap_docs_share s 
                where d.docs_id = s.docs_id and d.project_id = $project_id
                and s.docs_id = $doc_id and s.hash is not null";
        $q = db_query($sql);
        if (db_num_rows($q)) {
            // Return existing link
            $hash = db_result($q, 0);
        } else {
            // Generate hash
            $hash = generateRandomHash(100);
            // Save the hash
            $sql = "replace into redcap_docs_share (docs_id, hash) values ($doc_id, '".db_escape($hash)."')";
            if (!db_query($sql)) return false;
        }
        // Return link
        return APP_PATH_SURVEY_FULL . "?__file=$hash";
    }


    // Output HTML for file sharing dialog
    public static function shareFile($doc_id)
    {
        if (!isinteger($doc_id)) return false;
        global $lang;

        $publicLinkHtml = "";
        $shareInstructions = $lang['docs_1116'];
        if ($GLOBALS['file_repository_allow_public_link'] == '1')
        {
            $shareInstructions = $lang['docs_1106'];
            // Get public name and filename
            $publicLink = self::getPublicLink($doc_id);
            // $docName = Files::getEdocName(FileRepository::getDocIdFromDocsId($doc_id));
            // $isImg = in_array(strtolower(getFileExt($docName)), ['jpeg','jpg','jpe','gif','png','tif','bmp']);
            // Output HTML
            $publicLinkHtml .= RCView::div(['class'=>'py-3 px-1', 'style'=>'background-color:#f7f7f7;border:1px solid #ddd;'],
                RCView::div(['class' => 'fs14 ms-2 mb-2 font-weight-bold'],
                    '<i class="fa-solid fa-link"></i> ' . $lang['docs_1103']
                ) .
                RCView::div(['class' => 'fs13 ms-2 mt-1 mb-3'],
                    $lang['docs_1110']
                ) .
                '<input id="filePublicLink" value="'.$publicLink.'" onclick="this.select();" readonly="readonly" class="staticInput" style="font-size:14px;width:96%;max-width:96%;margin-bottom:5px;margin-right:5px;color:#e83e8c;font-family:SFMono-Regular,Menlo,Monaco,Consolas,\'Liberation Mono\',\'Courier New\',monospace;">
                <div class="ms-2 mt-1">
                    <button class="btn btn-defaultrc btn-xs fs14 me-2" onclick="window.open(\''.$publicLink.'\',\'_blank\');"><i class="fa-solid fa-up-right-from-square"></i> '.RCView::tt('docs_1104').'</button>
                    <button id="filePublicLinkBtn" class="btn btn-primaryrc btn-xs fs14" onclick="copyLinkToClipboard();"><i class="fas fa-paste"></i> '.RCView::tt('docs_1105').'</button>
                </div>'
            ) .
            (!($GLOBALS['sendit_enabled'] == '1' || $GLOBALS['sendit_enabled'] == '3') ? "" :
                RCView::div(array('class'=>'m-3 fs14 text-secondary'),
                    "&mdash; {$lang['global_46']} &mdash;"
                )
            );
        }

        // Obtain the public share link for this file
        if ($GLOBALS['sendit_enabled'] == '1' || $GLOBALS['sendit_enabled'] == '3')
        {
            $publicLinkHtml .=
                RCView::div(['class' => 'py-3 px-1', 'style' => 'background-color:#f7f7f7;border:1px solid #ddd;'],
                    RCView::div(['class' => 'fs14 ms-2 mb-2 font-weight-bold'],
                        '<i class="fa-solid fa-envelope"></i> ' . $lang['docs_1107']
                    ) .
                    RCView::div(['class' => 'fs13 ms-2 mb-3'],
                        $lang['docs_1109']
                    ) .
                    '<button class="btn btn-primaryrc btn-xs ms-2 fs14" onclick="popupSendIt(' . $doc_id . ',2);"><i class="fa-solid fa-paper-plane"></i> ' . RCView::tt('docs_1108') . '</button>'
                );
        }

        print RCView::p(array('class' => 'fs14 mt-0 mb-4'),
                $shareInstructions
            ) .
            $publicLinkHtml;
    }

    // Get all attributes for a public file using its public hash
    public static function getFileByHash($hash)
    {
        $sql = "select e.doc_id, d.* from redcap_docs_share s, redcap_docs d, redcap_docs_to_edocs t, redcap_edocs_metadata e
                where s.docs_id = d.docs_id and d.docs_id = t.docs_id and t.doc_id = e.doc_id
                and s.hash = '".db_escape($hash)."' limit 1";
        $q = db_query($sql);
        return (!$q || db_num_rows($q) == 0) ? false : db_fetch_assoc($q);
    }

    // Get HTML of breadcrumb links
    public static function getBreadcrumbs($folder_id, $type, $recycle_bin=0)
    {
        global $user_rights, $lang, $Proj;
        if ($type == '' && $recycle_bin == '0' && (!isinteger($folder_id) || !self::userHasFolderAccess($folder_id))) return "";
        // Recycle Bin
        if ($recycle_bin == '1') {
            return "<div class='ItemListBreadcrumbSeparator'>/</div><div class='ItemListBreadcrumb'>".RCView::tt('docs_1092')."</div>";
        }
        // Record Locking PDFs
        elseif ($type == 'record_lock_pdf_archive') {
            return "<div class='ItemListBreadcrumbSeparator'>/</div><div class='ItemListBreadcrumb'>".RCView::tt('data_entry_489')."</div>";
        }
        // eConsent PDFs
        elseif ($type == 'pdf_archive') {
            $showDownloadFilesBtn = ($user_rights['data_export_tool'] == '1');
            $downloadFilesBtn = "";
            if ($showDownloadFilesBtn) {
                $downloadFilesBtn = RCView::div(['class'=>'float-end'], RCView::button(array('class'=>'btn btn-xs btn-defaultrc', 'style'=>'color:#A86700;font-size:13px;margin-top:5px;', 'onclick'=>"window.location.href=app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:download&doc_id=pdf_archive_all';"), '<i class="fas fa-file-archive"></i> '.RCView::tt("docs_71")));
            }
            $downloadFileListCsvBtn = RCView::div(['class'=>'float-end me-2'], RCView::button(array('class'=>'btn btn-xs btn-defaultrc text-successrc', 'style'=>'font-size:13px;margin-top:5px;', 'onclick'=>"window.location.href=app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:download&doc_id=pdf_archive_csv';"), "<img src='" . APP_PATH_IMAGES . "xls.gif' style='position: relative;top: -1px;'> ".RCView::tt("docs_1154")));
            return "<div class='ItemListBreadcrumbSeparator'>/</div><div class='ItemListBreadcrumb'>".RCView::tt('econsent_115')."</div>" . $downloadFilesBtn . $downloadFileListCsvBtn;
        }
        // Data Export files
        elseif ($type == 'export') {
            return "<div class='ItemListBreadcrumbSeparator'>/</div><div class='ItemListBreadcrumb'>".RCView::tt('docs_30')."</div>";
        }
        // File Attachments
        elseif ($type == 'attachments') {
            return "<div class='ItemListBreadcrumbSeparator'>/</div><div class='ItemListBreadcrumb'>".RCView::tt('data_entry_600')."</div>";
        }
        // Top level
        elseif ($folder_id == '') {
            return "";
        }
        // User Files
        $html = "";
        $currentFolder = true;
        $orig_folder_id = $folder_id;
        do {
            // Get the name of this folder and return HTML link and div
            list ($folderName, $parent_folder_id) = self::getFolderNameAndParentFromId($folder_id);
            $folderName = strip_tags($folderName);
            // If this is not the current folder (but a parent), add as a clickable link
            if (!$currentFolder) {
                $folderName = "<a href='javascript:;' onclick=\"loadFileRepoTable('$folder_id','',0);\" class='ItemListBreadcrumb-link'>$folderName</a>";
            }
            // Wrap with HTML
            $html = "<div class='ItemListBreadcrumbSeparator'>/</div><div class='ItemListBreadcrumb'>".$folderName."</div>" . $html;
            // Set for next loop
            $currentFolder = false;
            $folder_id = $parent_folder_id;
        } while ($folder_id != null);
        // Add DAG restriction note (if applicable)
        $dag_id = self::folderHasDagRestriction($orig_folder_id);
        if ($dag_id != false) {
            $html .= "<span class='Breadcrumb-Dag-Restriction ms-3 fs12 text-secondary' style='position:relative;top:-2px;'>[".$lang['docs_91']."<code class='fs13 ms-1'>".strip_tags($Proj->getGroups($dag_id))."</code>]</span>";
        }
        // Add Role restriction note (if applicable)
        $role_id = self::folderHasRoleRestriction($orig_folder_id);
        if ($role_id != false) {
            $roles = UserRights::getRoles();
            if (isset($roles[$role_id])) {
                $html .= "<span class='Breadcrumb-Role-Restriction ms-3 fs12 text-secondary' style='position:relative;top:-2px;'>[".$lang['docs_1085']."<code class='fs13 ms-1'>".strip_tags($roles[$role_id]['role_name'])."</code>]</span>";
            }
        }
        // Add Admin restriction note (if applicable)
        if (self::folderHasAdminRestriction($orig_folder_id)) {
            $html .= "<span class='Breadcrumb-Admin-Restriction ms-3 fs12 text-secondary text-dangerrc' style='position:relative;top:-2px;'><i class='fas fa-user-shield me-1'></i>".$lang['docs_1156']."</span>";
        }
        // Return all HTML
        return $html;
    }


    // Get folder name and parent folder_id (if has a parent) using folder_id
    public static function getFolderNameAndParentFromId($folder_id)
    {
        if (!isinteger($folder_id)) return [null, null];
        // Get the name of this folder and return HTML link and div
        $sql = "select name, parent_folder_id from redcap_docs_folders where folder_id = $folder_id and project_id = ".PROJECT_ID;
        $q = db_query($sql);
        return (db_num_rows($q) ? [db_result($q, 0, "name"), db_result($q, 0, "parent_folder_id")] : [null, null]);
    }


    // Get all the miscellaneous file attachments (added via the rich text editor) found in the redcap_docs_attachments table
    public static function getMiscFileAttachments()
    {
        $rows = [];
        $sql = "select d.docs_id, e.doc_id, d.docs_name, d.docs_size, e.stored_date, d.docs_comment
                from redcap_docs_to_edocs de, redcap_edocs_metadata e, redcap_docs d, redcap_docs_attachments a
                where d.project_id = ? and d.export_file = 0 and a.docs_id = d.docs_id
                and de.docs_id = d.docs_id and de.doc_id = e.doc_id 
                and e.delete_date is null and e.date_deleted_server is null";
        $q = db_query($sql, PROJECT_ID);
        while ($row = db_fetch_assoc($q)) {
            $rows[$row['docs_name'] . "-" . $row['docs_id']] = $row;
        }
        // Perform natural sorting by filename
        natcaseksort($rows);
        // Return files array
        return array_values($rows);
    }


    // Is a file a miscellaneous file attachment uploaded via the rich text editor?
    public static function isFileMiscAttachment($edoc_id)
    {
        $sql = "select 1 from redcap_edocs_metadata e, redcap_docs_to_edocs de, redcap_docs_attachments a
                where e.doc_id = ? and de.doc_id = e.doc_id and a.docs_id = de.docs_id
                and e.delete_date is null and e.date_deleted_server is null";
        $q = db_query($sql, $edoc_id);
       return (db_num_rows($q) > 0);
    }


    // Return HTML table of all uploaded misc file attachments
    public static function getMiscFileAttachmentsTable()
    {
        global $lang;
        $docsTable = "";
        $miscFileAttachments = FileRepository::getMiscFileAttachments();
        if (!empty($miscFileAttachments)) {
            $docsTable = "<table class='dataTable fs12 mt-3' style='width:98%;'>
                            <thead>
                            <tr>
                                <th>{$lang['data_export_tool_169']}</th>
                                <th>{$lang['data_export_tool_170']}</th>
                                <th>{$lang['docs_78']}</th>
                                <th>{$lang['data_export_tool_311']}</th>
                            </tr>
                            </thead>
                            <tbody>";
            foreach ($miscFileAttachments as $docAttr) {
                $docsTable .= "<tr><td>" . strip_tags(label_decode($docAttr['docs_name'])) . "</td>
                              <td class='nowrap'>" . DateTimeRC::format_user_datetime($docAttr['stored_date'], 'Y-M-D_24') . "</td>
                              <td class='nowrap'>" . round($docAttr['docs_size'] / 1024, 2) . " KB</td>
                              <td>" . strip_tags(label_decode($docAttr['docs_comment'])) . "</td></tr>";
            }
            $docsTable .= "</tr></tbody></table>";
        }
        return $docsTable;
    }



    // Return file list for current context as JSON
    public static function getFileList($folder_id=null, $type=null, $recycle_bin=0)
	{
        global $lang, $user_rights, $Proj, $file_repository_enabled, $record_locking_pdf_vault_filesystem_type, $record_locking_pdf_vault_enabled, $pdf_econsent_system_ip;
        $project_id = PROJECT_ID;
        $folder_id = (isinteger($folder_id) && $folder_id > 0) ? (int)$folder_id : null;
        $recycle_bin = ($recycle_bin == 1) ? 1 : 0;
        $displayShareIcon = ($GLOBALS['sendit_enabled'] == '1' || $GLOBALS['sendit_enabled'] == '3' || $GLOBALS['file_repository_allow_public_link'] == '1');

        // Is PDF Auto-Archiver enabled?
        $rss = new PdfSnapshot();
        $hasSnapshotTriggersEnabled = $rss->hasSnapshotTriggersEnabled($project_id);
        if (!$hasSnapshotTriggersEnabled) {
            $sql = "select count(*) from redcap_surveys_pdf_archive a, redcap_edocs_metadata e
                    where e.doc_id = a.doc_id and a.event_id in (".prep_implode(array_keys($Proj->eventInfo)).")
                    and (a.survey_id is null or a.survey_id in (".prep_implode(array_keys($Proj->surveys))."))
                    and e.delete_date is null and e.project_id = " . $Proj->project_id;
            $hasSnapshotTriggersEnabled = (db_result(db_query($sql), 0) > 0);
        }

        // Is record-locking PDF confirmation enabled?
        $recordLockingPdfEnabled = ($record_locking_pdf_vault_filesystem_type != '' && $record_locking_pdf_vault_enabled);
        if (!$recordLockingPdfEnabled) {
            $sql = "select count(*) from redcap_locking_records_pdf_archive a, redcap_edocs_metadata e
                    where e.doc_id = a.doc_id
                    and e.delete_date is null and e.project_id = " . $Proj->project_id;
            $recordLockingPdfEnabled = (db_result(db_query($sql), 0) > 0);
        }

		$sql = "";
        $rows = [];

        // Data Export Files: If user is in a Data Access Group, only show exported files from users within that group
        $group_sql = "";
        if ($user_rights['group_id'] != "") {
            //Get list of users in this group
            $group_sql = "and (";
            $q = db_query("select username from redcap_user_rights where project_id = $project_id and group_id = {$user_rights['group_id']}");
            $i = 0;
            while ($row = db_fetch_assoc($q)) {
                if ($i != 0) $group_sql .= " or ";
                $i++;
                $group_sql .= "d.docs_comment like '% created by {$row['username']} on %'";
            }
            $group_sql .= ")";
        }
        
		// USER FILES (no type/category or folder given) - DEFAULT HIGH LEVEL
        if ($type == null)
        {
            // BUILT-IN TOP-LEVEL FOLDERS: DATA EXPORT FILES, PDF SURVEY ARCHIVE, RECORD-LOCKING PDFS
            if ($folder_id == null && $recycle_bin == 0)
            {
                // Get number of data export files
                $sql = "select count(*) from redcap_docs d where d.project_id = $project_id and d.export_file = 1 $group_sql";
                $thisFileCount = db_result(db_query($sql), 0);
                $thisFileCountText = RCView::span(['class'=>'nowrap'], $thisFileCount . " " . ($thisFileCount === 1 ? $lang['docs_76'] : $lang['api_99']));
                // Add the static folders for default home view
                $topLevelFolderIcon1 = "<i class='fa-solid fa-star fs10 text-primaryrc'></i>";
                $topLevelFolderIcon2 = "<i class='fa-solid fa-folder fs20 text-primaryrc me-1'></i>";
                $rows[] = [0 => $topLevelFolderIcon1, 1 => "$topLevelFolderIcon2 <a href='javascript:;' class='fs14' onclick=\"loadFileRepoTable('','export',0);\">{$lang['docs_30']}</a>", 2 => ['sort'=>$thisFileCount, 'display'=>$thisFileCountText], 3 => ['sort'=>"", 'display'=>""], 4 => "", 5 => "", 6 => "", 7 => ""];
                if ($hasSnapshotTriggersEnabled) {
                    // Get number of e-Consent files
                    $thisFileCount = count(PdfSnapshot::getPdfSnapshotArchiveFiles($Proj, (isset($user_rights['group_id']) ? $user_rights['group_id'] : null)));
                    $thisFileCountText = RCView::span(['class'=>'nowrap'], $thisFileCount . " " . ($thisFileCount === 1 ? $lang['docs_76'] : $lang['api_99']));
                    $rows[] = [0 => $topLevelFolderIcon1, 1 => "$topLevelFolderIcon2 <a href='javascript:;' class='fs14' onclick=\"loadFileRepoTable('','pdf_archive',0);\">{$lang['econsent_115']}</a>", 2 => ['sort'=>$thisFileCount, 'display'=>$thisFileCountText], 3 => ['sort'=>"", 'display'=>""], 4 => "", 5 => "", 6 => "", 7 => ""];
                }
                if ($recordLockingPdfEnabled) {
                    // Get number of record-locking PDFS
                    $thisFileCount = count(Locking::getLockedRecordPdfFiles($Proj, (isset($user_rights['group_id']) ? $user_rights['group_id'] : null)));
                    $thisFileCountText = RCView::span(['class'=>'nowrap'], $thisFileCount . " " . ($thisFileCount === 1 ? $lang['docs_76'] : $lang['api_99']));
                    $rows[] = [0 => $topLevelFolderIcon1, 1 => "$topLevelFolderIcon2 <a href='javascript:;' class='fs14' onclick=\"loadFileRepoTable('','record_lock_pdf_archive',0);\">{$lang['data_entry_489']}</a>", 2 => ['sort'=>$thisFileCount, 'display'=>$thisFileCountText], 3 => ['sort'=>"", 'display'=>""], 4 => "", 5 => "", 6 => "", 7 => ""];
                }
                // File Attachments added via rich text editor: Get number of attachments
                $thisFileCount = count(self::getMiscFileAttachments());
                $thisFileCountText = RCView::span(['class'=>'nowrap'], $thisFileCount . " " . ($thisFileCount === 1 ? $lang['docs_76'] : $lang['api_99']));
                $rows[] = [0 => $topLevelFolderIcon1, 1 => "$topLevelFolderIcon2 <a href='javascript:;' class='fs14' onclick=\"loadFileRepoTable('','attachments',0);\">{$lang['data_entry_600']}</a>", 2 => ['sort'=>$thisFileCount, 'display'=>$thisFileCountText], 3 => ['sort'=>"", 'display'=>""], 4 => "", 5 => "", 6 => "", 7 => ""];
                // Recycle Bin
                $sql = "SELECT count(*)
                        FROM redcap_docs d
                        LEFT JOIN redcap_docs_to_edocs e ON e.docs_id = d.docs_id
                        LEFT JOIN redcap_edocs_metadata m ON m.doc_id = e.doc_id
                        WHERE m.delete_date is not null AND m.date_deleted_server is null 
                        AND d.export_file = 0 $group_sql
                        AND d.project_id = ".PROJECT_ID;
                $thisFileCount = db_result(db_query($sql), 0);
                $thisFileCountText = RCView::span(['class'=>'nowrap'], $thisFileCount . " " . ($thisFileCount === 1 ? $lang['docs_76'] : $lang['api_99']));
                $rows[] = [0 => $topLevelFolderIcon1, 1 => "$topLevelFolderIcon2 <a href='javascript:;' class='fs14' onclick=\"loadFileRepoTable('','',1);\">{$lang['docs_1092']}</a>", 2 => ['sort'=>$thisFileCount, 'display'=>$thisFileCountText], 3 => ['sort'=>"", 'display'=>""], 4 => "", 5 => "", 6 => "", 7 => ""];
            }

            // Query for all non-export files
            if ($file_repository_enabled)
            {
                $roles = UserRights::getRoles();

                // FOLDER LIST: Query to display the current folder's sub-folders (do not show folders but only files in the Recycle Bin)
                if ($recycle_bin == 0)
                {
                    $dagFolders = [];
                    $roleFolders = [];
                    $adminOnlyFolders = [];
                    $folders = [];
                    $dagsql = ($user_rights['group_id'] == "") ? "" : "and (dag_id is null or dag_id = ".$user_rights['group_id'].")";
                    $rolesql = ($user_rights['role_id'] == "") ? "" : "and (role_id is null or role_id = ".$user_rights['role_id'].")";
                    $sql = "select folder_id, name, dag_id, role_id, admin_only from redcap_docs_folders 
                            where project_id = $project_id $dagsql $rolesql
                            and parent_folder_id " . (isinteger($folder_id) ? "= $folder_id" : "is null")." and deleted = 0";
                    if (!UserRights::isSuperUserNotImpersonator()) $sql .= " and admin_only = 0";
                    $q = db_query($sql);
                    while ($row = db_fetch_assoc($q))
                    {
                        $folders[$row['folder_id']] = $row['name'];
                        if ($row['dag_id'] != '') {
                            $dagFolders[$row['folder_id']] = $row['dag_id'];
                        }
                        if ($row['role_id'] != '') {
                            $roleFolders[$row['folder_id']] = $row['role_id'];
                        }
                        if ($row['admin_only'] == '1') {
	                        $adminOnlyFolders[$row['folder_id']] = true;
                        }
                    }
                    natcasesort($folders);
                    foreach ($folders as $thisFolderId=>$theseFolderNames) {
                        // Get count of files in this folder (including all its sub-folders)
                        $thisFileCount = self::getFileCount($thisFolderId, $recycle_bin);
                        $thisFileCountText = RCView::span(['class'=>'nowrap'], $thisFileCount . " " . ($thisFileCount === 1 ? $lang['docs_76'] : $lang['api_99']));
                        // Set DAG-restriction text (if applicable)
                        $dagRestrictText = isset($dagFolders[$thisFolderId]) ? "<span class='ms-3 fs11 text-secondary' style='position:relative;top:-2px;'>[".$lang['docs_91']."<code class='fs12 ms-1'>".strip_tags($Proj->getGroups($dagFolders[$thisFolderId]))."</code>]</span>" : "";
                        // Set Role-restriction text (if applicable)
                        $roleRestrictText = isset($roleFolders[$thisFolderId]) ? "<span class='ms-3 fs11 text-secondary' style='position:relative;top:-2px;'>[".$lang['docs_1085']."<code class='fs12 ms-1'>".strip_tags($roles[$roleFolders[$thisFolderId]]['role_name'])."</code>]</span>" : "";
                        // Set admin-only icon (if applicable)
                        $adminOnlyIcon = isset($adminOnlyFolders[$thisFolderId]) ? "<span class='ms-3 fs11 text-dangerrc' style='position:relative;top:-2px;'><i class='fas fa-user-shield me-1'></i>".$lang['docs_1156']."</span>" : "";
                        // Add row for this folder
                        $rows[] = [0=>RCView::checkbox(['id'=>'folder-select-'.$thisFolderId, 'class'=>'folder-select opacity50']), 1=>"<i class='fa-solid fa-folder fs20 me-1 text-primaryrc'></i> <a id='file-folder-$thisFolderId' href='javascript:;' class='fs14' onclick=\"loadFileRepoTable('$thisFolderId','',0);\">".strip_tags($theseFolderNames)."</a>"
                            . "<a id='folder-rename-$thisFolderId' href='javascript:;' class='fs12 opacity65 ms-3 folder-rename invisible' title='" . RCView::tt_js('docs_102') . "' onclick=\"fileRepoFolderRename($thisFolderId);\"><i class='fa-solid fa-pencil'></i></a>{$adminOnlyIcon}{$dagRestrictText}{$roleRestrictText}",
                            2=>['sort'=>$thisFileCount, 'display'=>$thisFileCountText], 3=>['sort'=>"", 'display'=>""], 4=>"",
                            5=>"", 6=>"<a href='javascript:;' class='fs15 opacity65 px-2' title='" . RCView::tt_js('docs_97') . "' onclick=\"fileRepoDeleteFolder($thisFolderId,$thisFileCount);\"><i class='fas fa-times'></i></a>",
                            7=>"<div class='fs11 text-primaryrc' title='folder_id'>$thisFolderId</div>"];
                    }
                }

                // Check folder's DAG access
                $noDagAccess = false;
                if ($user_rights['group_id'] != '' && $folder_id != null) {
                    $dag_id = self::folderHasDagRestriction($folder_id);
                    $noDagAccess = ($dag_id != false && $dag_id != $user_rights['group_id']);
                }

                // FILE LIST: If user is in a DAG, make sure they can access the current folder
                if (!FileRepository::userHasFolderAccess($folder_id) || $noDagAccess) {
                    // ERROR message: No access
                    $rows[] = [0 => "", 1 => "<span class='fs18 text-dangerrc'><i class='fa-solid fa-triangle-exclamation'></i> ".$lang['docs_89']."</span>", 2 => ['sort'=>"", 'display'=>""], 3 => ['sort'=>"", 'display'=>""], 4 => "", 5 => "", 6 => "", 7 => ""];
                } else {
                    // Query files for current folder
                    $sql = "select d.docs_id, d.docs_name, d.docs_size, e.stored_date, d.docs_comment, ff.folder_id, e.delete_date, e.doc_id
                            from redcap_docs_to_edocs de, redcap_edocs_metadata e, redcap_docs d
                            left join redcap_docs_attachments a on a.docs_id = d.docs_id
                            left join redcap_docs_folders_files ff on ff.docs_id = d.docs_id
                            left join redcap_docs_folders f on ff.folder_id = f.folder_id
                            where d.project_id = $project_id and d.export_file = 0 and a.docs_id is null
                            and de.docs_id = d.docs_id and de.doc_id = e.doc_id and e.date_deleted_server is null";
                    if ($recycle_bin) {
                        // Recycle bin: Show ALL files from ALL folders (flat display) - apply DAG/Role restriction here since we normally apply it at folder level outside the Recycle Bin
                        $dagsql = ($user_rights['group_id'] == "") ? "" : "and (f.dag_id is null or f.dag_id = ".$user_rights['group_id'].")";
                        $rolesql = ($user_rights['role_id'] == "") ? "" : "and (f.role_id is null or f.role_id = ".$user_rights['role_id'].")";
                        $sql .= " and e.delete_date is not null $dagsql $rolesql";
	                    if (!SUPER_USER) $sql .= " and f.admin_only = 0";
                    } else {
                        $sql .= " and e.delete_date is null and ff.folder_id " . (isinteger($folder_id) ? "= $folder_id" : "is null");
                    }
                    $q = db_query($sql);
                    $newrows = [];
                    while ($row = db_fetch_row($q)) {
                        // In case file was from a sub-folder of an admin-restricted folder, check it
                        if (!UserRights::isSuperUserNotImpersonator() && self::folderHasAdminRestriction($row[5])) continue;
                        // Name and doc_id
                        $faClass = Files::getFontAwesomeClass(getFileExt($row[1]));
                        $doc_id = $row[0];
                        $edoc_id = $row[7];
                        $filename = $row[1];
                        $dateDeleted = $row[6];
                        $row[1] = "<i class='$faClass fs18 me-1'></i> <a id='file-download-$doc_id' href='javascript:;' class='fs14' title='" . RCView::tt_js('docs_75') . "' onclick=\"fileRepoDownload($doc_id);\">".htmlentities($filename)."</a>"
                            . "<a id='file-rename-$doc_id' href='javascript:;' class='fs12 opacity65 ms-3 file-rename' style='display:none;' title='" . RCView::tt_js('docs_86') . "' onclick=\"fileRepoRename($doc_id);\"><i class='fa-solid fa-pencil'></i></a>";
                        unset($row[0], $row[6], $row[7]);
                        // Size
                        $row[2] = ['sort'=>$row[2], 'display'=>RCView::span(['class'=>'nowrap'], ($row[2] < 1048576 ? round($row[2] / 1024, ($row[2] < 100 ? 3 : 1)) . " KB" : round($row[2] / 1048576, 1) . " MB"))];
                        // Date/time
                        $row[3] = ['sort'=>str_replace([" ",":","-"],"",$row[3]), 'display'=>"<div class='nowrap file-time'>" . DateTimeRC::format_user_datetime($row[3], 'Y-M-D_24') . "</div>"];
                        // Comments
                        if ($row[4] == '') $row[4] = '&nbsp;&nbsp;&nbsp;&nbsp;';
                        $row[4] = "<div id='frc-{$doc_id}' class='fr-comments frc-edit' onclick='fileRepoEditComment($doc_id);' title='".RCView::tt_js('docs_1139')."'>".htmlentities($row[4])."</div>";
                        if (!$recycle_bin) {
                            // Share icons
                            $row[5] = !$displayShareIcon ? "" : "<a href='javascript:;' class='fs15 opacity65 px-2' title='" . RCView::tt_js('form_renderer_65') . "' onclick=\"fileRepoGetPublicLink($doc_id,'".htmlspecialchars(strip_tags($filename), ENT_QUOTES)."');\"><i class='fa-solid fa-arrow-up-from-bracket'></i></a>";
                            // Delete icon
                            $row[6] = "<a href='javascript:;' class='fs15 opacity65 px-2' title='" . RCView::tt_js('form_renderer_52') . "' onclick=\"fileRepoDelete($doc_id);\"><i class='fas fa-times'></i></a>";
                            // doc_id
                            $row[7] = "<div class='fs11 text-secondary' title='doc_id'>$edoc_id</div>";
                        }
                        // Reset indexes
                        $row = array_values($row);
                        if ($recycle_bin) {
                            array_pop($row);
                            // Add delete time and restore icon
                            $row[] = ['sort'=>str_replace([" ",":","-"],"",$dateDeleted), 'display'=>"<div class='nowrap file-time'>" . DateTimeRC::format_user_datetime($dateDeleted, 'Y-M-D_24') . "</div>"];
                            // Calculate time of permanent deletion from server
                            $datePermDeleted = (new DateTime($dateDeleted))->add(date_interval_create_from_date_string(Files::EDOCS_DELETION_DAYS_OLD.' days'))->format('Y-m-d H:i:s');
                            $datePermDeletedDays = round(datediff($datePermDeleted, NOW, "d"), 1);
                            $row[] = ['sort'=>$datePermDeletedDays, 'display'=>"<div class='nowrap file-time'>$datePermDeletedDays ".$lang['scheduling_25']."</div>"];
                            $restoreIcon = "<a href='javascript:;' class='d-block text-center fs18 opacity75 px-2 text-successrc' title='" . RCView::tt_js('docs_1088') . "' onclick=\"fileRepoRestore($doc_id);\"><i class='fa-solid fa-file-circle-plus'></i></a>";
                            if (UserRights::isSuperUserNotImpersonator()) {
                                // Add option to permanently delete the file
                                $restoreIcon .= "<a href='javascript:;' class='d-block text-center fs14 opacity75 px-2 mt-2 text-dangerrc' title='" . RCView::tt_js('docs_1144') . "' onclick=\"fileRepoDeleteNow($doc_id);\"><i class='fa-solid fa-trash-can'></i></a>";
                            }
                            $row[] = $restoreIcon;
                        } else {
                            // Add checkbox to first column
                            array_unshift($row, RCView::checkbox(['id'=>'file-select-'.$doc_id, 'class'=>'file-select opacity50']));
                        }
                        // Add to JSON
                        $newrows[$filename . "-" . $doc_id] = $row;
                    }
                    // Perform natural sorting by filename
                    natcaseksort($newrows);
                    $rows = array_merge($rows, array_values($newrows));
                }
            }
		}
        // DATA EXPORT FILES
        elseif ($type == 'export')
        {
            // Check each data export file to see if user has rights to download it (applying form-level export rights)
            $cannotExportFile = [];
            $sql = "select d.docs_id, l.data_values
                    from redcap_projects p, redcap_docs d, redcap_docs_to_edocs t, redcap_edocs_metadata e, ".Logging::getLogEventTable($project_id)." l
                    where d.project_id = p.project_id and d.export_file = 1 and e.delete_date is null and e.date_deleted_server is null 
                    and d.docs_id = t.docs_id and t.doc_id = e.doc_id and d.project_id = $project_id
                    and timestamp(l.ts) = e.stored_date and l.project_id = d.project_id and l.event = 'DATA_EXPORT'";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $json = json_decode($row['data_values'], true);
                if (!is_array($json) || !isset($json['fields'])) continue;
                foreach ($json['fields'] as $thisField) {
                    if (!isset($Proj->metadata[$thisField])) continue;
                    $thisForm = $Proj->metadata[$thisField]['form_name'];
                    if (!isset($user_rights['forms_export'][$thisForm])) continue;
                    $thisFormExportRight = $user_rights['forms_export'][$thisForm];
                    if ($thisFormExportRight != '1') {
                        // Add file to array to denote that user is not allowed to download it
                        $cannotExportFile[$row['docs_id']] = true;
                        break;
                    }
                }
            }

            // Query for all export files
            $sql = "select d.docs_id, d.docs_name, d.docs_size, e.stored_date, d.docs_comment
                    from redcap_docs d, redcap_docs_to_edocs de, redcap_edocs_metadata e
                    WHERE d.project_id = $project_id AND d.export_file = 1 and e.delete_date is null and e.date_deleted_server is null 
                    and de.docs_id = d.docs_id and de.doc_id = e.doc_id $group_sql 
                    ORDER BY d.docs_id desc";
            // Get rows of data to display via query
            $q = db_query($sql);
            while ($row = db_fetch_row($q))
            {
                // Name and doc_id
                $faClass = Files::getFontAwesomeClass(getFileExt($row[1]));
                $doc_id = $row[0];
                $filename = $row[1];
                if (isset($cannotExportFile[$doc_id])) {
                    $row[1] = "<i class='$faClass fs18 me-1 text-primaryrc'></i> <span class='fs14 text-dangerrc' style='cursor:not-allowed;' title='".RCView::tt_js('data_export_tool_294')."'>".htmlentities($filename)."</span>";
                } else {
                    $row[1] = "<i class='$faClass fs18 me-1 text-primaryrc'></i> <a href='javascript:;' class='fs14' title='".RCView::tt_js('docs_75')."' onclick=\"fileRepoDownload($doc_id);\">".htmlentities($filename)."</a>";
                }
                unset($row[0]);
                // Size
                $row[2] = ['sort'=>$row[2], 'display'=>RCView::span(['class'=>'nowrap'], ($row[2] < 1048576 ? round($row[2] / 1024, ($row[2] < 100 ? 3 : 1)) . " KB" : round($row[2] / 1048576, 1) . " MB"))];
                // Date/time
                $row[3] = ['sort'=>str_replace([" ",":","-"],"",$row[3]), 'display'=>"<div class='nowrap file-time'>" . DateTimeRC::format_user_datetime($row[3], 'Y-M-D_24') . "</div>"];
                // Comments
                $row[4] = "<div class='fr-comments'>".htmlentities($row[4])."</div>";
                // Delete
                $row[5] = "";
                // Add empty first column
                $row = array_values($row);
                array_unshift($row, "");
                // Add to JSON
                $rows[] = $row;
            }
        }
        // RECORD LOCKING PDF ARCHIVE
        elseif ($type == 'record_lock_pdf_archive')
        {
            if (!$recordLockingPdfEnabled) redirect(APP_PATH_WEBROOT.PAGE."?pid=".PROJECT_ID);
            // Put file info in array
            $files = Locking::getLockedRecordPdfFiles($Proj, (isset($user_rights['group_id']) ? $user_rights['group_id'] : null));
            // Build drop-down list of records to filter on
            $recordList = array();
            if (isset($files[0])) {
                $recordList[''] = $lang['docs_68'];
                foreach ($files as $attr) {
                    $recordList[$attr['record']] = $attr['record'];
                }
            }
            natcaseksort($recordList);
            $extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($recordList, false);
            foreach ($files as $file)
            {
                // Secondary ID / CRL
                $record_extra_label = isset($extra_record_labels[$file['record']]) ? " ".$extra_record_labels[$file['record']] : "";
                // Get arm of this record
                $this_arm = 1;
                if ($Proj->multiple_arms) {
                    foreach ($Proj->events as $this_arm=>$attr) {
                        if ($attr['id'] == $file['arm_id']) break;
                    }
                }
                // Set row values
                $row = [];
                $doc_id = $file['doc_id'];
                // Filename
                $filename = $file['doc_name'];
                if ($user_rights['data_export_tool'] == '1') {
                    $filename = "<div class='nowrap'><i class='fa-solid fa-file-pdf fs18 me-1 text-dangerrc'></i> <a href='javascript:;' class='fs14' title='".RCView::tt_js('docs_75')."' onclick=\"fileRepoDownload($doc_id,'lock_doc_id');\">".htmlentities($filename)."</a></div>";
                } else {
                    $filename = "<div class='nowrap'><i class='fa-solid fa-file-pdf fs18 me-1 text-dangerrc'></i> ".htmlentities($filename)."</div>";
                }
                $row[] = "";
                $row[] = $filename;
                // Record name
                $row[] = RCView::a(['style'=>'text-decoration:underline;padding:0 5px;font-size:14px;', 'title'=>$lang['docs_96'], 'href'=>APP_PATH_WEBROOT . "DataEntry/record_home.php?pid=".$Proj->project_id."&id={$file['record']}&arm=$this_arm"],
                    $file['record'] . " $record_extra_label"
                );
                // Time locked
                $row[] = ['sort'=>str_replace([" ",":","-"],"",$file['stored_date']), 'display'=>"<div class='nowrap file-time'>" . DateTimeRC::format_user_datetime($file['stored_date'], 'Y-M-D_24')."</div>"];
                // Size
                $row[] = ['sort'=>$file['doc_size'], 'display'=>"<div class='nowrap'>" . ($file['doc_size'] < 1048576 ? round($file['doc_size'] / 1024, ($file['doc_size'] < 100 ? 3 : 1)) . " KB" : round($file['doc_size'] / 1048576, 1) . " MB")."</div>"];
                // Add to JSON
                $rows[] = $row;
            }
        }
        // MISCELLANEOUS FILE ATTACHMENTS (FROM THE RICH TEXT EDITOR)
        elseif ($type == 'attachments')
        {
            // Put file info in array
            $files = self::getMiscFileAttachments();
            foreach ($files as $file)
            {
                $row = [0=>""];
                // Name and doc_id
                $filename = $file['docs_name'];
                $faClass = Files::getFontAwesomeClass(getFileExt($filename));
                $doc_id = $file['docs_id'];
                $edoc_id = $file['doc_id'];
                $doc_size = $file['docs_size'];
                $row[1] = "<i class='$faClass fs18 me-1'></i> <a id='file-download-$doc_id' href='javascript:;' class='fs14' title='" . RCView::tt_js('docs_75') . "' onclick=\"fileRepoDownload($doc_id);\">".htmlentities($filename)."</a>";
                // Size
                $row[2] = ['sort'=>$doc_size, 'display'=>RCView::span(['class'=>'nowrap'], ($doc_size < 1048576 ? round($doc_size / 1024, ($doc_size < 100 ? 3 : 1)) . " KB" : round($doc_size / 1048576, 1) . " MB"))];
                // Date/time
                $row[3] = ['sort'=>str_replace([" ",":","-"],"",$file['stored_date']), 'display'=>"<div class='nowrap file-time'>" . DateTimeRC::format_user_datetime($file['stored_date'], 'Y-M-D_24') . "</div>"];
                // Comments
                $row[4] = ($file['docs_comment'] == '') ? '&nbsp;&nbsp;&nbsp;&nbsp;' : $lang['docs_1151']." ".$file['docs_comment'];
                // Share icons
                $row[5] = !$displayShareIcon ? "" : "<a href='javascript:;' class='fs15 opacity65 px-2' title='" . RCView::tt_js('form_renderer_65') . "' onclick=\"fileRepoGetPublicLink($doc_id,'".htmlspecialchars(strip_tags($filename), ENT_QUOTES)."');\"><i class='fa-solid fa-arrow-up-from-bracket'></i></a>";
                // Delete icon
                $row[6] = "<a href='javascript:;' class='fs15 opacity65 px-2' title='" . RCView::tt_js('form_renderer_52') . "' onclick=\"fileRepoDelete($doc_id);\"><i class='fas fa-times'></i></a>";
                // doc_id
                $row[7] = "<div class='fs11 text-secondary' title='doc_id'>$edoc_id</div>";
                // Add to JSON
                $rows[] = $row;
            }
        }
        // ECONSENT PDF ARCHIVE
        elseif ($type == 'pdf_archive')
        {
            if (!$hasSnapshotTriggersEnabled) redirect(APP_PATH_WEBROOT.PAGE."?pid=".PROJECT_ID);
            // Selected record
            $selectedRecord = (isset($_GET['record']) ? $_GET['record'] : '');
            // Add file into array for display as table
            $hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
            // Put file info in array
            $files = PdfSnapshot::getPdfSnapshotArchiveFiles($Proj, (isset($user_rights['group_id']) ? $user_rights['group_id'] : null));
            // Build drop-down list of records to filter on
            $recordList = array();
            if (isset($files[0])) {
                $recordList[''] = RCView::getLangStringByKey("docs_68");
                foreach ($files as $attr) {
                    $recordList[$attr['record']] = $attr['record'];
                }
            }
            natcaseksort($recordList);
            $extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($recordList, false);
            foreach ($files as $file)
            {
                if ($selectedRecord != '' && $selectedRecord != $file['record']) continue;
                // Determine if some are e-Consent files
                $isEconsentPdf = (Econsent::econsentEnabledForSurvey($file['survey_id']) || trim($file['identifier'].$file['version'].$file['type']) != '');
                // Secondary ID / CRL
                $record_extra_label = isset($extra_record_labels[$file['record']]) ? " ".$extra_record_labels[$file['record']] : "";
                // If this a repeating form or event?
                $isRepeatingFormOrEvent = ($hasRepeatingFormsEvents && $Proj->isRepeatingFormOrEvent($file['event_id'], $Proj->surveys[$file['survey_id']]['form_name']));
                // Set row values
                $row = [];
                $doc_id = $file['doc_id'];
                $contains_completed_consent = $file['contains_completed_consent'] == '1';
                // Get arm of this record
                $this_arm = 1;
                if ($Proj->multiple_arms) {
                    foreach ($Proj->events as $this_arm=>$attr) {
                        if ($attr['id'] == $file['arm_id']) break;
                    }
                }
                // Filename
                $filename = $file['doc_name'];
                if ($file['survey_id'] == '' || $user_rights['forms_export'][$Proj->surveys[$file['survey_id']]['form_name']] == '1') {
                    $filename = "<div class='nowrap'><i class='fa-solid fa-file-pdf fs18 me-1 text-dangerrc'></i> <a href='javascript:;' class='fs14' title='".RCView::tt_js('docs_75')."' onclick=\"fileRepoDownload($doc_id,'doc_id');\">".htmlentities($file['doc_name'])."</a></div>";
                } else {
                    $filename = "<div class='nowrap'><i class='fa-solid fa-file-pdf fs18 me-1 text-dangerrc'></i> $filename</div>";
                }
                $row[] = "";
                $row[] = $filename;
                $row[] = ($contains_completed_consent
                        ? RCView::div(['class'=>'mr-2', 'title'=>RCView::tt_attr('econsent_181')], RCView::fa('fa-solid fa-user-pen opacity65 fs12'))
                        : RCView::div(['class'=>'mr-3 text-tertiary fs16'], "-")
                );
                // Record name
                $form_name = $Proj->surveys[$file['survey_id']]['form_name'] ?? "";
                $link = $form_name == "" ? "DataEntry/record_home.php?pid=".$Proj->project_id."&id={$file['record']}&arm=$this_arm" : "DataEntry/index.php?pid=".$Proj->project_id."&instance={$file['instance']}&event_id={$file['event_id']}&page=$form_name&id={$file['record']}";
                $row[] = RCView::a(['style'=>'text-decoration:underline;padding:0 5px;font-size:14px;', 'title'=>$lang['docs_96'], 'href'=>APP_PATH_WEBROOT . $link],
                    $file['record'] . " $record_extra_label"
                );
                // Survey
                if ($file['survey_id'] == null) {
                    $row[] = "";
                } else {
                    $row[] = ($Proj->surveys[$file['survey_id']]['title'] ?? "")
                        . ($Proj->longitudinal ? " (".$Proj->eventInfo[$file['event_id']]['name_ext'].")" : "")
                        . ($isRepeatingFormOrEvent ? " #".$file['instance'] : "");
                }
                // Survey Completion Time
                $row[] = ['sort'=>str_replace([" ",":","-"],"",$file['stored_date']), 'display'=>"<div class='nowrap file-time'>" . DateTimeRC::format_user_datetime($file['stored_date'], 'Y-M-D_24')."</div>"];
                // Identifier
                $row[] = $file['identifier'];
                // IP
                if ($pdf_econsent_system_ip) $row[] = $file['ip'];
                // Version
                $row[] = $file['version'];
                // Type
                $row[] = ($isEconsentPdf ? "<code class='nowrap fs11 d-block'>".$lang['info_45']."</code>" : "") . $file['type'];
                // Size
                $row[] = ['sort'=>$file['doc_size'], 'display'=>"<div class='nowrap'>" . ($file['doc_size'] < 1048576 ? round($file['doc_size'] / 1024, ($file['doc_size'] < 100 ? 3 : 1)) . " KB" : round($file['doc_size'] / 1048576, 1) . " MB")."</div>"];
                // Add to JSON
                $rows[] = $row;
            }
        }

        // Output JSON
        header('Content-Type: application/json');
		echo json_encode_rc(['data'=>$rows]);
	}


    // Get an array list of details of all files in the PDF Snapshot Archive
    public static function getPdfArchiveFileList()
    {
        global $lang, $user_rights, $Proj, $pdf_econsent_system_ip;
        // Add file into array for display as table
        $hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
        // Put file info in array
        $files = PdfSnapshot::getPdfSnapshotArchiveFiles($Proj, ($user_rights['group_id'] ?? null));
        // Build drop-down list of records to filter on
        $recordList = array();
        if (isset($files[0])) {
            $recordList[''] = RCView::getLangStringByKey("docs_68");
            foreach ($files as $attr) {
                $recordList[$attr['record']] = $attr['record'];
            }
        }
        natcaseksort($recordList);
        $extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($recordList, false);
        $rows = [];
        // Headers
        $row = [];
        $row[] = $lang['docs_77'];
        $row[] = $lang['econsent_181'];
        $row[] = $lang['global_49'];
        $row[] = $lang['survey_1586'];
        $row[] = $lang['survey_1585'];
        $row[] = $lang['survey_1172'];
        if ($pdf_econsent_system_ip) $row[] = $lang['survey_1221'];
        $row[] = $lang['survey_1173'];
        $row[] = $lang['survey_1174'];
        $row[] = $lang['docs_78'];
        $rows[] = $row;
        // Loop through all files
        foreach ($files as $file)
        {
            // Determine if some are e-Consent files
            $isEconsentPdf = (Econsent::econsentEnabledForSurvey($file['survey_id']) || trim($file['identifier'].$file['version'].$file['type']) != '');
            // Secondary ID / CRL
            $record_extra_label = isset($extra_record_labels[$file['record']]) ? " ".strip_tags($extra_record_labels[$file['record']]) : "";
            // If this a repeating form or event?
            $isRepeatingFormOrEvent = ($hasRepeatingFormsEvents && $Proj->isRepeatingFormOrEvent($file['event_id'], $Proj->surveys[$file['survey_id']]['form_name']));
            // Set row values
            $row = [];
            $row[] = $file['doc_name'];
            $row[] = ($file['contains_completed_consent'] == '1' ? RCView::getLangStringByKey("design_100") : RCView::getLangStringByKey("design_99"));
            $row[] = trim($file['record'] . " $record_extra_label");
            // Survey
            $row[] = strip_tags($Proj->surveys[$file['survey_id']]['title'] ?? "")
                . ($Proj->longitudinal ? " (".strip_tags($Proj->eventInfo[$file['event_id']]['name_ext']).")" : "")
                . ($isRepeatingFormOrEvent ? " #".$file['instance'] : "");
            // Survey Completion Time
            $row[] = $file['stored_date'];
            // Identifier
            $row[] = $file['identifier'];
            // IP
            if ($pdf_econsent_system_ip) $row[] = $file['ip'];
            // Version
            $row[] = $file['version'];
            // Type
            $row[] = ($isEconsentPdf ? $lang['info_45'] : "") . $file['type'];
            // Size
            $row[] = ($file['doc_size'] < 1048576 ? round($file['doc_size'] / 1024, ($file['doc_size'] < 100 ? 3 : 1)) . " KB" : round($file['doc_size'] / 1048576, 1) . " MB");
            // Add to JSON
            $rows[] = $row;
        }
       return $rows;
    }

    // Render the File Repository page
    public static function renderIndexPage()
    {
        global $Proj, $lang, $user_rights, $file_repository_enabled, $pdf_econsent_system_ip;

        // Get storage limit
        $getMaxStorage = self::getMaxStorage(PROJECT_ID);
        if (is_numeric($getMaxStorage)) $getMaxStorage = $getMaxStorage*1024*1024; // bytes

        // Get current storage usage
        $currentStorage = rounddown(self::getCurrentUsage(PROJECT_ID)*1024*1024); // bytes

        renderPageTitle("<i class=\"fas fa-folder-open\"></i> {$lang['app_04']}");

        //Instructions at top of page
        print "<p>{$lang['docs_28']}</p>";

        // Detect if any DAG groups exist. If so, give note below (unless user-uploading is disabled)
        if ($file_repository_enabled && (isset($_GET['id']) || !isset($_GET['type'])))
        {
            $dag_groups = $Proj->getGroups();
            if (count($dag_groups) > 0) {
                print  "<p style='color:#B00000;'>{$lang['global_02']}{$lang['colon']} {$lang['docs_90']}</p>";
            }
        }

        // If user is in DAG, only show info from that DAG and give note of that
        if ($user_rights['group_id'] != "" && isset($_GET['type']) && $_GET['type'] == "export") {
            print  "<p style='color:#A00000;'>{$lang['global_02']}{$lang['colon']} {$lang['docs_51']}</p>";
        }

        ## DATA EXPORT FILES (cannot view - error message)
        if (isset($_GET['type']) && $_GET['type'] == 'export' && $user_rights['data_export_tool'] != '1')
        {
            // If user does not have full export rights, let them know that they cannot view this tab
            print 	RCView::div(array('class'=>'yellow','style'=>'clear:both;margin-top:20px;padding:15px;'),
                RCView::img(array('src'=>'exclamation_orange.png')) .
                RCView::b($lang['global_03'].$lang['colon']) . " " . $lang['docs_64']
            );
        }

        // Build selection options for DAGS (if user is in a DAG, then only show ALL or their own DAG as options)
        if ($user_rights['group_id'] == '') {
            $dags = $Proj->getGroups();
        } else {
            $dags = [$user_rights['group_id']=>$Proj->getGroups($user_rights['group_id'])];
        }
        $dagDropdown = RCView::select(['id'=>'new-folder-dag', 'class'=>'x-form-text x-form-field'], [''=>$lang['docs_1083']]+$dags);

        // Build selection options for User Roles (if user is in a role, then only show ALL or their own role as options)
        if ($user_rights['role_id'] == '') {
            $roles = UserRights::getRoles();
            $roleOptions = [];
            foreach ($roles as $roleId=>$roleAttr) {
                $roleOptions[$roleId] = $roleAttr['role_name'];
            }
        } else {
            $roles = UserRights::getRoles();
            $roleOptions = [$user_rights['role_id']=>$roles[$user_rights['role_id']]['role_name']];
        }
        $roleDropdown = RCView::select(['id'=>'new-folder-role', 'class'=>'x-form-text x-form-field'], [''=>$lang['docs_1087']]+$roleOptions);

        // Set lang variables used in JS
        addLangToJS(['period', 'docs_74', 'sendit_32', 'docs_77', 'docs_78', 'docs_79', 'docs_80', 'docs_81', 'docs_82', 'sendit_03', 'sendit_04', 'sendit_05', 'global_01',
            'docs_84', 'docs_85', 'docs_46', 'docs_1148', 'form_renderer_52', 'global_19', 'global_53', 'docs_86', 'docs_87', 'docs_88', 'questionmark', 'docs_95', 'data_entry_491',
            'docs_92', 'docs_93', 'docs_94', 'global_49', 'survey_1586', 'survey_1585', 'survey_1172', 'survey_1221', 'survey_1173', 'survey_1174', 'docs_97', 'docs_98', 'docs_99',
            'docs_100', 'docs_101', 'docs_102', 'docs_103', 'docs_104', 'docs_105', 'docs_106', 'docs_107', 'docs_1082', 'docs_1084', 'api_46', 'design_170', 'design_172', 'docs_1102',
            'docs_1086', 'docs_1087', 'docs_1088', 'docs_1089', 'docs_1090', 'global_79', 'docs_1091', 'docs_1093', 'docs_1089', 'docs_1094', 'docs_1095', 'docs_1096', 'design_174',
            'docs_1099', 'docs_1111', 'docs_1112', 'survey_133', 'docs_1117', 'docs_1118', 'docs_1119', 'docs_1120', 'docs_1121', 'docs_1122', 'docs_1123', 'docs_1125', 'docs_1126',
            'docs_1127', 'docs_1128', 'docs_1129', 'docs_1130', 'docs_1132', 'docs_1133', 'folders_11', 'docs_1141', 'docs_1142', 'docs_1143', 'docs_1139', 'docs_72', 'global_47',
            'docs_1145', 'docs_1146', 'docs_1147', 'global_03', 'docs_1149', 'docs_1150', 'docs_1152', 'docs_1153', 'econsent_181', 'docs_1155', 'docs_1157']);
        loadJS('Libraries/clipboard.js');
        loadJS('FileRepository.js');
        loadCSS('FileRepository.css');
        ?>
        <script type="text/javascript">
            var maxUploadSizeFileRepository = <?=maxUploadSizeFileRepository()*1024*1024?>; // bytes
            var maxStorageSizeFileRepository = <?=$getMaxStorage ?? "null"?>; // bytes
            var currentStorageSizeFileRepository = <?=$currentStorage?>; // bytes
            var pdf_econsent_system_ip = <?=(int)$pdf_econsent_system_ip?>;
            var dagDropdown = '<?=js_escape($dagDropdown)?>';
            var roleDropdown = '<?=js_escape($roleDropdown)?>';
            var dagCount = <?=count($Proj->getGroups())?>;
            var roleCount = <?=count(UserRights::getRoles())?>;
        </script>
        <div id='file-repository-table-parent'>
            <table id='file-repository-table'></table>
        </div>
        <?php
    }

    // Render the file download landing page
    public static function renderFileDownloadPage($fileAttr)
    {
        global $lang, $isMobileDevice;
        if (!(is_array($fileAttr) && !empty($fileAttr))) exit;
        $edoc_id = $fileAttr['doc_id'];
        // Obtain download link
        $fileDownloadLink = APP_PATH_SURVEY_FULL . "index.php?__file={$_GET['__file']}&__passthru=DataEntry%2Ffile_download.php&id=$edoc_id&doc_id_hash=" . Files::docIdHash($edoc_id);
        // Display landing page for downloading or viewing the file
        $objHtmlPage = new HtmlPage();
        $objHtmlPage->setPageTitle(strip_tags($fileAttr['docs_name']));
        $objHtmlPage->PrintHeader();
        ?>
        <style type="text/css">
            body { background-color: #fcfcfc; }
            #footer, #pagecontainer { max-width: 1000px; text-align: center; }
            #footer { margin-top: 20px; margin-bottom: 50px; }
            #footer a { text-decoration: none !important; }
        </style>
        <?php
        // Button
        $button = RCView::div(['class'=>'mt-4 mb-5'],
            RCView::a(['class'=>'btn btn-xs btn-primaryrc fs18 text-white', 'href'=>$fileDownloadLink], '<i class="fa-solid fa-arrow-down"></i> '.$lang['api_46'])
        );
        // Display image
        $image = "<div class='text-secondary fs16 pt-4 pb-5'>{$lang['docs_1101']}</div>";
        $fileExt = trim(strtolower(getFileExt($fileAttr['docs_name'])));
        $allowedExtTypes = array("jpeg", "jpg", "gif", "png", "bmp", "svg");
        $image_view_page = APP_PATH_SURVEY . "index.php?__file={$_GET['__file']}&__passthru=".urlencode("DataEntry/image_view.php")."&doc_id_hash=".Files::docIdHash($edoc_id)."&id=$edoc_id";
        if (in_array($fileExt, $allowedExtTypes)) {
            //Set max-width for logo (include for mobile devices)
            $img_attach_width = (isset($isMobileDevice) && $isMobileDevice) ? '250' : '900';
            // Get img dimensions (local file storage only)
            $thisImgMaxWidth = $img_attach_width;
            $styleDim = "max-width:{$thisImgMaxWidth}px;";
            list ($thisImgWidth, $thisImgHeight) = Files::getImgWidthHeightByDocId($edoc_id, true);
            $nativeDim = '0';
            if (is_numeric($thisImgHeight)) {
                $thisImgMaxHeight = round($thisImgMaxWidth/$thisImgWidth*$thisImgHeight);
                if ($thisImgWidth < $thisImgMaxWidth) {
                    // Use native dimensions
                    $styleDim = "width:{$thisImgWidth}px;max-width:{$thisImgWidth}px;height:{$thisImgHeight}px;max-height:{$thisImgHeight}px;";
                    $nativeDim = '1';
                } else {
                    // Shrink size
                    $styleDim = "width:{$thisImgMaxWidth}px;max-width:{$thisImgMaxWidth}px;height:{$thisImgMaxHeight}px;max-height:{$thisImgMaxHeight}px;";
                }
            }
            // Inline image
            $image = "<div class='pt-3'><div class='text-secondary fs16 mb-2'>{$lang['docs_1100']}</div><img src='$image_view_page' alt='".RCView::tt_js("survey_1140")."' style='border:1px solid #ddd;$styleDim' nativedim='$nativeDim' onload='fitImg(this);' class='rc-dt-img'></div>";
        }
        // Display PDF
        elseif ($fileExt == 'pdf') {
            $image = "<div class='pt-3'><div class='text-secondary fs16 mb-2'>{$lang['docs_1100']}</div>
                        ".PDF::renderInlinePdfContainer($image_view_page)."
                    </div>";
        }
        // Output the download button (and image, if applicable)
        $faClass = Files::getFontAwesomeClass(getFileExt($fileAttr['docs_name']));
        print RCView::div(['class'=>'mt-3 mx-5 mb-5'],
            RCView::div(['class'=>'fs24'], "<i class='$faClass me-1'></i> " . RCView::escape($fileAttr['docs_name'])) .
            $button .
            $image
        );
        $objHtmlPage->PrintFooter();
    }

    // Obtain the File Repository max storage limit
    public static function getMaxStorage($project_id=null)
    {
        // If PID is provided, then check the project-level override first, otherwise default to the system-level setting
        if (isinteger($project_id)) {
            // Get project-level setting, if set
            $Proj = new Project($project_id);
            if (isinteger($Proj->project['file_repository_total_size']) && $Proj->project['file_repository_total_size'] > 0) {
                return $Proj->project['file_repository_total_size'];
            }
        }
        // If we got this far, then return the system-level setting, if set
        if (isinteger($GLOBALS['file_repository_total_size']) && $GLOBALS['file_repository_total_size'] > 0) {
            return $GLOBALS['file_repository_total_size'];
        }
        // If neither project nor global settings are set, then return null
        return null;
    }

    // Obtain currently used storage amount for project (in MB)
    public static function getCurrentUsage($project_id)
    {
        $sql = "select round(sum(d.docs_size)/1024/1024,2) from 
                redcap_docs d, redcap_edocs_metadata m, redcap_docs_to_edocs e
                left join redcap_docs_attachments a on a.docs_id = e.docs_id
                where d.project_id = $project_id and d.export_file = 0 and e.docs_id = d.docs_id
                and a.docs_id is null and m.doc_id = e.doc_id and m.delete_date is null";
        $doc_usage  = db_result(db_query($sql),0);
        if ($doc_usage == '') $doc_usage = 0;
        return $doc_usage;
    }

    // Get array of all folders and sub-folders to be used in drop-down list.
    // Use --Folder and ----Subfolder to denote hierarchy.
    public static function getFolderList($project_id, $current_folder_id=null)
    {
        global $user_rights, $lang;
        // Build initial array of all folders, filtering by DAG and role if user in DAG or role
        $dagsql = ($user_rights['group_id'] == "") ? "" : "and (dag_id is null or dag_id = ".$user_rights['group_id'].")";
        $rolesql = ($user_rights['role_id'] == "") ? "" : "and (role_id is null or role_id = ".$user_rights['role_id'].")";
        $sql = "select folder_id, name, parent_folder_id 
                from redcap_docs_folders
                where project_id = $project_id and deleted = 0 $dagsql $rolesql 
                order by parent_folder_id, name regexp '^[A-Z]', abs(name), left(name,1), 
                         CONVERT(SUBSTRING_INDEX(name,'-',-1),UNSIGNED INTEGER), CONVERT(SUBSTRING_INDEX(name,'_',-1),UNSIGNED INTEGER), name";
        $q = db_query($sql);
        $folders = [];
        while ($row = db_fetch_assoc($q)) {
            $folders[$row['folder_id']] = $row;
        }
        // Loop through the folders and denote subfolder depth for each (if folder is in main directory with no parent, depth=0)
        $maxDepth = 0;
        $foldersLabelsOrdered = [];
        foreach ($folders as $folder_id=>$row) {
            // Find the depth of the parent and add +1
            if ($row['parent_folder_id'] != null && isset($folders[$row['parent_folder_id']]['depth'])) {
                $parentDepth = $folders[$row['parent_folder_id']]['depth'] + 1;
            } else {
                // Default 0 depth
                $parentDepth = 0;
                // Add highest-level folders here to see the labels array
                $foldersLabelsOrdered[$folder_id] = strip_tags2($row['name']);
            }
            $folders[$folder_id]['depth'] = $parentDepth;
            // Set maxDepth
            if ($parentDepth > $maxDepth) $maxDepth = $parentDepth;
        }
        // Build flat array of folder->sub-folder relations
        $subfolderList = [];
        foreach ($folders as $folder_id => $row) {
            if ($row['parent_folder_id'] == null) $row['parent_folder_id'] = "";
            $subfolderList[$row['parent_folder_id']][] = $folder_id;
        }
        // Now loop through sub-folder array
        $folderLabels = [""=>$lang['docs_1131']];
        foreach ($subfolderList as $parent_id=>$subfolder_ids) {
            $folderLabels = self::processSubfolderListDisplay($folders, $subfolder_ids, $folderLabels, $subfolderList, $current_folder_id);
        }
        // Return array of all folders in correct hierarchical order
        return $folderLabels;
    }

    // Function to help process getFolderList in a recursive fashion
    public static function processSubfolderListDisplay($folders, $subfolder_ids, $folderLabels, $subfolderList, $current_folder_id)
    {
        global $lang;
        foreach ($subfolder_ids as $subfolder_id) {
            $folderLabels[$subfolder_id] = str_repeat("--- ", $folders[$subfolder_id]['depth']) . strip_tags2($folders[$subfolder_id]['name']);
            // Append [current folder] text to label if this folder is the current context folder
            if ($current_folder_id != null && $subfolder_id == $current_folder_id) {
                $folderLabels[$subfolder_id] .= " ".$lang['docs_1134'];
            }
            // If folder has sub-folders, then loop inside them recursively
            if (isset($subfolderList[$subfolder_id])) {
                $folderLabels = self::processSubfolderListDisplay($folders, $subfolderList[$subfolder_id], $folderLabels, $subfolderList, $current_folder_id);
            }
        }
        return $folderLabels;
    }
}