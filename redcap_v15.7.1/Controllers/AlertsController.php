<?php

class AlertsController extends Controller
{
	// Render the setup page
	public function setup()
	{
        $alerts = new Alerts();
        $alerts->renderSetup();
	}

    // Find filename of edoc by doc_id
    public function getEdocName()
    {
        $edoc = isset($_POST['edoc']) && is_numeric($_POST['edoc']) ? (int)$_POST['edoc'] : null;
        $alerts = new Alerts();
        $alerts->getEdocNameById($edoc);
    }

    // Create a new alert or update an existing alert
    public function saveAlert()
    {
        $alerts = new Alerts();
        $alerts->saveAlert();
    }

    // Copy an alert
    public function copyAlert()
    {
        $alerts = new Alerts();
        $alerts->copyAlert();
    }

    // Delete an alert
    public function deleteAlert()
    {
        $alerts = new Alerts();
        $alerts->deleteAlert();
    }

    // Delete an alert (permanently)
    public function deleteAlertPermanent()
    {
        $alerts = new Alerts();
        $alerts->deleteAlertPermanent();
    }

    // Download an alert's attachment file
    public function downloadAttachment()
    {
        $alerts = new Alerts();
        $alerts->downloadAttachment();
    }

    // Upload an alert's attachment file
    public function saveAttachment()
    {
        $alerts = new Alerts();
        $alerts->saveAttachment();
    }

    // Delete an alert's attachment file
    public function deleteAttachment()
    {
        $alerts = new Alerts();
        $alerts->deleteAttachment();
    }

    // Determine if we need to display repeating instrument textbox option when manually queueing an alert for a record
    public function displayRepeatingFormTextboxQueue()
    {
        $alerts = new Alerts();
        $alerts->displayRepeatingFormTextboxQueue();
    }

    // Delete a queued record for a given alert
    public function deleteQueuedRecord()
    {
        $alerts = new Alerts();
        $alerts->deleteQueuedRecord();
    }

    // Display table of an alert's message contents
    public function previewAlertMessage()
    {
        $alerts = new Alerts();
        $alerts->previewAlertMessage();
    }

    // Display dialog of an alert's message contents for a specific record
    public function previewAlertMessageByRecordDialog()
    {
        $alerts = new Alerts();
        $alerts->previewAlertMessageByRecordDialog();
    }

    // Display table inside dialog of an alert's message contents for a specific record
    public function previewAlertMessageByRecord()
    {
        $alerts = new Alerts();
        $alert_sent_log_id = (isset($_POST['alert_sent_log_id']) ? $_POST['alert_sent_log_id'] : null);
        $aq_id = (isset($_POST['aq_id']) ? $_POST['aq_id'] : null);
        $alerts->previewAlertMessageByRecord($alert_sent_log_id, $aq_id);
    }

    // Re-evaluate all alerts in a project
	public function reevalAlerts()
	{
		$alerts = new Alerts();
		$alerts->reevalAlerts(@$_GET['action']);
	}

    // Move an alert
    public function moveAlert()
    {
        $alerts = new Alerts();
        $alerts->moveAlert();
    }

    // Download CSV - Alerts
    public function downloadAlerts()
    {
        $alerts = new Alerts();
        $alerts->downloadAlerts();
    }

    // Upload CSV - Alerts
    public function uploadAlerts() {
	    $alerts = new Alerts();
	    $alerts->uploadAlerts();
    }

    // Alerts - Upload Download CSV Help Page
    public function uploadDownloadHelp() {
        $alerts = new Alerts();
        $alerts->uploadDownloadHelp();
    }

    // Download CSV - Notification Logs
    public function downloadLogs()
    {
        $alerts = new Alerts();
        $alerts->downloadLogs();
    }

    public function getSendgridData() {
        $alerts = new Alerts();
        $alerts->getSendgridData();
    }
}