<?php

use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\DesignChecker;
use Vanderbilt\REDCap\Classes\Fhir\FhirUser;
use Vanderbilt\REDCap\Classes\Fhir\SerializableException;

class DataMartController extends BaseController {

    /**
     * maximum number of simultaneous revisions per hour
     */
    const MAX_REVISIONS_PER_HOUR = 10;

    /**
     * instance of the model
     *
     * @var DataMart
     */
    private $model;

    /**
     * numeric ID of the currrent user
     *
     * @var int
     */
    private $userid;

    /**
     * current project ID
     *
     * @var int
     */
    private $project_id;

    /**
     *
     * @var FhirUser
     */
    private $fhirUser;

    public function __construct()
    {
        parent::__construct();
        $this->userid = defined('UI_ID') ? UI_ID : null;
        $this->project_id = defined('PROJECT_ID') ? PROJECT_ID : intval(@$_REQUEST['pid']);
        // $this->enableCORS();
        $this->model = new DataMart($this->userid);
        $this->fhirUser = new FhirUser(UI_ID, $this->project_id);
    }

    public function checkDataMartPermission($reason=null, $code=403) {
        $reason = $$reason ?? "You are not authorized to use Data Mart features";
        if(!$this->fhirUser->can_use_datamart) throw new Exception($reason, $code);
        return true;
    }

    /**
     * route, get a list of revisions
     *
     * @return string json response
     */
    public function revisions()
    {
        $request_id = @$_REQUEST['request_id'];
        if($request_id)
        {
            $revision = $this->model->getRevisionFromRequest($request_id);
            if(!$revision)
            {
                $error = new JsonError(
                    $title = 'revision not found',
                    $detail = sprintf("no revision associated to the request ID %s has been found", $request_id),
                    $status = 400,
                    $source = PAGE // get the current page
                );
                $this->printJSON($error, $status_code=400);
            }
            $response = array($revision);
        }else
        {
            $response = $this->model->getRevisions($this->project_id);
        }
        $this->printJSON($response, $status_code=200);
    }

    /**
     * route, get the user
     *
     * @return string json response
     */
    public function getUser()
    {
        /* 
         * static version
        $modelClassName = get_class($this->model);
        $response =   call_user_func_array(array($modelClassName, "getUserInfo"), array($this->username, $this));
        */
        $response =   $this->model->getUser($this->project_id);
        $this->printJSON($response, $status_code=200);
    }

    /**
     * add a revision
     *
     * @return string
     */
    public function addRevision()
    {
        try {
            $this->checkDataMartPermission();
            
            $post_data = file_get_contents("php://input");
            
            $params = json_decode($post_data, $assoc=true);
            $settings = array(
                'project_id'            => $this->project_id,
                'request_id'            => $params['request_id'] ?? $_REQUEST['request_id'] ?? null,
                'mrns'                  => $params['mrns'] ?? null,
                'date_range_categories' => $params['date_range_categories'] ?? null,
                'fields'                => $params['fields'] ?? null,
                'date_min'              => $params['date_min'] ?? null,
                'date_max'              => $params['date_max'] ?? null,
            );
            $response = $this->model->addRevision($settings);
            if($response==true)
                $this->printJSON($response, $status_code=200);
            else
                $this->printJSON($response, $status_code=400);
        } catch (\Throwable $th) {
            $response = [
                'message' => $th->getMessage(),
                'code' => $status_code = $th->getCode(),
            ];
            $this->printJSON($response, $status_code);
        }
    }

    /**
     * delete a revision
     *
     * @return void
     */
    public function deleteRevision()
    {
        try {
            $this->checkDataMartPermission();
            // gete the data from the DELETE method
            $data = file_get_contents("php://input");
            $params = json_decode($data, $assoc=true);
            $id = @$params['revision_id'];
            $deleted = $this->model->deleteRevision($id);
            if($deleted==true)
            {
                $response = array('data'=>array('id'=>$id));
                $this->printJSON($response, $status_code=200);
            } else
            {
                throw new Exception("The revision ID $id could not be deleted", 400);
                
            }
        } catch (\Throwable $th) {
            //throw $th;
            // typical structure for a json object
            $error = new JsonError(
                $title = 'Revision not deleted',
                $detail = $th->getMessage(),
                $status = $th->getCode(),
                $source = PAGE
            );
            $this->printJSON($error, $status_code=400);
        }
        
    }
    /**
     * export a revision
     *
     * @return void
     */
    public function exportRevision()
    {
        try {
            $this->checkDataMartPermission();
            $revision_id = @$_REQUEST['revision_id'];
            $format = @$_REQUEST['format'] ?: 'csv';
            $csv_delimiter = @$_REQUEST['csv_delimiter'] ?: User::getCsvDelimiter();
            $fields = @$_REQUEST['fields'] ?: [];
            if(!is_array($fields)) $fields = explode(',', $fields); // make sure it is an array
            $this->model->exportRevision($revision_id, $fields, $format, $csv_delimiter);
        } catch (\Throwable $th) {
            $response = [
                'success' => false,
                'message' => $th->getMessage(),
            ];
            $this->printJSON($response, $th->getCode());
        }
    }

    /**
     * parse a file for a revision
     *
     * @return string
     */
    public function importRevision()
    {
        try {
            $this->checkDataMartPermission();
            $uploaded_files = FileManager::getUploadedFiles();
            $files = $uploaded_files['files'];
            $file = reset($files); // get the first element in the array of files
            if($file)
            {
                $data = $this->model->importRevision($file);
                $this->printJSON($data, $status_code=200);
            }else
            {
                throw new Exception("A file must be provided to import a revision", 400);
            }
        } catch (\Throwable $th) {
            $error = new JsonError(
                $title = 'Import error',
                $detail = $th->getMessage(),
                $status = $th->getCode(),
                $source = PAGE // get the current page
            );
            $this->printJSON($error, $status);
        }
        
    }

    public function getSettings()
    {
        global $lang;
        try {
            $this->checkDataMartPermission();
            $requestIdKey = 'request_id';
            $request_id = array_key_exists($requestIdKey, $_REQUEST) ? $_REQUEST[$requestIdKey] : false;
            $settings = $this->model->getSettings($this->project_id, $request_id, $lang);
            $this->printJSON($settings, $status_code=200);
        } catch (\Exception $e) {
            $code = $e->getCode();
            if($code<400) $code = 400;
            $this->printJSON($e->getMessage(), $code);
        }
    }

    /**
     * helper function that sends an error response if the maximum
     * number of requests for a page has been reached
     *
     * @param integer $limit
     * @return string|null
     */
    public function throttle($limit=10)
    {
        $page = PAGE; // get the current page
        $throttler = new Throttler();
        
        if($throttler->throttle($page, $limit))
        {
            // typical structure for a json object
            $error = new JsonError(
                $title = 'Too Many Requests',
                $detail = sprintf('The maximum of %u simultaneus request%s has been reached. Try again later.', $limit, $singular=($limit===1) ? '' : 's' ),
                $status = Throttler::ERROR_CODE,
                $source = PAGE
            );

            $this->printJSON($error , $status_code=$status);
        }
    }

    /**
     * method for testing the throttle
     *
     * @return string
     */
    private function throttleTest()
    {
        $this->throttle(1); //limit to a maximum of 1
        sleep(10);
        $this->printJSON(array('success' => true, 'errors'=>array()), $status_code=200);
    }

    /**
     * run a revision
     *
     * @return string
     */
    public function runRevision()
    {
        try {
            $this->checkDataMartPermission();
            $post_data = file_get_contents("php://input");
            $post_params = json_decode($post_data, $assoc=true);
            $revisionId = @$post_params['revision_id'];
            $mrn = @$post_params['mrn'];
            $background = $post_params['background']??false;

            if(!$background) {
                $response = $this->model->runRevision($revisionId, $mrn);
                $this->printJSON($response, $status_code=200);
            }else {
                return $this->scheduleRevision();
            }
        } catch (\Exception $e) {
            $exception = new SerializableException($e->getMessage(), $code=$e->getCode());
            $this->printJSON($exception, $code);
        }
    }

    public function scheduleRevision()
    {
        try {
            $this->checkDataMartPermission();
            $post_data = file_get_contents("php://input");
            $post_params = json_decode($post_data, $assoc=true);
            $revisionId = @$post_params['revision_id'];
            $mrn_list = $post_params['mrn_list'] ?? null;
            $send_feedback = @$post_params['send_feedback'];

            $message = $this->model->runBackgroundProcess($revisionId, $mrn_list, $send_feedback);
            $response = [
                'success' => true,
                'message' => $message,
            ];
            $this->printJSON($response, $status_code=200);

        } catch (\Exception $e) {
            $exception = new SerializableException($e->getMessage(), $code=$e->getCode());
            $this->printJSON($exception, $code);
        }
    }

    /**
     * approve a revision
     *
     * @return string
     */
    public function approveRevision()
    {
        try {
            $this->checkDataMartPermission();
            $post_data = file_get_contents("php://input");
            $post_params = json_decode($post_data, $assoc=true);
            $id = $post_params['revision_id'] ?? null;
            
            $revision = $this->model->approveRevision($id);
            if($revision)
            {
                $response = array('data'=>$revision);
                $this->printJSON($response, $status_code=200);
            }else
            {
                throw new Exception("The revision ID $id could not be approved", 400);
            }
        } catch (\Throwable $th) {
            $response = [
                'success' => false,
                'message' => $th->getMessage(),
            ];
            $this->printJSON($response, $th->getCode());
        }
        
    }

    public function index()
    {
        try {
            ob_start();
            
            extract($GLOBALS);
            $browser_supported = !$isIE || vIE() > 10;
            $datamart_enabled = DataMart::isEnabled($this->project_id);
            $this->checkDataMartPermission();

            // ********** HTML **********
            ?>
            <?php if(!$browser_supported) : ?>
                <h3>
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>This feature is not available for your browser.</span>
                </h3>
            <?php elseif($datamart_enabled) : ?>
                <div style="max-width: 900px;">
                    <div id="datamart-design-checker"></div>
                    <p>
                    Listed below is the current Data Mart configuration for this project.
                    If you have appropriate privileges, you may run the "Fetch clinical data" button to retrieve data from the EHR.
                    When fetching, any existing patients will have new data added to their record in the project.
                    If you have permission to create new revisions of the current Data Mart configuration,
                    you will see a "Request a configuration change" button at the bottom of the page.
                    When submitted, all configuration revisions must be approved by a REDCap administrator before taking effect in the project.
                    </p>
                    <div id="datamart-app"></div>
                </div>

                <style>
                @import url('<?= APP_PATH_JS ?>vue/components/dist/style.css');
                </style>

                <script type="module">
                    import { Datamart, DatamartDesignChecker } from '<?= getJSpath('vue/components/dist/lib.es.js') ?>'

                    Datamart('#datamart-app')
                    DatamartDesignChecker('#datamart-design-checker')

                </script>
            <?php else : ?>
                <h3>
                    <i class="fas fa-info-circle"></i>
                    <span>This is not a Datamart Project!</span>
                </h3>
            <?php endif; ?>

            <?php
        } catch (\Throwable $th) {
            ?>
                <h3>
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= $th->getMessage() ?></span>
                    <br>
                    <span>Please contact your administrator.</span>
                </h3>
            <?php
        } finally {
            // ********** HTML **********
            $contents = ob_get_contents();
            ob_end_clean();

            include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
            print $contents;
            include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
        }
    }

    public function searchMrns()
    {
        $query = @$_GET['query'];
        $start = @$_GET['start'] ?: 0;
        $limit = @$_GET['limit'] ?: 0;
        $result = $this->model->searchMrns($this->project_id, $query, $start, $limit);
        $this->printJSON($result);

    }



    public function checkDesign()
    {
        try {
            $this->checkDataMartPermission();
            $designChecker = new DesignChecker($this->project_id, $this->userid);
            // extract just the privileges: no project ID, no username
            $commands = $designChecker->check();
            $designChecker->backupCommands();
            $settings = $designChecker->getSettings();
            $response = [
                'commands' => $commands,
                // 'user' => User::getUserInfoByUiid($this->userid),
                'settings' => $settings,
            ];
            HttpClient::printJSON($response);
        } catch (\Throwable $th) {
            $response = [
                'message' => $th->getMessage(),
                'code' => $code = $th->getCode(),
            ];
            HttpClient::printJSON($response, $code);
        }
    }

    public function fixDesign()
    {
        $designChecker = new DesignChecker($this->project_id, $this->userid);
        $runFixCheck = function() use($designChecker) {
            // prepare the response in case of errors
            $response = [
                'error' => true,
                'message' => "error",
            ];
            $settings = $designChecker->getSettings();
            $privileges = $settings['privileges'] ?? [];
            $project_metadata = $settings['project_metadata'] ?? [];
            $projectCanBeModified = $project_metadata['can_be_modified'] ?? false;
            $userHasDesignPrivileges = $privileges['design'] ?? false;
            
            if(!$projectCanBeModified) {
                $response['message'] = "The project can only be modified in draft mode";
                HttpClient::printJSON($response, 401);
            }

            if(!$userHasDesignPrivileges) {
                $response['message'] = "The project can only be modified by a user with 'design' privileges";
                HttpClient::printJSON($response, 403);
            }
            return true;
        };
        
        $runFixCheck(); // verify if the conditions to fix the project are met

        $designChecker = new DesignChecker($this->project_id, $this->userid);
        try {
            $this->checkDataMartPermission();
            $executed = $designChecker->executeCachedCommands();
            $response = [
                'success' => true,
                'message' => 'all commands have been executed successfully',
            ];
            HttpClient::printJSON($response);
        } catch (\Throwable $th) {
            $response = [
                'success' => false,
                'message' => $th->getMessage(),
            ];
            HttpClient::printJSON($response, $th->getCode());
        }
    }
    
    public function executeFixcommand()
    {
        $designChecker = new DesignChecker($this->project_id, $this->userid);
        $request = json_decode(file_get_contents("php://input"), true);
        $command = @$request['command'];
        $options = @$request['options'] ?? [];
        $result = $designChecker->executeCachedCommands();
        $response = [
            'message' => 'completed',
            'success' => true,
        ];
        HttpClient::printJSON($response);
    }


}