<?php

class PdfSnapshotController extends Controller
{
    // Display main setup page
    public function index()
    {
        $rs = new PdfSnapshot();
        $this->render('HeaderProject.php', $GLOBALS);
        $rs->renderSetup();
        $this->render('FooterProject.php');
    }

    // Save snapshot
    public function saveSetup()
    {
        $rs = new PdfSnapshot();
        $rs->saveSetup($_GET['snapshot_id'] ?? null);
    }

    // Display AJAX output for Edit eConsent Setup dialog
    public function editSetup()
    {
        $rs = new PdfSnapshot();
        $rs->editSetup($_POST['snapshot_id'] ?? null);
    }

    // Load table via ajax
    public function loadTable()
    {
        // Output JSON
        header('Content-Type: application/json');
        $rs = new PdfSnapshot();
        echo $rs->loadTable($_GET['display_inactive'] ?? false);
    }

    // Disable snapshot trigger
    public function disable()
    {
        $rs = new PdfSnapshot();
        $rs->disable($_POST['snapshot_id'] ?? null);
    }

    // Copy snapshot trigger
    public function copy()
    {
        $rs = new PdfSnapshot();
        $rs->copy($_POST['snapshot_id'] ?? null);
    }

    // Re-enable snapshot trigger
    public function reenable()
    {
        $rs = new PdfSnapshot();
        $rs->reenable($_POST['snapshot_id'] ?? null);
    }

    // Display dialog to manually trigger or re-trigger PDF Snapshots
    public function triggerSnapshotDialog()
    {
        $rs = new PdfSnapshot();
        $rs->manualTriggerDialog($_POST['record'] ?? null, $_POST['event_id'] ?? null, $_POST['form'] ?? null, $_POST['instance'] ?? 1);
    }

    // Manually trigger or re-trigger PDF Snapshots
    public function triggerSnapshot()
    {
        $rs = new PdfSnapshot();
        list ($snapshotsToRepoTriggered, $snapshotsToFieldTriggered) = $rs->manualTriggerSave($_POST['snapshot_id'] ?? null, $_POST['record'] ?? null, $_POST['event_id'] ?? null, $_POST['form'] ?? null, $_POST['instance'] ?? 1);
        print $snapshotsToRepoTriggered+$snapshotsToFieldTriggered;
    }

}