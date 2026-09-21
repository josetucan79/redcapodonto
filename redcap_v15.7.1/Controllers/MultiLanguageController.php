<?php

use MultiLanguageManagement\MultiLanguage;

class MultiLanguageController extends Controller
{
    // Render the setup page inside a project
    public function projectSetup()
    {
        if (!(defined("PROJECT_ID") && PROJECT_ID > 0)) {
            throw new Exception("Must be in project context!");
        }
        // Header
        $this->render("HeaderProject.php", $GLOBALS);
        // Content
        if (MultiLanguage::showProjectMenuItem(PROJECT_ID)) {
            require APP_PATH_DOCROOT . "MultiLanguage/setup.php";
        }
        else {
            $noAccessMsg = RCView::tt_i("config_07", array(
                $GLOBALS['project_contact_email'],
                $GLOBALS['project_contact_name']
            ), false); 
            print '<div class="red"><i class="fas fa-exclamation-circle"></i> '.RCView::tt("global_05", "b").'<br><br>'.$noAccessMsg.'</div>';
        }
        // Footer
        $this->render("FooterProject.php");
    }

    // Render the setup page for the system (Control Center)
    public function systemConfig() 
    {
        // Need some globals for header/footer rendering
        global $lang, $objHtmlPage;
        // Header
        require APP_PATH_DOCROOT . "ControlCenter/header.php";
        // Content
        require APP_PATH_DOCROOT . "MultiLanguage/setup.php";
        // Footer
        require APP_PATH_DOCROOT . "ControlCenter/footer.php";
    }

    public function ajax()
    {
        require APP_PATH_DOCROOT . "MultiLanguage/ajax.php";
    }
}