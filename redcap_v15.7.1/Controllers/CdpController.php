<?php

use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\AutoAdjudicator;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Mapper as CdpMapper;
use Vanderbilt\REDCap\Classes\Utility\TransactionHelper;

class CdpController extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    private function getMapperInstance($project_id) {
        $config = System::getConfigVals();
        $mapper = new CdpMapper(new Project($project_id), $config);
        return $mapper;
    }

    private function getPaginationParams() {
        $page =  intval($_GET['_page'] ?? 1);
        $perPage =  intval($_GET['_per_page'] ?? 500);
        return [$page, $perPage];
    }


    /**
     * route, get a list of revisions
     *
     * @return string json response
     */
    public function test()
    {
        $response = array('test' => 123);
        $this->printJSON($response, $status_code=200);
    }

    /**
     * auto-adjudication
     * get stats about the data to be auto-adjudicated
     *
     * @return void
     */
    public function getDdpRecordsDataStats()
    {
        try {
            list($page, $perPage) = $this->getPaginationParams();
            $project_id = $_GET['pid'] ?? null;
            $user_id = defined('USERID') ? USERID : false;
            if(empty($project_id)) throw new \Exception("A project ID must be provided", 1);
            $adjudicator = new AutoAdjudicator($project_id, $user_id);
            $response = $adjudicator->getDdpRecordsMetadata($page, $perPage);
            $this->printJSON($response);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $status_code = $e->getCode();
            $this->printJSON($message, $status_code);
        }
    }

    /**
     * auto-adjudication
     * adjudicate all data found on database
     * for a project
     *
     * @return void
     */
    public function processCachedData()
    {
        try {
            $project_id = $_GET['pid'] ?? null;
            $user_id = defined('USERID') ? USERID : false;
            $post = $this->getPhpInput();
            $background = $post['background'] ?? false;
            $send_feedback = $post['send_feedback'] ?? false;
            if(!$project_id) throw new \Exception("A project ID must be provided", 400);
            $adjudicator = new AutoAdjudicator($project_id, $user_id);
            $records = $adjudicator->processCachedData($background, $send_feedback);
            $this->printJSON($records);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $status_code = $e->getCode();
            $this->printJSON($message, $status_code);
        }
    }

    /**
     * auto-adjudication
     * adjudicate all data found on database
     * for a project and a specific record
     * @return void
     */
    public function processField()
    {
        try {
            $project_id = $_GET['pid'] ?? null;
            $user_id = defined('USERID') ? USERID : false;
            $post = $this->getPhpInput();
            $record_id = $_POST['record'] ?? $post['record'] ?? null;
            $event_id = $_POST['event_id'] ?? $post['event_id'] ?? null;
            $field_name = $_POST['field_name'] ?? $post['field_name'] ?? null;
            $dry_run = $_POST['dry_run'] ?? $post['dry_run'] ?? false;
            // throw new \Exception("A project ID must be provided", 400);
            if(!($project_id)) throw new \Exception("A project ID must be provided", 400);
            if(!($record_id)) throw new \Exception("A record ID must be provided", 400);
            if(!($event_id)) throw new \Exception("An event ID must be provided", 400);
            if(!($field_name)) throw new \Exception("A field name must be provided", 400);
            $adjudicator = new AutoAdjudicator($project_id, $user_id);
            if($dry_run) {
                $response = TransactionHelper::executeDryRun([$adjudicator, 'processField'], [$record_id, $event_id, $field_name]);
            }else {
                $response = $adjudicator->processField($record_id, $event_id, $field_name);
            }
            $this->printJSON($response);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $status_code = $e->getCode();
            $this->printJSON($message, $status_code);
        }
    }
    
    public function getPreviewData()
    {
        $project_id = @$_GET['pid'];
        $record_identifier = @$_GET['record_identifier'];
        if(empty($project_id) || empty($record_identifier)) {
            $this->printJSON($message='A project ID and a record identifier must be provided', 400);
        }
        try {
            $ddp = new DynamicDataPull($project_id, $realtime_webservice_type='FHIR');
            $response = $ddp->getPreviewData($record_identifier);
            $this->printJSON($response, 200);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $status_code = $e->getCode();
            $this->printJSON($message, $status_code);
        }
    }

/**
     * get the logs
     *
     * @return void
     */
    public function getSettings()
    {
        try {
            $project_id = $_GET['pid'] ??  null;
            $mapper = $this->getMapperInstance($project_id);
            $settings = $mapper->getSettings();
            $this->printJSON($settings, $status_code=200);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $code = $e->getCode();
            $error = new JsonError(
                $title = 'error retrieving settings',
                $detail = sprintf("There was a problem retrieving the settings for project ID %s - %s", $project_id, $message),
                $status = $code,
                $source = PAGE // get the current page
            );
            $this->printJSON($error, $code);
        }
    }

    /**
     * get the logs
     *
     * @return void
     */
    public function setSettings()
    {
        try {
            $project_id = $_GET['pid'] ?? null;
            $post = $this->getPhpInput();
            $settings = $post['settings'] ?? [];
            $mapper = $this->getMapperInstance($project_id);
            $response = $mapper->saveSettings($settings);
            $this->printJSON($response, $status_code=200);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $response = [
                'error' => true,
                'message' => $message,
            ];
            $status_code = 400;
            $this->printJSON($response, $status_code);
        }
    }
    /**
     * get the logs
     *
     * @return void
     */
    public function setMappings()
    {
        try {
            $project_id = $_GET['pid'] ?? null;
            $post = $this->getPhpInput();
            $mappings = $post['mappings'] ?? [];
            
            $mapper = $this->getMapperInstance($project_id);
            $response = $mapper->saveMappings($mappings);
            $mapper->handleSettingModification();
            $this->printJSON($response, $status_code=200);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $response = [
                'error' => true,
                'message' => $message,
            ];
            $status_code = 400;
            $this->printJSON($response, $status_code);
        }
    }

    /**
     * TODO
     *
     * @return void
     */
    public function importMappings()
    {
        try {
            $file = $_FILES['file'] ?? null;
            if(!$file) {
                $response = ['message' => 'no file received'];
                $this->printJSON($response, $status_code=200);
            }
            $file_path = $file['tmp_name'] ?? null;
            
            $project_id = $_GET['pid'] ?? null;
            $mapper = $this->getMapperInstance($project_id);
            $response = $mapper->importMappings($file_path);
            $this->printJSON($response, $status_code=200);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $response = [
                'error' => true,
                'message' => $message,
            ];
            $status_code = 400;
            $this->printJSON($response, $status_code);
        }

    }

    /**
     * TODO
     *
     * @return void
     */
    public function exportMappings()
    {
        try {
            $project_id = $_GET['pid'] ?? null;
            $mapper = $this->getMapperInstance($project_id);
            $download_url = $mapper->exportMappings();
            $response = compact('download_url');
            $this->printJSON($response, $status_code=200);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $response = [
                'error' => true,
                'message' => $message,
            ];
            $status_code = 400;
            $this->printJSON($response, $status_code);
        }
    }  
    
    public function download()
    {
        try {
            $project_id = @$_GET['pid'];
            $file_name = @$_GET['file_name'];
            $mapper = $this->getMapperInstance($project_id);
            return $mapper->download($file_name);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $response = [
                'error' => true,
                'message' => $message,
            ];
            $status_code = 400;
            $this->printJSON($response, $status_code);
        }
    }

}