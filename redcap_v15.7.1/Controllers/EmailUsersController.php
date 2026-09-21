<?php

use Vanderbilt\REDCap\Classes\Email\DynamicVariablesParser;
use Vanderbilt\REDCap\Classes\Email\EmailScheduler;
use Vanderbilt\REDCap\Classes\Email\EmailUsers;
use Vanderbilt\REDCap\Classes\Email\EmailUsersCSV;

class EmailUsersController extends BaseController
{
    private $username;
    private $ui_id;

    public function __construct()
    {
        parent::__construct();
        $this->username = defined('USERID') ? USERID : null;
        $this->ui_id = defined('UI_ID') ? UI_ID : null;
        $this->checkPermissions();
    }

    private function checkPermissions() {
        if (!ACCOUNT_MANAGER) {
            $response = [
                'message' => 'Unauthorized access. You must be an account manager',
                'code' => $code = 401,
            ];
            $this->printJSON($response, $code);
            exit;
        };
    }

    public function getSettings() {
        $emailUsers = new EmailUsers($this->username);
        $settings = $emailUsers->getSettings();
        $response = ['data' => $settings];
        $this->printJSON($response);
    }

    public function getQueries() {
        $page = intval($_GET['_page'] ?? 1);
        $perPage = intval($_GET['_per_page'] ?? 50);
        $emailUsers = new EmailUsers($this->username);
        $queries = $emailUsers->getQueries($page, $perPage);
        $response = ['data' => $queries];
        $this->printJSON($response);
    }

    public function getMessages() {
        $page = intval($_GET['_page'] ?? 1);
        $perPage = intval($_GET['_per_page'] ?? 50);
        $emailUsers = new EmailUsers($this->username);
        $result = $emailUsers->getMessages($page, $perPage, $metadata);

        $response = [
            'success' => true,
            'data' => $result,
            'metadata' => $metadata,
        ];
        $this->printJSON($response);
    }

//    public function deleteMessage() {
//        try {
//            $id = $_GET['id'] ?? null;
//            $emailUsers = new EmailUsers($this->username);
//            $emailUsers->deleteMessage($id);
//            $response = ['success' => true];
//            $this->printJSON($response);
//        } catch (\Throwable $th) {
//            $response = [
//                'message' => $th->getMessage(),
//                'code' => $code = $th->getCode(),
//            ];
//            $this->printJSON($response, $code);
//        }
//    }

    public function saveQuery() {
        try {
            $data = $this->getPhpInput();
            $id = $data['id'] ?? null;
            $query = $data['query'] ?? null;
            $name = $data['name'] ?? '';
            $description = $data['description'] ?? '';
    
            $emailUsers = new EmailUsers($this->username);
            $emailUsers->saveQuery($query, $name, $description, $id);
            $response = ['success' => true];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $response = [
                'message' => $th->getMessage(),
                'code' => $code = $th->getCode(),
            ];
            $this->printJSON($response, $code);
        }
    }

    public function deleteQuery() {
        try {
            $id = $_GET['id'] ?? null;
            $emailUsers = new EmailUsers($this->username);
            $emailUsers->deleteQuery($id);
            $response = ['success' => true];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $response = [
                'message' => $th->getMessage(),
                'code' => $code = $th->getCode(),
            ];
            $this->printJSON($response, $code);
        }
    }

    public function testQuery() {
        try {
            $data = $this->getPhpInput();

            $query = $data['query'] ?? null;
            $page = intval($_GET['_page'] ?? EmailUsers::PAGE_START);
            $per_page = intval($_GET['_per_page'] ?? EmailUsers::PER_PAGE);
    
            $emailUsers = new EmailUsers($this->username);
            $result = $emailUsers->testQuery($page, $per_page, $query, $metadata);

            $response = [
                'success' => true,
                'data' => $result,
                'metadata' => $metadata,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $response = [
                'message' => $th->getMessage(),
                'code' => $code = $th->getCode(),
            ];
            $this->printJSON($response, $code);
        }
    }

    public function downloadCSV() {
        $filename = $_GET['file'] ?? null;
        $emailUsersCSV = new EmailUsersCSV();
        $emailUsersCSV->downloadCSV($filename);
    }

    public function generateCSV() {
        try {
            $data = $this->getPhpInput();
            $query = $data['query'] ?? null;
            $emailUsers = new EmailUsers($this->username);
            $emailUsersCSV = new EmailUsersCSV();
            $list = $emailUsers->getList($query);
            $fileName = $emailUsersCSV->generateCSV($list);
            $url = EmailUsersCSV::getDownloadURL($fileName);
            $response = [
                'success' => true,
                'filename' => $fileName,
                'url' => $url,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $response = [
                'message' => $th->getMessage(),
                'code' => $code = $th->getCode(),
            ];
        $this->printJSON($response, $code);
    }
    }

    public function previewMessage() {
        try {
            $data = $this->getPhpInput();
            $email = $data['email'] ?? '';
            $subject = $data['subject'] ?? '';
            $body = $data['body'] ?? '';

            $parser = new DynamicVariablesParser();
            $parsedSubject = $parser->parse($subject, $email);
            $parsedBody = $parser->parse($body, $email);
            $response = [
                'subject' => $parsedSubject,
                'body' => $parsedBody,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $response = [
                'message' => $th->getMessage(),
                'code' => $code = $th->getCode(),
            ];
            $this->printJSON($response, $code);
        }
    }

    public function sendEmails() {
        try {
            $data = $this->getPhpInput();

            $emailBody = $data['body'] ?? null;
            $emailSubject  = $data['subject'] ?? null;
            $userEmail = $data['from'] ?? null; // the preferred user email to use for sending
            $queryObject = $data['query'] ?? null; // the query that will be used to get the list of users
            $options = [
                'fromName' => $data['fromName'] ?? null,
                'cc' => $data['cc'] ?? null,
                'bcc' => $data['bcc'] ?? null,
            ];

            $emailUsers = new EmailUsers($this->username);
            $emailScheduler = new EmailScheduler(USERID, $userEmail, $options);

            $queueKeys = $emailUsers->sendEmails($emailScheduler, $emailSubject, $emailBody, $queryObject, $metadata);

            $emailUsers->saveMessage($this->ui_id, $emailSubject, $emailBody);
            
            $response = [
                'queueKeys' => $queueKeys,
                'metadata' => $metadata,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $response = [
                'message' => $th->getMessage(),
                'code' => $code = $th->getCode(),
            ];
            $this->printJSON($response, $code);
        }
    }

}