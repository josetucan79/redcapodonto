<?php

class FileRepositoryController extends Controller
{
    // Render the File Repository page
    public function index()
    {
        $this->render('HeaderProject.php', $GLOBALS);
        FileRepository::renderIndexPage();
        $this->render('FooterProject.php');
    }

    // Output HTML for file sharing dialog
    public function share()
    {
        print FileRepository::shareFile($_POST['doc_id']??null);
    }

    // Get HTML of breadcrumb links
    public function getBreadcrumbs()
    {
        print FileRepository::getBreadcrumbs($_POST['folder_id']??null, $_POST['type']??null, $_POST['recycle_bin']??0);
    }

    // Move files/folders to new location
    public function move()
    {
        FileRepository::move($_POST['folders']??"", $_POST['docs']??"", $_POST['current_folder']??"", $_POST['new_folder']??"");
    }

    // Get HTML for drop-down containing the list of all folders (when moving files/folders)
    public function getFolderDropdown()
    {
        $folder_id = $_POST['current_folder']??"";
        print RCView::select(['id'=>'filerepo-folder-list', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:100%;'], FileRepository::getFolderList(PROJECT_ID, $folder_id), $folder_id, 400);
    }

    // Download file from the File Repository page
    public function download()
    {
        FileRepository::download();
    }

    // Delete files from the File Repository page
    public function downloadMultiple()
    {
        FileRepository::downloadMultiple($_GET['folders']??"", $_GET['docs']??"", $_GET['current_folder']??"");
    }

    // Delete file from the File Repository page
    public function delete()
    {
        print FileRepository::delete($_POST['delete']??null) ? '1' : '0';
    }

    // Delete files from the File Repository page
    public function deleteMultiple()
    {
        FileRepository::deleteMultiple($_POST['delete']??null);
    }

    // Delete file from the File Repository page PERMANENTLY (admins only)
    public function deleteNow()
    {
        print FileRepository::deleteNow($_POST['delete']??null) ? '1' : '0';
    }

    // Edit a file's comment
    public function editComment()
    {
        print FileRepository::editComment($_POST['doc_id']??null, $_POST['comment']??null) ? '1' : '0';
    }

    // Restore file in the File Repository page
    public function restore()
    {
        FileRepository::restore();
    }

    // Rename folder in the File Repository page
    public function createFolder()
    {
        FileRepository::createFolder();
    }

    // Rename folder in the File Repository page
    public function renameFolder()
    {
        FileRepository::renameFolder();
    }

    // Delete folder from the File Repository page
    public function deleteFolder()
    {
        FileRepository::deleteFolder();
    }

    // Upload a file to the File Repository page
    public function upload()
    {
        FileRepository::upload();
    }

    // Rename a file in the File Repository page
    public function rename()
    {
        FileRepository::rename();
    }

    // Load file/folder list via AJAX
    public function getFileList()
    {
		print FileRepository::getFileList($_GET['folder_id']??null, $_GET['type']??null, $_GET['recycle_bin']??0);
    }

    // Return current space usage (in bytes)
    public function getCurrentUsage()
    {
        print rounddown(FileRepository::getCurrentUsage(PROJECT_ID)*1024*1024);
    }

}