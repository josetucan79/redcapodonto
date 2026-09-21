<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManagerFactory;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules\RulesManager;

class CdisTokenController extends BaseController
{


    public function __construct()
    {
        parent::__construct();
    }

    public function getSettings() {
        $project_id = $_GET['pid'] ?? false;
        $rulesManager = RulesManager::instance();
        $userRules = $rulesManager->getRulesByProject($project_id);
        $globalRule = $rulesManager->getOrMakeGlobalRuleForProject($project_id);

        $users = RulesManager::getProjectUsers($project_id);
        /* $userRules = [
            [ 'id' => 1, 'user' => 2, 'allow' => true, 'username' => 'francesco'],
            [ 'id' => 2, 'user' => 3, 'allow' => true, 'username' => 'delacqf'],
            [ 'id' => 3, 'user' => 4, 'allow' => false, 'username' => 'luna'],
            [ 'id' => 4, 'user' => 5, 'allow' => true, 'username' => 'stella'],
            [ 'id' => 5, 'user' => 3, 'allow' => false, 'username' => 'penny'],
        ]; */
        $response = [
            'userRules' => $userRules,
            'globalRule' => $globalRule,
            'users' => array_values($users),
        ];
        $this->printJSON($response);
    }

    public function getRules() {
        $rules = [
            [ 'id' => 1, 'user' => 2, 'allow' => true, 'username' => 'francesco'],
            [ 'id' => 2, 'user' => 3, 'allow' => true, 'username' => 'delacqf'],
            [ 'id' => 3, 'user' => 4, 'allow' => false, 'username' => 'luna'],
            [ 'id' => 4, 'user' => 5, 'allow' => true, 'username' => 'stella'],
            [ 'id' => 5, 'user' => 3, 'allow' => false, 'username' => 'penny'],
        ];
        $response = [
            'rules' => $rules,
        ];
        $this->printJSON($response);
    }

    public function saveChanges() {
        try {
            $project_id = $_GET['pid'] ?? false;
            $post = $this->getPhpInput();
            $changes = $post['changes'] ?? false;
            if (!$changes) {
                throw new Exception('Invalid JSON payload', 400);
            }
            $rulesManager = RulesManager::instance();
            $results = $rulesManager->applyChanges($project_id, $changes);
            $response = ['success' => true, 'results' => $results];
            $this->printJSON($response);
        } catch (Exception $e) {
            $code = $e->getCode();
            $code = ($code >= 400) ? $code : 400;
            $response = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
            $this->printJSON($response, $code);
        }
    }

    public function testRules() {
        try {
            $project_id = $_GET['pid'] ?? false;
            $fhirSystem = FhirSystem::fromProjectId($project_id);
            $tokenManager = FhirTokenManagerFactory::create($fhirSystem, $user_id=null, $project_id);
            $token = $tokenManager->getToken();
            $response = [
                'success' => false,
                'token' => $token,
            ];
        } catch (\Exception $e) {
            $code = $e->getCode();
            $code = ($code >= 400) ? $code : 400;
            $response = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
            $this->printJSON($response, $code);
        }

    }

    public function addRule() {
        $post = $this->getPhpInput();
        $response = $post;
        $this->printJSON($response);
    }

    public function updateRule() {
        $post = $this->getPhpInput();
        $response = $post;
        $this->printJSON($response);
    }
    
    public function deleteRule() {
        $post = $this->getPhpInput();
        $response = $post;
        $this->printJSON($response);
    }

    

}