<?php

class BulkRecordDeleteController extends Controller
{
    private $rmd;
    private $armId;
    private $groupId;

    public function __construct()
    {
        $this->rmd = new BulkRecordDelete();
        $this->armId = $_POST['arm_id'] ?? null;
        $this->groupId = $_POST['group_id'] ?? null;
        $this->rmd->validateUserRights();
    }

    public function index()
    {
        $rmd = new \BulkRecordDelete();
        $rmd->renderIndexPage();
    }

    /**
     * @throws Exception
     */
    public function fetchRecords()
    {
        $limitOffset = (int)$_GET['limitOffset'];
        $this->rmd->fetchRecords($this->armId, $this->groupId, BulkRecordDelete::FETCH_RECORDS_LIMIT + 1, $limitOffset); // fetching 1 more than the max allowed per page to determine whether we should display `Next` button.
    }

    public function checkRecordsExist(): void
    {
        global $Proj;
        $dbRecords = Records::getRecordList($Proj->getId(), $this->groupId, false, false, $this->armId, null, 0, $_POST['records']);
        $diff = array_diff($_POST['records'], $dbRecords);
        echo json_encode(array("response" => $diff));
    }

    public function renderFormEventList()
    {
        global $Proj;
        $armNumber = $_GET['arm_number'];
        $intCastResult = settype($armNumber, 'integer');
        if (!$intCastResult || !in_array($armNumber, array_keys($Proj->events))) {
            $response = [
                'errors' => 'invalid arm selected'
            ];
        } else {
            $response = [
                'form_event_list' => $this->rmd->getFormEventList($armNumber)
            ];
        }
        echo json_encode($response);
    }

    // AJAX request to view a project's background deletions
    public function loadBackgroundDeletionsTable()
    {
        $rmd = new \BulkRecordDelete();
        $rmd->loadBackgroundDeletionsTable();
    }
    // Cancel a background deletion (only possible if user is the uploader)
    public function cancelBackgroundDelete()
    {
        BulkRecordDelete::cancelBackgroundDelete($_POST['delete_id'] ?? null, $_POST['action'] ?? 'view');
    }

    // View the details of a background deletion
    public function viewBackgroundDeleteDetails()
    {
        BulkRecordDelete::viewBackgroundDeleteDetails($_GET['delete_id'] ?? null);
    }

    // Download errors from a background deletion
    public function downloadBackgroundErrors()
    {
        BulkRecordDelete::downloadBackgroundErrors($_GET['delete_id'] ?? null);
    }
}