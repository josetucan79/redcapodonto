<?php
namespace Vanderbilt\REDCap\DynamicDataPull;

use Exception;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirPatientPortal;
use Vanderbilt\REDCap\Classes\Utility\ToastGenerator;

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $autocloseTime = 5000;
        $portal = new FhirPatientPortal();
        $user_id = defined('UI_ID') ? UI_ID : null;
        $action = $_POST['action'] ?? null;
        switch ($action) {
            case 'remove-project':
                $project_id = $_POST['pid'] ?? null;
                $success = $portal->removeEhrProject($project_id, $user_id);
                if($success) ToastGenerator::flashToast('Project was removed.', 'Success', ['autoClose' => $autocloseTime]);
                else throw new Exception("project was NOT removed", 1);
                break;
            
            case 'add-project':
                $project_id = $_POST['pid'] ?? null;
                $success = $portal->addEhrProject($project_id, $user_id);
                if($success) ToastGenerator::flashToast('project was added', 'Success', ['autoClose' => $autocloseTime]);
                else throw new Exception("project was NOT added", 1);
                break;
            
            case 'create-patient-record':
                $project_id = $_POST['pid'] ?? null;
                $mrn = $_POST['mrn'] ?? null;
                $record = $_POST['record'] ?? null;
                $html = $portal->createPatientRecord($project_id, $mrn, $record);
                ToastGenerator::flashToast($html, 'Success', ['autoClose' => false]);
                break;
            
            default:
                break;
        }
    } catch (\Throwable $th) {
        ToastGenerator::flashToast($th->getMessage(), 'Error', ['autoClose' => false]);
    } finally {
        $ehrPage = APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . '/ehr.php';
        $previous_page = $_SERVER['HTTP_REFERER'];
        $parsed_url = parse_url($previous_page);
        $path = $parsed_url['path'] ?? '';
        // make sure the previous page is ehr.php
        if (preg_match('/\/ehr\.php$/', $path)!==1) {
            $previous_page = $ehrPage;
        }
        redirect($previous_page);
    }
}

echo 'Nothing to see here';