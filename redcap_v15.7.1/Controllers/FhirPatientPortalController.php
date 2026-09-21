<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\FhirCookieDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirPatientPortal;


class FhirPatientPortalController extends BaseController
{

    public function addProject() {
        $portal = new FhirPatientPortal();
        $user_id = UI_ID;
        $post = $this->getPhpInput();
        $project_id = @$post['pid'];
        $success = $portal->addEhrProject($project_id, $user_id);
        if($success) {
            $this->printJSON([
                'message' => 'project was added',
            ]);
        }else {
            $this->printJSON([
                'message' => 'project was NOT added',
            ], 400);
        }
    }

    public function removeProject() {
        $portal = new FhirPatientPortal();
        $user_id = UI_ID;
        $post = $this->getPhpInput();
        $project_id = @$post['pid'];
        $post = $this->getPhpInput();
        $success = $portal->removeEhrProject($project_id, $user_id);
        if($success) {
            $this->printJSON([
                'message' => 'project was removed',
            ]);
        }else {
            $this->printJSON([
                'message' => 'project was NOT removed',
            ], 400);
        }
        
    }

    /**
     * remove the information that marks
     * the Fhir Launch context
     *
     * @return void
     */
    public function removeFhirLaunchContext() {
        FhirCookieDTO::destroy(FhirLauncher::COOKIE_NAME);
        if (session_status() != PHP_SESSION_ACTIVE) return;
        unset($_SESSION[FhirLauncher::COOKIE_NAME]);
    }

    public function createPatientRecord() {
        $post = $this->getPhpInput();
        $project_id = @$post['pid'];
        $patientMrn = @$post['mrn'];
        $record = @$post['record'];
        $portal = new FhirPatientPortal();
        try {
            $html = $portal->createPatientRecord($project_id, $patientMrn, $record);
            print($html);
        } catch (\Throwable $th) {
            exit($th->getMessage());
        }
        /* try {
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->printJSON(['message'=>$th->getMessage()], $th->getCode());
        } */
    }


}