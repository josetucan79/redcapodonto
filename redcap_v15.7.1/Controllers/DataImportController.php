<?php

class DataImportController extends Controller
{
	// Render Data Import Tool page
	public function index()
	{
		$this->render('HeaderProject.php', $GLOBALS);
		DataImport::renderDataImportToolPage();
		$this->render('FooterProject.php');
	}
	
	// AJAX request to view a project's background imports
	public function loadBackgroundImportsTable()
	{
		DataImport::loadBackgroundImportsTable();
	}

	// Cancel a background import (only possible if user is the uploader)
	public function cancelBackgroundImport()
	{
		DataImport::cancelBackgroundImport($_POST['import_id'] ?? null, $_POST['action'] ?? 'view');
	}

	// View the details of a background import
	public function viewBackgroundImportDetails()
	{
		DataImport::viewBackgroundImportDetails($_GET['import_id'] ?? null);
	}

	// Download errors from a background import
	public function downloadBackgroundErrors()
	{
		DataImport::downloadBackgroundErrors($_GET['import_id'] ?? null);
	}

	// Download the CSV data for the records that failed to import from a background import due to errors
	public function downloadBackgroundErrorData()
	{
		DataImport::downloadBackgroundErrorData($_GET['import_id'] ?? null);
	}

	// Download import template CSV file
	public function downloadTemplate()
	{
		DataImport::downloadCSVImportTemplate();
	}

	// Check the fields in the CSV headers prior to the background import official import
	public function fieldPreCheck()
	{
		DataImport::fieldPreCheck($_POST['fields'] ?? null);
	}
}