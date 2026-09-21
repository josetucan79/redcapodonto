<?php

class EconsentController extends Controller
{
    // Display main setup page
    public function index()
    {
        $ec = new Econsent();
        $this->render('HeaderProject.php', $GLOBALS);
        $ec->renderSetup();
        $this->render('FooterProject.php');
    }

    // Disable e-Consent for a survey
    public function disable()
    {
        $ec = new Econsent();
        $ec->disable($_POST['consent_id'] ?? null, $_POST['survey_id'] ?? null);
    }

    // Re-enable e-Consent for a survey
    public function reenable()
    {
        $ec = new Econsent();
        $ec->reenable($_POST['consent_id'] ?? null, $_POST['survey_id'] ?? null);
    }

    // Display AJAX output for Edit eConsent Setup dialog
    public function editSetup()
    {
        $ec = new Econsent();
        $ec->editSetup($_POST['consent_id'] ?? null, $_POST['survey_id'] ?? null);
    }

    // Display AJAX output for Save eConsent Setup dialog
    public function saveSetup()
    {
        $ec = new Econsent();
        $ec->saveSetup($_POST['consent_id'] ?? null, $_POST['survey_id'] ?? null);
    }

    // Load table via ajax
    public function loadTable()
    {
        $ec = new Econsent();
        $ec->loadTable($_GET['display_inactive'] ?? false);
    }

    // Display dialog for user to choose a survey that does not have e-Consent enabled yet
    public function surveySelectDialog()
    {
        $ec = new Econsent();
        $ec->surveySelectDialog();
    }

    // Display add consent form dialog
    public function addConsentForm()
    {
        $ec = new Econsent();
        $ec->addConsentForm($_POST['consent_id'] ?? null);
    }

    // Remove a consent form
    public function removeConsentForm()
    {
        $ec = new Econsent();
        $ec->removeConsentForm($_POST['consent_id'] ?? null, $_POST['consent_form_id'] ?? null);
    }

    // Display the consent form inline in a dialog
    public function viewConsentForm()
    {
        $ec = new Econsent();
        $ec->viewConsentForm($_GET['consent_id'] ?? null, $_GET['consent_form_id'] ?? null);
    }

    // Save consent form
    public function saveConsentForm()
    {
        $ec = new Econsent();
        $ec->saveConsentForm($_POST['consent_id'] ?? null, $_POST['consent_form_id'] ?? null);
    }

    // Upload consent form PDF file
    public function uploadConsentForm()
    {
        $ec = new Econsent();
        $ec->uploadConsentForm($_GET['consent_id'] ?? null, $_GET['consent_form_id'] ?? null);
    }

    // Delete consent form in table (because wrong file type)
    public function deleteConsentForm()
    {
        $ec = new Econsent();
        $ec->deleteConsentForm($_POST['consent_id'] ?? null, $_POST['consent_form_id'] ?? null);
    }

    // View list of all consent form versions for a survey
    public function viewConsentFormVersions()
    {
        $ec = new Econsent();
        $ec->viewConsentFormVersions($_POST['consent_id'] ?? null, $_POST['survey_id'] ?? null);
    }

}