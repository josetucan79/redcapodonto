<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart;

use Renderer;
use Exception;
use Vanderbilt\REDCap\Classes\Queue\Queue;
use Vanderbilt\REDCap\Classes\Fhir\FhirUser;
use Vanderbilt\REDCap\Classes\Queue\Message;
use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\SerializableException;
use Vanderbilt\REDCap\Classes\Fhir\Facades\FhirClientFacade;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroup;
use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStatsCollector;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMartRecordAdapter;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirMetadataSource;
use Vanderbilt\REDCap\Classes\Fhir\MappingHelper\FhirMappingHelper;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataCdmDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\GroupDecorators\DataMartGroupDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataEmailDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataVandyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataCustomDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataAdverseEventDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataCapabilitiesDecorator;

class DataMart
{
    /**
     * datetime in FHIR compatible format
     * https://www.hl7.org/fhir/datatypes.html#dateTime
     */
    const FHIR_DATETIME_FORMAT = "Y-m-d\TH:i:s\Z";

    /**
     * type of request for To-Do List
     *
     * @var string
     */
    const TODO_REQUEST_TYPE = "Clinical Data Mart revision";

    /**
     * list of Data Mart related project settings
     */
    const PROJECT_SETTINGS = [
        'datamart_enabled',
        'datamart_allow_repeat_revision',
        'datamart_allow_create_revision',
        'datamart_cron_enabled',
        'datamart_cron_end_date',
    ];

    const DATE_RANGE_CATEGORIES = [
        ['name' => FhirCategory::ALLERGY_INTOLERANCE, 'defaultEnabled' => false],
        ['name' => FhirCategory::APPOINTMENT_APPOINTMENTS, 'defaultEnabled' => false],
        ['name' => FhirCategory::APPOINTMENT_SCHEDULED_SURGERIES, 'defaultEnabled' => false],
        ['name' => FhirCategory::CONDITION, 'defaultEnabled' => false],
        ['name' => FhirCategory::CONDITION_DENTAL_FINDING, 'defaultEnabled' => false],
        ['name' => FhirCategory::CONDITION_GENOMICS, 'defaultEnabled' => false],
        ['name' => FhirCategory::CONDITION_INFECTION, 'defaultEnabled' => false],
        ['name' => FhirCategory::CONDITION_MEDICAL_HISTORY, 'defaultEnabled' => false],
        ['name' => FhirCategory::CONDITION_PROBLEMS, 'defaultEnabled' => false],
        ['name' => FhirCategory::CONDITION_REASON_FOR_VISIT, 'defaultEnabled' => false],
        ['name' => FhirCategory::CORE_CHARACTERISTICS, 'defaultEnabled' => true],
        ['name' => FhirCategory::DIAGNOSIS, 'defaultEnabled' => false],
        ['name' => FhirCategory::DOCUMENT_REFERENCE_CLINICAL_NOTES, 'defaultEnabled' => false],
        ['name' => FhirCategory::ENCOUNTER, 'defaultEnabled' => true],
        ['name' => FhirCategory::IMMUNIZATION, 'defaultEnabled' => true],
        ['name' => FhirCategory::LABORATORY, 'defaultEnabled' => true],
        ['name' => FhirCategory::MEDICATIONS, 'defaultEnabled' => false],
        ['name' => FhirCategory::PROCEDURE, 'defaultEnabled' => false],
        ['name' => FhirCategory::SMART_DATA, 'defaultEnabled' => false],
        ['name' => FhirCategory::SOCIAL_HISTORY, 'defaultEnabled' => false],
        ['name' => FhirCategory::VITAL_SIGNS, 'defaultEnabled' => true],
    ];

    /**
     * userid
     *
     * @var int
     */
    private $userid;

    /**
     *
     * @param int $userid
     * @param Project $project
     */
    public function __construct($userid)
    {
        $this->userid = $userid;
    }

    public function getUserId()
    {
        return $this->userid;
    }

    /**
     * @param Project $project
     * @param int $request_id
     * @return array
     */
    public function getSettings($project_id, $request_id, $lang=[])
    {
        /**
         * when standard users cannot create projects a revision
         * has no project_id, but only a request ID.
         * Add the requested revision to the list
         */
        $addRevisionFromRequestID = function(&$settings, $request_id) {
            if($request_id)
            {
                $revision = $this->getRevisionFromRequest($request_id);
                if($revision instanceof DataMartRevision) $settings['revisions'][] = $revision;
            }
        };

        $getAppSettings = function() use($project_id, $lang) {
            $configVals = \System::getConfigVals();
            $fhir_standalone_authentication_flow = $configVals['fhir_standalone_authentication_flow'] ?? '';
            $fhir_source_system_custom_name = $configVals['fhir_source_system_custom_name'] ?? '';
            return [
                'project_id' => $project_id,
                'lang' => $lang,
                'standalone_authentication_flow' => $fhir_standalone_authentication_flow,
                'standalone_launch_enabled' => true,
                'standalone_launch_url' => FhirLauncher::getStandaloneLaunchUrl(),
                'ehr_system_name' => strip_tags($fhir_source_system_custom_name),
                'mapping_helper_url' => FhirMappingHelper::getLink($project_id),
                'date_range_categories' => self::DATE_RANGE_CATEGORIES,
            ];
        };
        
        $settings = [
            'fhir_metadata' => $this->getFhirMetadataSource($project_id)->getList(),
            'app_settings' => $getAppSettings(),
            'user' => $this->getUser($project_id),
            'revisions' => $this->getRevisions($project_id),
        ];

        $addRevisionFromRequestID($settings, $request_id);
        
        return $settings;

    }

    
    
    /**
     * get revisions and return them as array
     * 
     * @param int $project_id
     * @return DataMartRevision[] 
     */
    public function getRevisions($project_id)
    {
        return DataMartRevision::all($project_id);
    }

    /**
     * get revision using request_id
     * 
     * @param int $request_id
     * @return DataMartRevision
     */
    public function getRevisionFromRequest($request_id)
    {
        return DataMartRevision::getRevisionFromRequest($request_id);
    }

    /**
     * get a revision by id
     *
     * @return DataMartRevision
     */
    public function getRevision($id)
    {
        return DataMartRevision::get($id);
    }

    /**
     * get info for the instance project
     *
     * @param int $project_id
     * @return object
     */
    public function getProjectInfo($project_id)
    {
        $db = new \RedCapDB();
        return $db->getProject($project_id);
    }

    /**
     * return info and privileges of a user
     *
     * @return array
     */
    public function getUser($project_id=null)
    {        
        return new FhirUser($this->userid, $project_id);
    }

    /**
     * get user information
     *
     * @param int|string $id can be a username or the ui_id
     * @return object
     */
    private function getUserInfo($id)
    {
        $userInfo = intval($id) ? \User::getUserInfoByUiid($id) : \User::getUserInfo($id);
        return (object)$userInfo;
    }

    /**
     * get REDCap configuration values
     *
     * @return void
     */
    private function getConfigVals()
    {
        $config_vals = \System::getConfigVals();
    }

    /**
     * delete a revision
     *
     * @param int $revision_id
     * @return void
     */
    public function deleteRevision($revision_id)
    {        
        $userInfo = $this->getUserInfo($this->userid);
        $superUser = $userInfo->super_user==='1';
        if($superUser) {
            $revision = DataMartRevision::get($revision_id);
            if(!$revision) return false; // no revision found
            $revision->delete();
            // archive any pending revision request
            $this->archivePendingRequest($revision);
            // return $revision;
            return true;
        }else {
            return false;
        }
    }

    /**
     * Undocumented function
     *
     * @param integer $revision_id
     * @param array $fields associative array with keys to be exported
     * @param string $format 
     * @param string $csv_delimiter
     * @return void
     */
    public function exportRevision($revision_id, $fields=[], $format='csv', $csv_delimiter=",")
    {
        $revision = DataMartRevision::get($revision_id);
        $data = $revision->getData();
        $dataString = [];
        foreach($data as $key => $value)
        {
            // check if the key must be exported
            if(!in_array($key, $fields)) {
                unset($data[$key]);
                continue;
            }

            $toString = is_array($value) ? implode(' ', $value) : strval($value);
            $dataString[$key] = $toString;
        }
        $filename = sprintf('datamart_revision_%u', $revision_id);
        if($format=='csv')
        {
            \FileManager::exportCSV(array($dataString), $filename, $extract_headers=true,$delimiter=$csv_delimiter,$enclosure='"' );
        }else if($format=='json')
        {
            \FileManager::exportJSON($data, $filename);
        }
    }

    /**
     * parse a file for a revision.
     * 
     * automatically detect file type (json or CSV)
     * if file type is CSV it can automatically detect the delimiter
     *
     * @param array $file
     * @return array
     */
    public function importRevision($file)
    {
        $splitString = function($string) {
            // Split string on comma or whitespace(s)
            $pattern = '/[\s,]+/';
            $parts = preg_split($pattern, $string);
        
            return $parts;
        };
        // $file_type = $file['type'];
        $file_name = $file['name'];
        $file_path = $file['tmp_name'];
        $file_info = pathinfo($file_name);
        $file_extension = $file_info['extension'];
        // check file extension
        switch ($file_extension) {
            case 'json':
                $file_content = file_get_contents($file_path);
                $data = json_decode($file_content, true);
                break;
            case 'csv':
                $rows = \FileManager::readCSV($file_path, $length=0, $delimiter='auto');
                if(count($rows)>1)
                {
                    // convert the CSV rows to an associative array using the first line for the keys
                    $data = array_combine($rows[0], $rows[1]);
                    // convert mrns and fields from string to array
                    foreach ($data as $key => $value) {
                        if(in_array($key, array('fields', 'mrns', 'date_range_categories'))) {
                            $split = explode(',', $value);
                            $data[$key] = array_filter($split, function($item) {
                                return trim($item) !== '';
                            });
                        }
                    }
                }
                break;
            default:
                $data = array();
                break;
        }
        return $data;
    }

    /**
     * add a revision to the current data mart project
     * 
     * @param array $settings
     * @return DataMartRevision
     */
    public function addRevision($settings)
    {
        $project_id = $settings['project_id'];
        $user = new FhirUser($this->userid, $project_id);
        // check if the user can create a revision
        if(!$user->can_use_datamart || !$user->can_create_revision) return false;

        $settings['user_id'] = $user->id;

        $revision = DataMartRevision::create($settings);
        // automtically approve revision for super users
        if($user->super_user) $revision = $this->approveRevision($revision);

        /**
         * send a revision request if the revision
         * - is assigned to a project 
         * - is not approved
         * 
         * you do no want to create/send a revision request if the project ID is not available
         * because the admin has already been sent a project creation request
         */
        if($revision->project_id && !$revision->approved)
        {
            $response = $this->createRevisionRequest($revision);
            return $response['revision'];
        }
        return $revision;
    }

    /**
     * helper function to get the URL used to access the DataMart app
     */
    private function getDataMartUrl($query_params) {
        $URL = APP_PATH_WEBROOT_FULL . "redcap_v".REDCAP_VERSION."/index.php?route=DataMartController:index";
        $action_url = $URL . '&' . http_build_query($query_params);
        return $action_url;
    }

    /**
     * send an email to the admin when a new revision request is submitted
     *
     * @param DataMartRevision $revision
     * @return bool
     */
    private function sendRevisionRequestEmail($revision)
    {
        global $lang;
        // project information
        $projectInfo = $this->getProjectInfo($revision->project_id);
        // user information
        $user = $revision->getCreator();
        $admin_email = $projectInfo->project_contact_email;
        $user_email = $user->user_email;
        $user_fullname = sprintf("%s %s", $user->user_firstname, $user->user_lastname);
        
        // get url for Revision creation
        $action_url = $this->getDataMartUrl(array(
            'pid' => $revision->project_id,
            'request_id' => $revision->request_id
        ));
        /**
         * send an email
         */
        $email = new \Message();
        $emailSubject = "[REDCap] Request to Approve New Data Mart Revision";
        $email->setFrom($user_email);
        $email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
        $email->setTo($admin_email);

        ob_start();
        ?>
        <html>
            <head><title>$emailSubject</title></head>
            <body style='font-family:arial,helvetica;'>
                <?php echo $lang['global_21'] ?>
                <br><br>
                <?php echo $lang['email_admin_03'] ?> <b><?php echo html_entity_decode($user_fullname, ENT_QUOTES) ?></b>
                (<a href="mailto:<?php echo $user_email ?>"><?php echo $user_email ?></a>)
                <?php echo $lang['email_admin_21'] ?>:
                <b> ID <?php echo html_entity_decode($revision->id, ENT_QUOTES) ?></b><?php echo $lang['period'] ?>
                <br><br>
                <?php echo $lang['email_admin_05'] ?><br>
                <a href="<?php echo $action_url ?>"><?php echo $lang['email_admin_22'] ?></a>
            </body>
        </html>
        <?php
        $contents = ob_get_contents();
        ob_end_clean();

        // Finalize email
        $email->setBody($contents);
        $email->setSubject($emailSubject);
        
        return $email->send();
    }

    /**
     * Send a confirmation email to the user that requested a revision approval
     *
     * @param DataMartRevision $revision
     * @return void
     */
    private function sendRevisionApprovedEmail($revision)
    {
        global $lang;
        $projectInfo = $this->getProjectInfo($revision->project_id);
        $project_title = $projectInfo->app_title;
        $user = $revision->getCreator();

        $admin_email = $projectInfo->project_contact_email;
        $user_email = $user->user_email;
        $action_url = $this->getDataMartUrl(array('pid' => $revision->project_id));

        /**
         * send an email
         */
        $email = new \Message();
        $emailSubject = "[REDCap] Data Mart Revision approved";
        $email->setFrom($admin_email);
        $email->setFromName($projectInfo->project_contact_name);
        $email->setTo($user_email);

        ob_start();
        ?>
        <html>
            <head><title><?php echo $emailSubject ?></title></head>
            <body style='font-family:arial,helvetica;'>
                <?php echo $lang['global_21'] ?>
                <br><br>
                The Data Mart revision that you requested for your project has been approved:
                <b><?php echo html_entity_decode($project_title, ENT_QUOTES) ?></b>.
                <br><br>
                <a href="<?php echo $action_url ?>" target="_blank">View your new Data Mart Revision</a>
            </body>
        </html>
        <?php
        $contents = ob_get_contents();
        ob_end_clean();
        // Finalize email
        $email->setBody($contents);
        $email->setSubject($emailSubject);
        
        return $email->send();
    }

    /**
     * Send a confirmation email to the user that requested a revision approval
     *
     * @param DataMartRevision $revision
     * @return void
     */
    private function sendRevisionRejectedEmail($revision)
    {
        global $lang;
        $projectInfo = $this->getProjectInfo($revision->project_id);
        $project_title = $projectInfo->app_title;
        $user = $revision->getCreator();

        $admin_email = $projectInfo->project_contact_email;
        $user_email = $user->user_email;
        $action_url = $this->getDataMartUrl(array('pid' => $revision->project_id));

        /**
         * send an email
         */
        $email = new \Message();
        $emailSubject = "[REDCap] Clinical Data Mart Revision rejected";
        $email->setFrom($admin_email);
        $email->setFromName($projectInfo->project_contact_name);
        $email->setTo($user_email);

        ob_start();
        ?>
        <html>
            <head><title>$emailSubject</title></head>
            <body style='font-family:arial,helvetica;'>
                <?php echo $lang['global_21'] ?>
                <br><br>
                The Data Mart revision that you requested for your project has been rejected:
                <b><?php echo html_entity_decode($project_title, ENT_QUOTES) ?></b>.
                <p>For more info contact your administrator.</p>
            </body>
        </html>
        <?php
        $contents = ob_get_contents();
        ob_end_clean();
        // Finalize email
        $email->setBody($contents);
        $email->setSubject($emailSubject);
        
        return $email->send();
    }


    /**
     * creates revision request that must be approved by an admin
     * - create the request (if the revision has not a request_id aready)
     * - send an email
     *
     * @param DataMartRevision $revision
     * @return Array
     */
    public function createRevisionRequest($revision)
    {
        global $send_emails_admin_tasks;

        $projectInfo = $this->getProjectInfo($revision->project_id);

        /**
         * check if the revision has a request_id.
         * only create a ToDoList request if the
         * revision has not been already assigne to one
         */
        if (!$revision->request_id) {
            $actionURL = $this->getDataMartUrl(array('pid' => $revision->project_id));
            $request_id = \ToDoList::insertAction(
                $revision->user_id,
                $projectInfo->project_contact_email,
                self::TODO_REQUEST_TYPE,
                $actionURL, // request_id is automatically appended to end of action URL after insert to keep as a reference during admin processing
                $revision->project_id
            );
            // update the revision with the request ID
            $revision->setRequestId($request_id);
            $revision->save(); // persist changes to the database
        }
        
        /**
         * send an email to the admin
         */
        \Logging::logEvent("","redcap_data","MANAGE",$revision->project_id,"revision_id = $revision->id","Send request to approve a Clinical Data Mart Revision");
        $response = array();
        if ($send_emails_admin_tasks) $response['email_sent'] = $this->sendRevisionRequestEmail($revision);
        $response['revision'] = $revision;
        $response['request_id'] = $request_id;

        return $response;
    }

    /**
     * check if a revision is valid.
     * A valid revision is:
     * - active
     * - approved
     * - contains the specified mrn
     * - can be run by the specified user
     *
     * @param DataMartRevision $revision
     * @param FhirUser $fhirUser
     * @param string $mrn
     * @throws \Exception if a prerequisite in not met
     * @return DataMartRevision
     */
    private function checkRevision($revision, $fhirUser, $mrn)
    {
        $project_id = $revision->project_id;
        $project_has_mrn = $revision->projectContainsMrn($mrn);
        // check if MRN exists in the project
        if(!$project_has_mrn) {
            $message = sprintf("The MRN '%s' does not exist in the project ID %u", $mrn, $project_id);
            throw new \Exception($message, 400);
        }
        
        $active_revision = DataMartRevision::getActive($project_id);
        if(!$active_revision) throw new \Exception("There are no active revisions for this project", 400);
        // check if the revision we are trying to run is the active one
        if($revision->id !== $active_revision->id) throw new \Exception("This is not the active revision for this project", 400);
        // cannot run a revision if it is not approved
        if(!$revision->approved) throw new \Exception("This revision has not been approved", 400);

        // check if the user can run repeat revision
        
        if(!$fhirUser->can_use_datamart ) throw new \Exception("The user is not allowed to use Data Mart in this project", 400);
        if(!$fhirUser->can_repeat_revision)
        {
            if(!$revision->canFetchMrn($fhirUser, $mrn)) {
                $message = sprintf("This revision has already been run by the user %s", $fhirUser->id);
                throw new \Exception($message, 400);
            }
        }
        return $revision;
    }
    
    /**
     * run a revision and fetches data from remote
     *
     * @param int $revision_id
     * @param string $mrn medical record number
     * @return void
     */
    public function runRevision($revision_id, $mrn=null)
    {
        $checkSaveErrors = function($saveResponse) {
            $saveResponse = $saveResponse ?: 'Errors saving data';
            if(!is_array($saveResponse)) throw new \Exception($saveResponse, 400);
            $errors = $saveResponse['errors'] ?? [];
            if(!empty($errors)) {
                $message = implode("\n", $errors);
                throw new \Exception($message, 400);
            }
        };
        $errors = [];
        // add and format errors
        $addError = function($error) use(&$errors, &$addError) {
            if(is_array($error)) return array_map($addError, $error);

            if($error instanceof \Exception) {
                $code = $error->getCode();
                $message = $error->getMessage();
                $previous = $error->getPrevious();
                $message .= " (code {$code})";
                $errors[] = new SerializableException($message, $code, $previous);
            }
        };

        try {
            // check if the revision can be run
            $revision = DataMartRevision::get($revision_id);
            if(!($revision instanceof DataMartRevision)) throw new \Exception(sprintf("No revision found with the provided ID (%s)", $revision_id), 400);
            $fhirUser = new FhirUser($this->userid, $revision->project_id);

            // skip the fetching process if no MRN is provided
            if(!$mrn) return;

            // Why should we add this here? It's complicated.
            if(!defined('CREATE_PROJECT_ODM')) define("CREATE_PROJECT_ODM", true);
            $this->checkRevision($revision, $fhirUser, $mrn);
            $project_id = $revision->project_id;
            $mapping_list = $revision->getNormalizedMapping($mrn);
            
            $adapter = DataMartRecordAdapter::fromMRNAndRevision($mrn, $revision);

            $fhirSystem = FhirSystem::fromProjectId($project_id);
            $fhirClient = FhirClientFacade::getInstance($fhirSystem, $project_id, $this->userid);
            $fhirMetadataSource = $this->getFhirMetadataSource($project_id);

            // listen for notifications from the FhirClient
            $fhirClient->attach($adapter, FhirClient::NOTIFICATION_ENTRIES_RECEIVED);
            
            // start the fetching process
            $mappingGroups = FhirMappingGroup::makeGroups($fhirMetadataSource, $mapping_list);
            foreach ($mappingGroups as $mappingGroup) {
                $mappingGroup = new DataMartGroupDecorator($mappingGroup, $revision);
                $fhirClient->fetchData($mrn, $mappingGroup);
            }
            // get the record from the adapter; data was added in the fetchData process
            $record = $adapter->getRecord();
            
            $saveResponse = \Records::saveData([
                'project_id'=>$project_id,
                'dataFormat'=>'array',
                'data'=>$record,
                'skipFileUploadFields'=>false
            ]);
            $checkSaveErrors($saveResponse); // throws exception
            $stats = $adapter->getStats();
            // add any adapter errors
            $adapterErrors = $adapter->getErrors();
            $addError($adapterErrors);
            
            if($record_id = key($record)) {
                // only save stats if something was saved in REDCap
                // stats = [FhirCategory => int]
                $fhirStatsCollector = new FhirStatsCollector($project_id, FhirStatsCollector::REDCAP_TOOL_TYPE_CDM);
                foreach ($stats as $key => $value) {
                    $fhirStatsCollector->addEntry($record_id, $key, $value);
                }
                $fhirStatsCollector->logEntries();
            }

            $revision->setExecutionTime();
            $revision->save(); // persist changes to the database
            \Logging::logEvent('', "redcap_ehr_datamart_revisions", "MANAGE", $revision->project_id, sprintf("revision_id = %u", $revision->id), 'Fetch data for Clinical Data Mart');

        } catch (\Exception $e) {
            $dbError = db_error();
            if(!empty($dbError)) {
                $dbException = new Exception($dbError);
                $addError($dbException);
            }
            $addError($e);
        }finally {
            $fhirErrors = isset($fhirClient) ? $fhirClient->getErrors() : [];
            $addError($fhirErrors);
            
            $next_mrn = ($revision instanceof DataMartRevision) 
                ? $revision->getNextMrnWithValidDateRange($fhirUser, $mrn) 
                : null;
            
            // compose the response object
            $response = array(
                'errors' => $errors,
                'has_errors' => count($errors)>0,
                'metadata' => [
                    'stats' => isset($stats) ? $stats : [], // use empty array if no stats are available
                    'next_mrn' => $next_mrn,
                ]
            );
            return $response;
        }
    }

    private function canRevisionRunInBackground($revisionId) {
        $revision = DataMartRevision::get($revisionId);
        if( ($revision==false) || !($revision instanceof DataMartRevision) ) throw new \Exception(sprintf('No revision found with the ID \'%u\'.', $revisionId), 404);
        $project_id = $revision->project_id;
        $messageKey = sprintf('DataMart-%u (%u)', $project_id, $revisionId);
        $queue = new Queue();
        $existingMessages =  $queue->getMessagesByKey($messageKey, [Message::STATUS_PROCESSING, Message::STATUS_WAITING]);
        if(!empty($existingMessages)) throw new \Exception("No background fetch was scheduled because there is a previous process waiting to be processed.", 401);
        return $revision;
    }

    function runBackgroundProcess($revisionId, $mrn_list, $send_feedback=false) {
        $revision = $this->canRevisionRunInBackground($revisionId);
        $message = 'The request has been queued and will be run in a background process.';
        if($send_feedback) $message .= PHP_EOL.'You will receive a message when the process is completed.';
        $bgRunner = new DataMartBackgroundRunner($this);
        $bgRunner->schedule($revision, $mrn_list, $send_feedback);
        return $message;
    }

    /**
     * get a FhirMetadataSource with all decorators applied
     *
     * @param int $project_id
     * @return FhirMetadataSource
     * @return void
     */
    public function getFhirMetadataSource($project_id)
    {
        $fhirSystem = FhirSystem::fromProjectId($project_id);
		$fhirVersionManager = FhirVersionManager::getInstance($fhirSystem);
        $metadataSource = $fhirVersionManager->getFhirMetadataSource();
        $metadataSource = new FhirMetadataVandyDecorator($metadataSource);
        $metadataSource = new FhirMetadataCapabilitiesDecorator($metadataSource, $fhirVersionManager);
        $metadataSource = new FhirMetadataEmailDecorator($metadataSource);
        $metadataSource = new FhirMetadataAdverseEventDecorator($metadataSource);
        $metadataSource = new FhirMetadataCdmDecorator($metadataSource);
        $metadataSource = new FhirMetadataCustomDecorator($metadataSource);
        return $metadataSource;
    }

    /**
     * set a ToDoList request as completed
     * and email the user
     *
     * @param DataMartRevision $revision
     * @return void
     */
    private function completePendingRequest($revision)
    {
        if($revision->request_id && $revision->project_id)
        {
            \ToDoList::updateTodoStatusNewProject($revision->request_id, $revision->project_id);
            $this->sendRevisionApprovedEmail($revision);
        }
    }

    /**
     * archive a ToDoList request
     * and email the user
     *
     * @param DataMartRevision $revision
     * @return void
     */
    private function archivePendingRequest($revision)
    {
        if($revision->request_id && $revision->project_id)
        {
            \ToDoList::updateTodoStatus($revision->project_id, self::TODO_REQUEST_TYPE, $status='archived');
            $this->sendRevisionRejectedEmail($revision);
        }
    }

    /**
     * approve a revision and archive the revision request
     *
     * @param DataMartRevision|int $revision
     * @return DataMartRevision|false
     */
    public function approveRevision($revision)
    {        
        $userInfo = $this->getUserInfo($this->userid);
        $superUser = $userInfo->super_user==='1';
        if($superUser) {
            if(!is_a($revision, DataMartRevision::class))
            {
                $revision = DataMartRevision::get($revision);
                if(!$revision) return false; // exit if the revision is not found
            }
            $revision->approve();
            // archive any pending revision request
            $this->completePendingRequest($revision);
            $revision->save(); // persist changes to the database
            return $revision;
        }else {
            return false;
        }
    }

    /**
     * check if a project is enabled for datamart
     *
     * @param int $project
     * @return boolean
     */
    public static function isEnabled($project_id)
    {
        $Proj = new \Project($project_id);
        $datamart_enabled = $Proj->project['datamart_enabled'] ?? 0;
        return ($datamart_enabled == '1');
    }


    /**
     * check if REDCap has at least 1 active Data Mart project
     *
     * @return boolean
     */
    public static function isEnabledInSystem()
    {
        $enabled = false;
        $query_string = "SELECT value AS enabled FROM redcap_config WHERE field_name='fhir_data_mart_create_project'";
        $result = db_query($query_string);
        if(!$result) $enabled = false;
        if($row = db_fetch_assoc($result)) {
            $enabled = boolval($row['enabled']);
        }
        return $enabled;
    }

    /**
     * check if an expiration date for the CRON job is set
     * and if the CRON job must be disabled
     * based on the specified date in the project settings.
     *
     * @param integer $project_id
     * @return \DateTime|false
     */
    private function isCronJobExpired($project_id)
    {
        $project = new \Project($project_id);
        $project_data = $project->project;
        $datamart_cron_end_date_setting = $project_data['datamart_cron_end_date'] ?? '';
        $format = "Y-m-d H:i:s";
        $expiration_date = \DateTime::createFromFormat($format, $datamart_cron_end_date_setting);
        if(empty($expiration_date)) return false;
        $now = new \DateTime();
        if($now < $expiration_date) return false;
        // update CRON settings for the project and skip this revision
        $this->disableCronJob($project_id);
        $this->sendCronJobExpiredEmail($project_id, $expiration_date);
        return $expiration_date;
    }

    /**
     * update the project settings to disable the CRON job
     *
     * @param integer $project_id
     * @return void
     */
    public function disableCronJob($project_id)
    {
        $this->setProjectSettings($project_id, 'datamart_cron_enabled', 0);
        $this->setProjectSettings($project_id, 'datamart_cron_end_date', null);
    }

    /**
     * Send a confirmation email to the user that requested a revision approval
     *
     * @param DataMartRevision $revision
     * @param \DateTime $expiration_date
     * @return void
     */
    private function sendCronJobExpiredEmail($project_id, $expiration_date=null)
    {
        global $lang;
        // helper function to get project URL
        $getProjectURL = function($project_id, $query_params=[]) {
            $URL = APP_PATH_WEBROOT_FULL . "redcap_v".REDCAP_VERSION."/index.php?pid={$project_id}";
            if(!empty($query_params)) {
                $URL .= '&' . http_build_query($query_params);
            }
            return $URL;
        };

        $projectInfo = $this->getProjectInfo($project_id);
        $project_title = $projectInfo->app_title;
        $project_url = $getProjectURL($project_id);
        if(!($expiration_date instanceof \DateTime)) $expiration_date = new \DateTime();
        $formatted_date = $expiration_date->format('m-d-Y');

        $admin_email = $projectInfo->project_contact_email;

        /**
         * send an email
         */
        $email = new \Message();
        $emailSubject = "[REDCap] Data Mart CRON job expired";
        $email->setFrom($admin_email);
        $email->setFromName($projectInfo->project_contact_name);
        $email->setTo($admin_email);

        ob_start();
        ?>
        <html>
            <head><title>{{$emailSubject}}</title></head>
            <body style='font-family:arial,helvetica;'>
                <p><?php echo $lang['global_21'] ?></p>
                <p>
                    The CRON job for Data Mart project ID {{$project_id}}
                    (<a href="{{$project_url}}">{{$project_title}}</a>) has been
                    disabled since the expiration date had been set on {{$formatted_date}}.
                </p>
                <p>The CRON job can be re-enbled in the 'Project Setup' page.</p>
            </body>
        </html>
        <?php
        $contents = ob_get_contents();
        ob_end_clean();
        $data = compact('project_id', 'project_title', 'project_url', 'emailSubject', 'formatted_date');
        $data['project_title_decoded'] = html_entity_decode($project_title, ENT_QUOTES);

        $renderer = Renderer::getBlade();
        $html = $renderer->runString($contents, $data);
        // Finalize email
        $email->setBody($html);
        $email->setSubject($emailSubject);
        
        return $email->send();
    }

    /**
     * set Data Mart related settings for a project
     *
     * @param integer $project_id
     * @param string $name
     * @param mixed $value
     * @return boolean
     */
    public function setProjectSettings($project_id, $name, $value)
    {
        if(!in_array($name, self::PROJECT_SETTINGS)) {
            throw new \Exception("Error: the property '{$name}' is not a valid Data Mart setting.", 1);
        }
        $query_string = "UPDATE redcap_projects SET $name = ? WHERE project_id= ?";
        $result = db_query($query_string, [$value, $project_id]);
        return $result;
    }
    
    /**
     * get the most recent, approved, non-deleted revision
     * which belongs to a CRON enabled project
     *
     * @return DataMartRevision[]
     */
    public function getCronEnabledRevisions()
    {
        $query_string = 
            "SELECT r.id FROM redcap_ehr_datamart_revisions AS r
            # get the most recent, approved, non-deleted revision
            INNER JOIN (
                SELECT project_id,  MAX(id) AS id FROM redcap_ehr_datamart_revisions
                WHERE is_deleted=0 GROUP BY project_id
            ) AS latest_ids ON latest_ids.id = r.id
            # only get CRON-enabled projects, not archived or in analysis mode
            WHERE r.project_id IN (
                SELECT project_id FROM redcap_projects
                WHERE datamart_enabled=1
                AND datamart_cron_enabled=1
                AND date_deleted IS NULL
                AND status!=2
                AND completed_time IS NULL
            )
            # only get approved revisions
            AND r.approved=1";
        $result = db_query($query_string);
        $revisions = array();

        // check a revision and disable the cron if expiration date is reached
        $checkRevision = function($id) {
            $revision = DataMartRevision::get($id);
            if(!$revision instanceof DataMartRevision) return false;
            $project_id = $revision->project_id;
            if($expiration_date = $this->isCronJobExpired($project_id)) {
                return false;
            }
            return $revision;
        };
        
        while($row = db_fetch_object($result))
        {
            $id = $row->id ?: false;
            if($id && $revision = $checkRevision($id)) {
                $revisions[] = $revision;
            }
        }
        return $revisions;
    }

    /**
     * get a path to the template based on the system FHIR version
     *
     * @return string patht to the file
     */
    public static function getProjectTemplatePath()
    {
        // use the first available FHIR system (default)
        $defaultFhirSystem = FhirSystem::getDefault();
        if(!$defaultFhirSystem) return;
        $fhirCode = FhirVersionManager::getInstance($defaultFhirSystem)->getFhirCode();
        switch ($fhirCode) {
            case FhirVersionManager::FHIR_DSTU2:
                $path = APP_PATH_DOCROOT."Resources/misc/redcap_fhir_data_mart.xml";
                break;
            case FhirVersionManager::FHIR_R4:
                $path = APP_PATH_DOCROOT."Resources/misc/redcap_fhir_data_mart_R4.xml";
                break;
            default:
                $path = null;
                break;
        }
        return $path;
    }

    /**
     * search MRNs that can be fetched 
     *
     * @param [type] $project_id
     * @param [type] $query
     * @param integer $offset
     * @param integer $count
     * @return array [list, total] list of found items (limited by OFFSET/COUNT) and total matches
     */
    public function searchMrns($project_id, $query, $offset=0, $count=10)
    {
        $getTotal = function() {
            $result = db_query('SELECT FOUND_ROWS() AS `total`');
            $row = db_fetch_assoc($result);
            return intval($row['total'] ?? 0);
        };
        $now = new \DateTime();
        $formatted_now = $now->format('Y-m-d H:i');
        $us = chr(31); //unit separator
        $query_string = "SELECT
            SQL_CALC_FOUND_ROWS
            GROUP_CONCAT(CASE WHEN d.field_name = 'mrn' THEN d.value ELSE NULL END ORDER BY d.value ASC SEPARATOR '{$us}') AS `mrn`,
            GROUP_CONCAT(CASE WHEN d.field_name = 'fetch_date_start' THEN d.value ELSE NULL END ORDER BY d.value ASC SEPARATOR '{$us}') AS `fetch_date_start`,
            GROUP_CONCAT(CASE WHEN d.field_name = 'fetch_date_end' THEN d.value ELSE NULL END ORDER BY d.value ASC SEPARATOR '{$us}') AS `fetch_date_end`
            FROM (SELECT DISTINCT `record`, `field_name`, `value` 
                FROM ".\Records::getDataTable($project_id)."
                WHERE project_id = ? AND 
                field_name IN ('mrn', 'fetch_date_start', 'fetch_date_end')
            ) AS d
            GROUP BY `record`
            HAVING (
                (`fetch_date_start` IS NULL OR `fetch_date_start`< ?)
                AND (`fetch_date_end` IS NULL OR `fetch_date_end`> ?)
                AND `mrn` REGEXP ?
            )";
        $queryParams = [$project_id, $formatted_now, $formatted_now, $query];
        if($offset>=0) {
            $query_string .= ' LIMIT ?, ?';
            array_push($queryParams, $offset, $count);
        }
        $result = db_query($query_string, $queryParams);
        $total = $getTotal();
        $rows = [];
        while($row=db_fetch_assoc($result)) $rows[]['mrn'] = $row['mrn'] ?? '';
        return [
            'list' => $rows,
            'total' => $total
        ];
    }
}

