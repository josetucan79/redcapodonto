<?php

namespace Vanderbilt\REDCap\Classes\MyCap;
use ExternalModules\ExternalModules;
use MultiLanguageManagement\MultiLanguage;
use Project;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks\Promis;
use RCView;
use Exception;
use Vanderbilt\REDCap\Classes\MyCap\Api\DB\ParticipantDB;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\ProjectHandler;

/**
 * MyCap MOBILEAPP Class
 * Contains methods used with regard to the MyCap Mobile App
 */
class MyCap
{
    // Current project_id for this object
    public $project_id = null;
    // Array with project's basic values
    public $project = null;
    public $config;
    public $tasks;

    private static $mycapproject_cache = array();

	// URLs for app stores to download app
    //TODO - MyCap::Assign values to below constants
	const URL_IOS_APP_STORE 	= "https://itunes.apple.com/us/app/mycap/id1209842552?ls=1&mt=8";
	const URL_GOOGLE_PLAY_STORE = "https://play.google.com/store/apps/details?id=org.vumc.victr.mycap";
    const URL_FLUTTER_IOS_APP_STORE 	= "https://apps.apple.com/app/pacym/id6448734173";
    const URL_FLUTTER_GOOGLE_PLAY_STORE = "https://play.google.com/store/apps/details?id=org.vumc.mycapplusbeta";
	const URL_AMAZON_APP_STORE 	= "";
	const URL_GOOGLE_MAPS   	= "";

    const FILE_CATEGORY_PROMIS_FORM = '.PromisForm';
    const FILE_CATEGORY_PROMIS_CALIBRATION = '.PromisCalibration';
    const FILE_CATEGORY_IMAGE = '.Image';
    const FILE_CATEGORY_CONFIG_VERSION = '.Version';

    const EVENT_DISPLAY_ID_FORMAT = 'ID';
    const EVENT_DISPLAY_LABEL_FORMAT = 'LABEL';
    const EVENT_DISPLAY_NONE_FORMAT = 'NONE';

    /**
     * Constructor
     * @param mixed $this_project_id
     */
    public function __construct($this_project_id = null)
    {
        // Set project_id for this object
        if ($this_project_id == null) {
            if (defined("PROJECT_ID")) {
                $this->project_id = PROJECT_ID;
            } else {
                throw new Exception('No project_id provided!');
            }
        } else {
            $this->project_id = $this_project_id;
        }
        // Validate project_id as numeric
        if (!is_numeric($this->project_id)) throw new Exception('Project_id must be numeric!');
        // If project already exists in the cache, then return its cached value from the array (no need to re-run queries to build it)
        if (isset(self::$mycapproject_cache[$this->project_id])) {
            // Set this object's attributes from cached one in array
            foreach (self::$mycapproject_cache[$this->project_id] as $key=>$val) {
                $this->$key = $val;
            }
        }
        else {
            // Load all project attributes
            $this->loadMyCapProjectValues();
            // Place the object into a larger array that is a collection of all Proj objects from this request.
            // This will reduce number of queries run and allow us to not have to always call "global $myCapProj" inside methods.
            self::$mycapproject_cache[$this->project_id] = $this;
        }
    }

    /**
     * Checks whether a form is a MyCap Task
     * @param string $form_name 
     * @return bool 
     */
    public function isTask($form_name) {
        return isset($this->tasks[$form_name]);
    }

    /**
     * Checks whether a form is a MyCap Active Task
     * @param string $form_name 
     * @return bool 
     */
    public function isActiveTask($form_name) {
        return $this->isTask($form_name) && $this->tasks[$form_name]["is_active_task"] == true;
    }

    /**
     * Checks whether a form is a MyCap MTB Active Task
     * @param string $form_name 
     * @return bool 
     */
    public function isMtbActiveTask($form_name) {
        return $this->isTask($form_name) && $this->tasks[$form_name]["is_mtb_active_task"] == true;
    }

    /**
     * Load this project's basic values from redcap_mycap_projects
     *
     * @return void
     */
    public function loadMyCapProjectValues()
    {
        $Proj = new Project($this->project_id);
        $this->project = array();
        $sql = "SELECT * FROM redcap_mycap_projects WHERE project_id = " . $this->project_id;
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            foreach ($row as $key=>$value) {
                $this->project[$key] = $value;
            }
        }
        $this->config = $this->project['config'] ?? '';

        $this->tasks = array();
        // Populate tasks array those enabled for MyCap
        $sql = "SELECT * FROM redcap_mycap_tasks WHERE project_id = " . $this->project_id;
        $q = db_query($sql);
        $tasks_order = array();
        while ($row = db_fetch_assoc($q))
        {
            $this->tasks[$row['form_name']]['task_id'] = $row['task_id'];
            $this->tasks[$row['form_name']]['title'] = label_decode($row['task_title']);
            $this->tasks[$row['form_name']]['redcap_instrument']= $row['form_name'];
            $this->tasks[$row['form_name']]['enabled_for_mycap'] = $row['enabled_for_mycap'];
            $this->tasks[$row['form_name']]['schedule_details'] = Task::displayTaskSchedule($row['task_id'], $this->project_id);
            $this->tasks[$row['form_name']]['is_active_task'] = ActiveTask::isActiveTask($row['question_format']);
            $this->tasks[$row['form_name']]['is_mtb_active_task'] = ActiveTask::isMTBActiveTask($row['question_format']);
            $this->tasks[$row['form_name']]['is_spanish_mtb_active_task'] = str_ends_with($row['question_format'], 'Spanish');
            // Make sure tasks are in form order
            $tasks_order[$row['form_name']] = $Proj->forms[$row['form_name']]['form_number'];
        }
        db_free_result($q);
        // Make sure tasks are in form order
        asort($tasks_order);
        $tasks2 = array();
        foreach ($tasks_order as $this_form=>$order) {
            $tasks2[$this_form] = $this->tasks[$this_form];
        }
        $this->tasks = $tasks2;
        unset($tasks2);
    }


    /**
     * Return boolean if the currently saved/published app configuration is out of date (and thus users needs to click Publish)
     */
    public function isPublishedVersionOutOfDate()
    {
        // Replace guids in the JSON (they are randomly generated, so must be removed for diff check)
        $currentJSON = $this->config;
        $currentObject = json_decode($currentJSON);
        $guidReplace = [];
        if (!empty($currentObject->tasks)) {
            foreach ($currentObject->tasks as $attr) {
                if (isset($attr->instructionStep->identifier)) $guidReplace[] = $attr->instructionStep->identifier;
                if (isset($attr->completionStep->identifier)) $guidReplace[] = $attr->completionStep->identifier;

                // If project is longitunal schedules will be multiple and will be inside longitudinalSchedule
                if (isset($currentObject->isLongitudinal) && isset($attr->longitudinalSchedule)) {
                    foreach ($attr->longitudinalSchedule as $schedule) {
                        if (isset($schedule->instructionStep->identifier)) $guidReplace[] = $schedule->instructionStep->identifier;
                        if (isset($schedule->completionStep->identifier)) $guidReplace[] = $schedule->completionStep->identifier;
                    }
                }
                // If PROMIS instrument enabled for MyCap
                if (isset($attr->triggers)) {
                    foreach ($attr->triggers as $trigger) {
                        if (isset($trigger->uuid)) $guidReplace[] = $trigger->uuid;
                    }
                }
            }
        }
        if (!empty($guidReplace)) $currentJSON = str_replace($guidReplace, "", $currentJSON);

        $generatedJSON = $this->generateProjectConfigJSON($this->project_id);
        $generatedObject = json_decode($generatedJSON);
        $guidReplace = [];
        foreach ($generatedObject->tasks as $attr) {
            if (isset($attr->instructionStep->identifier)) $guidReplace[] = $attr->instructionStep->identifier;
            if (isset($attr->completionStep->identifier)) $guidReplace[] = $attr->completionStep->identifier;

            // If project is longitunal schedules will be multiple and will be inside longitudinalSchedule
            if (isset($generatedObject->isLongitudinal) && isset($attr->longitudinalSchedule)) {
                foreach ($attr->longitudinalSchedule as $schedule) {
                    if (isset($schedule->instructionStep->identifier)) $guidReplace[] = $schedule->instructionStep->identifier;
                    if (isset($schedule->completionStep->identifier)) $guidReplace[] = $schedule->completionStep->identifier;
                }
            }
            // If PROMIS instrument enabled for MyCap
            if (isset($attr->triggers)) {
                foreach ($attr->triggers as $trigger) {
                    if (isset($trigger->uuid)) $guidReplace[] = $trigger->uuid;
                }
            }
        }
        if (!empty($guidReplace)) $generatedJSON = str_replace($guidReplace, "", $generatedJSON);

        return ($generatedJSON !== $currentJSON);
    }

    /**
     * Publish new MyCap config
     *
     * @param int $projectId
     */
    public function publishConfigVersion($projectId)
    {
        // Check if any task warnings for all instruments exists
        $taskWarnings = Task::getMyCapTaskErrors('');
        if (!empty($taskWarnings)) {
            // Fix all warnings for all instruments before publishing config
            Task::fixMyCapTaskErrors('');
        }

        // Fix issue with pages (if first page is not having subtype as .Home
        $pageObj = new Page();
        $allPages = $pageObj->getAboutPagesSettings($projectId);
        $i = 1;
        if (count($allPages) > 0) {
            foreach ($allPages as $pageId => $attr) {
                if ($i == 1 && $attr['sub_type'] == Page::SUBTYPE_CUSTOM) {
                    $sql = "UPDATE redcap_mycap_aboutpages SET sub_type = '". Page::SUBTYPE_HOME ."' WHERE project_id = '" . $projectId . "' AND page_id = '" . db_escape($pageId) . "'";
                    db_query($sql);
                }
                $i++;
            }
        }
        $projectConfig = json_decode($this->generateProjectConfigJSON($projectId));
        // Increment Version to "1"
        $projectConfig->version++;
        $newProjectConfig = json_encode($projectConfig);
        $sql = "UPDATE redcap_mycap_projects 
        SET 
            config ='".db_escape($newProjectConfig)."'
        WHERE project_id = ".$_GET['pid'];
        if (db_query($sql)) {
            // Logging
            \Logging::logEvent($sql,"redcap_mycap_projects","MANAGE",$projectId,"project_id = ".$projectId,
                "Publish new MyCap version \n(Version ".$projectConfig->version.")");
            return true;
        }
        return false;
    }

    /**
     * Generate and Return this project's new config by populating all pages, links, contacts, tasks etc..
     *
     * @param int $projectId
     * @param string $publishedFrom (all, mycappages, mycaptasks)
     * @return string $projectConfig
     */
    public function generateProjectConfigJSON($projectId = null, $publishedFrom = 'all') {

        // Fix About pages images issue
        Page::fixAboutImages($projectId);

        $this->loadMyCapProjectValues();
        if (is_null($projectId))  $projectId = PROJECT_ID;

        $Proj = new Project($projectId);

        if ($this->config != '') {
            $config = json_decode($this->config);
            $identifier = $config->identifier;
            $version = $config->version;
        } else {
            $version = 0;
        }
        if (!isset($identifier) || is_null($identifier)) {
            $identifier = db_result(db_query("SELECT code FROM redcap_mycap_projects WHERE project_id = ".$projectId), 0);
        }

        if ($publishedFrom == 'mycappages' || $publishedFrom == 'all') {
            $pageObj = new Page();
            $pages = $pageObj->getAboutPagesSettings($projectId);
            $i = 0;
            $pagesData = [];
            foreach ($pages as $pageId => $page) {
                $pagesData[$i]['type'] = Page::TYPE_INTRO;
                $pagesData[$i]['dag_id'] = $page['dag_id'];
                $pagesData[$i]['identifier'] = $page['identifier'];
                $pagesData[$i]['subType'] = $page['sub_type'];
                $pagesData[$i]['title'] = $page['page_title'];
                $pagesData[$i]['content'] = $page['page_content'];
                $pagesData[$i]['imageName'] = ($page['image_type'] == Page::IMAGETYPE_SYSTEM ? $page['system_image_name'] : \Files::getEdocName($page['custom_logo'], true));
                $pagesData[$i]['imageType'] = $page['image_type'];
                $pagesData[$i]['sortOrder'] = (int) $page['page_order'];
                $i++;
            }

            $links = Link::getLinks($projectId);
            $i = 0;
            $linksData = [];
            foreach ($links as $linkId => $link) {
                $linksData[$i]['dag_id'] = $link['dag_id'];
                $linksData[$i]['identifier'] = $link['identifier'];
                $linksData[$i]['imageName'] = $link['link_icon'];
                $linksData[$i]['name'] = $link['link_name'];
                $linksData[$i]['url'] = $link['link_url'];
                $linksData[$i]['appendProjectCode'] = (bool) $link['append_project_code'];
                $linksData[$i]['appendParticipantCode'] = (bool) $link['append_participant_code'];
                $linksData[$i]['sortOrder'] = (int) $link['link_order'];
                $i++;
            }

            $contacts = Contact::getContacts($projectId);
            $i = 0;
            $contactsData = [];
            foreach ($contacts as $contactId => $contact) {
                $contactsData[$i]['identifier'] = $contact['identifier'];
                $contactsData[$i]['type'] = Contact::TYPE_SUPPORT;
                $contactsData[$i]['dag_id'] = $contact['dag_id'];
                $contactsData[$i]['name'] = $contact['contact_header'];
                $contactsData[$i]['title'] = $contact['contact_title'];
                $contactsData[$i]['phone'] = $contact['phone_number'];
                $contactsData[$i]['email'] = $contact['email'];
                $contactsData[$i]['website'] = $contact['website'];
                $contactsData[$i]['notes'] = $contact['additional_info'];
                $contactsData[$i]['sortOrder'] = (int) $contact['contact_order'];
                $i++;
            }

            $theme = Theme::getTheme($projectId);
            $themeData['primaryColor'] = $theme['primary_color'];
            $themeData['lightPrimaryColor'] = $theme['light_primary_color'];
            $themeData['accentColor'] = $theme['accent_color'];
            $themeData['darkPrimaryColor'] = $theme['dark_primary_color'];
            $themeData['lightBackgroundColor'] = $theme['light_bg_color'];
            $themeData['type'] = $theme['theme_type'];
            $themeData['systemType'] = $theme['system_type'];

            $baseline_date_config = $this->project['baseline_date_config'] ?? "";
            $zeroDateTaskData = !empty($baseline_date_config) ? json_decode($baseline_date_config, true) : ZeroDateTask::getDefaultBaselineDateSettings();
        } else {
            $pagesData = $config->pages;
            $linksData = $config->links;
            $contactsData = $config->contacts;
            $themeData = $config->theme;
            $baseline_date_config = $this->project['baseline_date_config'];
            $zeroDateTaskData = !empty($baseline_date_config) ? json_decode($baseline_date_config, true) : ZeroDateTask::getDefaultBaselineDateSettings();
        }

        if ($publishedFrom == 'mycaptasks' || $publishedFrom == 'all') {
            // Populate tasks array those enabled for MyCap
            $sql = "SELECT * FROM redcap_mycap_tasks WHERE project_id = " . $projectId . " AND enabled_for_mycap = 1";
            $q = db_query($sql);
            $tasks = array();
            $tasks_order = array();
            while ($row = db_fetch_assoc($q)) {
                $tasks[$row['task_id']] = $row;
                // Make sure tasks are in form order
                $tasks_order[$row['task_id']] = $Proj->forms[$row['form_name']]['form_number'];
            }

            db_free_result($q);
            // Make sure tasks are in form order
            asort($tasks_order);
            $tasks2 = array();
            foreach ($tasks_order as $this_id=>$order) {
                $tasks2[$this_id] = $tasks[$this_id];
            }
            $tasks = $tasks2;

            $i = 0;
            $tasksData = array();
            $errorTasks = array();
            foreach ($tasks as $taskId => $task) {
                $form_name = $task['form_name'];
                $taskSchedules = Task::getTaskSchedules($taskId);
                if (!empty($taskSchedules)) {
                    $tasksData[$i]['identifier'] = $form_name;
                    $tasksData[$i]['title'] = $task['task_title'];
                    $tasksData[$i]['enabled'] = true;
                    $tasksData[$i]['format'] = $task['question_format'];
                    $tasksData[$i]['extendedConfigJson'] = $task['extended_config_json'];
                    $tasksData[$i]['dormant'] = false;
                    $tasksData[$i]['sortOrder'] = $Proj->forms[$form_name]['form_number'];
                    $tasksData[$i]['notification'] = "";

                    if ($Proj->longitudinal) {
                        if (count($taskSchedules) > 0) {
                            foreach ($taskSchedules as $eventId => $schedule) {
                                $type = $schedule['schedule_type'];
                                $frequency = ($schedule['schedule_type'] == Task::TYPE_REPEATING) ? $schedule['schedule_frequency'] : null;
                                $interval = 0;
                                if ($frequency == Task::FREQ_WEEKLY) {
                                    $interval = $schedule['schedule_interval_week'];
                                } else if ($frequency == Task::FREQ_MONTHLY) {
                                    $interval = $schedule['schedule_interval_month'];
                                }

                                if ($schedule['include_instruction_step'] == 1) {
                                    $instructionStep['identifier'] = self::guid();
                                    $instructionStep['type'] = Page::TYPE_TASKINSTRUCTIONSTEP;
                                    $instructionStep['subType'] = Page::IMAGETYPE_CUSTOM;
                                    $instructionStep['title'] = $schedule['instruction_step_title'];
                                    $instructionStep['content'] = $schedule['instruction_step_content'];
                                    $instructionStep['imageName'] = "";
                                    $instructionStep['imageType'] = "";
                                    $instructionStep['sortOrder'] = 1;
                                } else {
                                    $instructionStep = null;
                                }
                                if ($schedule['include_completion_step'] == 1) {
                                    $completionStep['identifier'] = self::guid();
                                    $completionStep['type'] = Page::TYPE_TASKCOMPLETIONSTEP;
                                    $completionStep['subType'] = Page::IMAGETYPE_CUSTOM;
                                    $completionStep['title'] = $schedule['completion_step_title'];
                                    $completionStep['content'] = $schedule['completion_step_content'];
                                    $completionStep['imageName'] = "";
                                    $completionStep['imageType'] = "";
                                    $completionStep['sortOrder'] = 1;
                                } else {
                                    $completionStep = null;
                                }
                                $tasksData[$i]['longitudinalSchedule'][] = array('identifier' => (string) $eventId,
                                    'eventLabel' => $Proj->eventInfo[$eventId]['name'],
                                    'arm' => $Proj->eventInfo[$eventId]['arm_num'],
                                    'type' => $type,
                                    'allowsSaving' => ($schedule['allow_save_complete_later'] == 1) ? true : false,
                                    'allowsRetroactiveCompletion' => ($schedule['allow_retro_completion'] == 1) ? true : false,
                                    'frequency' => $frequency,
                                    'interval' => (int)$interval,
                                    'daysFixed' => ($schedule['schedule_days_fixed'] != "") ? $schedule['schedule_days_fixed'] : null,
                                    'daysOfTheMonth' => ($schedule['schedule_days_of_the_month'] != "") ? $schedule['schedule_days_of_the_month'] : null,
                                    'daysOfTheWeek' => ($schedule['schedule_days_of_the_week'] != "") ? $schedule['schedule_days_of_the_week'] : null,
                                    'relativeTo' => ($schedule['schedule_relative_to'] != "") ? $schedule['schedule_relative_to'] : Task::RELATIVETO_JOINDATE,
                                    'relativeOffset' => (int)$schedule['schedule_relative_offset'],
                                    'ends' => ($schedule['schedule_ends'] != "") ? $schedule['schedule_ends'] : Task::ENDS_NEVER,
                                    'endAfterDays' => (int)$schedule['schedule_end_after_days'],
                                    'endCount' => (int)$schedule['schedule_end_count'],
                                    'endDate' => ($schedule['schedule_end_date'] != "") ? $schedule['schedule_end_date'] : null,
                                    'instructionStep' => $instructionStep,
                                    'completionStep' => $completionStep);
                            }
                        }
                    } else {
                        $schedule = $taskSchedules[$Proj->firstEventId];
                        $type = $schedule['schedule_type'];
                        $frequency = ($schedule['schedule_type'] == Task::TYPE_REPEATING) ? $schedule['schedule_frequency'] : null;
                        $interval = 0;
                        if ($frequency == Task::FREQ_WEEKLY) {
                            $interval = $schedule['schedule_interval_week'];
                        } else if ($frequency == Task::FREQ_MONTHLY) {
                            $interval = $schedule['schedule_interval_month'];
                        }
                        $tasksData[$i]['schedule'] = array('type' => $type,
                            'frequency' => $frequency,
                            'interval' => (int)$interval,
                            'daysFixed' => ($schedule['schedule_days_fixed'] != "") ? $schedule['schedule_days_fixed'] : null,
                            'daysOfTheMonth' => ($schedule['schedule_days_of_the_month'] != "") ? $schedule['schedule_days_of_the_month'] : null,
                            'daysOfTheWeek' => ($schedule['schedule_days_of_the_week'] != "") ? $schedule['schedule_days_of_the_week'] : null,
                            'relativeTo' => ($schedule['schedule_relative_to'] != "") ? $schedule['schedule_relative_to'] : Task::RELATIVETO_JOINDATE,
                            'relativeOffset' => (int)$schedule['schedule_relative_offset'],
                            'ends' => ($schedule['schedule_ends'] != "") ? $schedule['schedule_ends'] : Task::ENDS_NEVER,
                            'endAfterDays' => (int)$schedule['schedule_end_after_days'],
                            'endCount' => (int)$schedule['schedule_end_count'],
                            'endDate' => ($schedule['schedule_end_date'] != "") ? $schedule['schedule_end_date'] : null);

                        $tasksData[$i]['allowsSaving'] = ($schedule['allow_save_complete_later'] == 1) ? true : false;
                        $tasksData[$i]['allowsRetroactiveCompletion'] = ($schedule['allow_retro_completion'] == 1) ? true : false;
                        if ($schedule['include_instruction_step'] == 1) {
                            $tasksData[$i]['instructionStep']['identifier'] = self::guid();
                            $tasksData[$i]['instructionStep']['type'] = Page::TYPE_TASKINSTRUCTIONSTEP;
                            $tasksData[$i]['instructionStep']['subType'] = Page::IMAGETYPE_CUSTOM;
                            $tasksData[$i]['instructionStep']['title'] = $schedule['instruction_step_title'];
                            $tasksData[$i]['instructionStep']['content'] = $schedule['instruction_step_content'];
                            $tasksData[$i]['instructionStep']['imageName'] = "";
                            $tasksData[$i]['instructionStep']['imageType'] = "";
                            $tasksData[$i]['instructionStep']['sortOrder'] = 1;
                        } else {
                            $tasksData[$i]['instructionStep'] = null;
                        }

                        if ($schedule['include_completion_step'] == 1) {
                            $tasksData[$i]['completionStep']['identifier'] = self::guid();
                            $tasksData[$i]['completionStep']['type'] = Page::TYPE_TASKCOMPLETIONSTEP;
                            $tasksData[$i]['completionStep']['subType'] = Page::IMAGETYPE_CUSTOM;
                            $tasksData[$i]['completionStep']['title'] = $schedule['completion_step_title'];
                            $tasksData[$i]['completionStep']['content'] = $schedule['completion_step_content'];
                            $tasksData[$i]['completionStep']['imageName'] = "";
                            $tasksData[$i]['completionStep']['imageType'] = "";
                            $tasksData[$i]['completionStep']['sortOrder'] = 1;
                        } else {
                            $tasksData[$i]['completionStep'] = null;
                        }
                    }

                    $tasksData[$i]['card']['type'] = $task['card_display'];
                    if ($task['card_display'] == Task::TYPE_DATELINE) {
                        $tasksData[$i]['card']['dateLineCard'] = array('xDateField' => str_replace(array('[', ']'), array('', ''), $task['x_date_field']),
                            'xTimeField' => str_replace(array('[', ']'), array('', ''), $task['x_time_field']),
                            'yNumericField' => str_replace(array('[', ']'), array('', ''), $task['y_numeric_field']));
                    } else {
                        $tasksData[$i]['card']['dateLineCard'] = null;
                    }

                    $tasksData[$i]['fields'] = [];

                    list($isPromis, $isAutoScoringInstrument) = \PROMIS::isPromisInstrument($form_name);

                    $triggers = array();
                    $trigger = null;
                    if ($isPromis) {
                        // Check if Battery Instrument
                        $batteryInstrumentsList = Task::batteryInstrumentsInSeriesPositions();
                        if (array_key_exists($form_name, $batteryInstrumentsList)) {
                            $trigger = Promis::triggerForBattery(
                                $form_name,
                                $batteryInstrumentsList
                            );
                        }
                    }
                    if (!is_null($trigger)) {
                        $triggers[] = $trigger;
                        $tasksData[$i]['triggers'] = $triggers;
                    } else {
                        $tasksData[$i]['triggers'] = null;
                    }
                    $i++;
                } else {
                    $errorTasks[] = $form_name;
                }
            }
        } else {
            $tasksData = $config->tasks;
        }

        // TODO: Need to implement permission editing UI. For now, just make sure we ask for push notifications
        $permissions['type'] = Permission::TYPE_NOTIFICATIONS;
        $permissions['content'] = Permission::TYPE_NOTIFICATION_CONTENT;
        $permissions['required'] = false;
        $permissionData[] = $permissions;

        $title = $this->project['name'];

        $projectConfigArr['identifier'] = $identifier;
        $projectConfigArr['title'] = filter_tags(html_entity_decode($title, ENT_QUOTES));
        if ($Proj->longitudinal) {
            $projectConfigArr['isLongitudinal'] = true;
        }

        $projectConfigArr['isFormActivationEnabled'] = ($Proj->project['form_activation_mycap_support'] == 1 ? true : false);
        $projectConfigArr['version'] = $version;
        $projectConfigArr['tasks'] = $tasksData;
        $projectConfigArr['pages'] = $pagesData;
        $projectConfigArr['permissions'] = $permissionData;
        $projectConfigArr['contacts'] = (!empty($contactsData)) ? $contactsData : null;
        $projectConfigArr['links'] = (!empty($linksData)) ? $linksData : null;
        $projectConfigArr['theme'] = $themeData;
        $projectConfigArr['zeroDateTask'] = $zeroDateTaskData ?? ZeroDateTask::getDefaultBaselineDateSettings();
        $projectConfigArr['languages'] = MultiLanguage::getMyCapActiveLanguages($projectId);

        $myCapProj = new MyCap($projectId);
        $settingData['notification']['time'] = substr($myCapProj->project['notification_time'], 0, 5); // publish in hh:mm format;
        if ($Proj->longitudinal) {
            $settingData['eventDisplayFormat'] = $myCapProj->project['event_display_format'];
        }

        $mtb_english_tasks_exists = ActiveTask::isLangMTBActiveTaskExists('English');
        $mtb_spanish_tasks_exists = ActiveTask::isLangMTBActiveTaskExists('Spanish');

        if ($mtb_english_tasks_exists && $mtb_spanish_tasks_exists) {
            $settingData['preventMTBLangSwitch'] = ($myCapProj->project['prevent_lang_switch_mtb'] == 1) ? true : false;
        }

        $projectConfigArr['settings'] = $settingData;

        $projectConfig = json_encode($projectConfigArr);

        return $projectConfig;
    }

    /**
     * Initialize MyCap - insert default homepage in pages table and default theme in theme table
     *
     * @return boolean $response
     */
    public function initMyCap($project_id = null)
    {
        $enableMyCapInProject = (SUPER_USER || $GLOBALS['mycap_enable_type'] != 'admin');

        if (is_null($project_id)) {
            global $Proj;
            $project_id = PROJECT_ID;
        } else {
            $Proj = new Project($project_id);
        }

        $projectTitle = $Proj->project['app_title'];


        // Insert default entry in about pages db table
        // Default about page
        $page['project_id'] = $project_id;
        $page['identifier'] = self::guid();
        $page['page_title'] = filter_tags(html_entity_decode($projectTitle, ENT_QUOTES));
        $page['page_content'] = Page::DEFAULT_DESCRIPTION;
        $page['sub_type'] = Page::SUBTYPE_HOME;
        $page['image_type'] = Page::IMAGETYPE_CUSTOM;
        $page['custom_logo'] = Page::uploadDefaultImageFile($project_id);
        $page['page_order'] = 1;
        $db_keys = array_map(function($item) { return "`".db_escape($item)."`";}, array_keys($page));
        $sql = "INSERT INTO redcap_mycap_aboutpages (".implode(', ', $db_keys).") VALUES
                    (".prep_implode(array_values($page)).")";
        db_query($sql);

        // Default Theme
        Theme::insertDefaultTheme($project_id);

        // Project is enabled first time return default initial config
        $sql = "INSERT INTO redcap_mycap_projects (code, hmac_key, project_id, name) 
                VALUES ('".$this->generateUniqueCode()."',
                    '".$this->generateHmacKey()."',
                    '".$project_id."',
                    '".$projectTitle."')";

        if (db_query($sql)) {
            // Update redcap_projects to set mycap_enabled to 1/0
            $sql_project = "UPDATE redcap_projects SET mycap_enabled = '1' WHERE project_id = $project_id";
            if ($enableMyCapInProject) db_query($sql_project);

            // Generate Project Config
            $projectConfig = $this->generateProjectConfigJSON($project_id);
            $sql_config = "UPDATE redcap_mycap_projects SET config = '".db_escape($projectConfig)."' WHERE project_id = $project_id";
            // Update redcap_mycap_projects to set json config
            db_query($sql_config);

            // Fetch all records and insert into MyCap participants db table
            $recordNames = array_values(\Records::getRecordList($project_id));
            if (!empty($recordNames)) {
                foreach ($recordNames as $record) {
                    Participant::saveParticipant($project_id, $record);
                }
            }

            Page::createAboutImagesZip($project_id);
            // Set response
            $response = "1";
        } else {
            $response = 0;
        }
        return $response;
    }

    /**
     * Generate unique code for MyCap project
     *
     * @return string $code
     */
    public function generateUniqueCode()
    {
        do {
            // Excluding letters I & O and number 0 to avoid confusion
            $code = 'P-' . self::generateRandomString(20);
            $sql = "SELECT * FROM redcap_mycap_projects WHERE project_id != ".PROJECT_ID." AND code = '".db_escape($code)."'";
            $q = db_query($sql);
            $count = db_num_rows($q);
        } while ($count > 0);

        return $code;
    }

    /**
     * Generate hmacKey (Hash-based Message Access Code key) for MyCap project
     *
     * @return string $hmacKey
     */
    public function generateHmacKey()
    {
        do {
            // Excluding letters I & O and number 0 to avoid confusion
            $hmacKey = bin2hex(self::generateHmacRandomString(32));
            $sql = "SELECT * FROM redcap_mycap_projects WHERE project_id != ".PROJECT_ID." AND hmac_key = '".db_escape($hmacKey)."'";
            $q = db_query($sql);
            $count = db_num_rows($q);
        } while ($count > 0);

        return $hmacKey;
    }

    /**
     * Generate random string for hmac key generation of MyCap project
     *
     * @return string $randomString
     */
    public static function generateHmacRandomString($length = 20) {
        $characters = 'abcdefghijklmnopqrstuvwxyz123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Generate random string for unique code generation of MyCap project
     *
     * @return string $randomString
     */
    public static function generateRandomString($length = 20) {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Return success and error message containers
     *
     * @return string
     */
    public static function getMessageContainers() {
        return '<div id="errMsgContainer" class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;"></div>
                <div class="alert alert-success" style="max-width:800px;border-color: #b2dba1 !important;display: none;" id="succMsgContainer"></div>';
    }

    /**
     * Return tabs html for MyCap MobileApp module
     *
     * @return string $tabContent
     */
    public function renderTabs() {
        global $lang, $user_rights, $myCapProj;

        $partCountBlock = '';
        if (!$myCapProj->project['acknowledged_app_link']) { // acknowledged_app_link = "0" in db
            $partCountBlock = '<span id="partCountBlock" class="badgerc">1</span>';
        }
        $tabs["participants"] = array("fa"=>"fas fa-users", "label"=>$lang['mycap_mobile_app_01'].$partCountBlock);

        if ($user_rights['mycap_participants'] == 1) {
            $actionNeededCount = Message::$action_needed_messages;
            $countBlock = '';
            if ($actionNeededCount > 0) {
                $countBlock = '<span class="badgerc">'.$actionNeededCount.'</span>';
            }
            $tabs["messages"] = array("fa"=>"fas fa-comments", "label"=>$lang['mycap_mobile_app_415'].$countBlock);
        }

        $unresolvedCount = SyncIssues::getUnresolvedIssuesCount(PROJECT_ID);
        $countBlock = '';
        if ($unresolvedCount > 0) {
            $countBlock = '<span class="badgerc">'.$unresolvedCount.'</span>';
        }
        $tabs["syncissues"] = array("fa"=>"fas fa-sync", "label"=>$lang['mycap_mobile_app_501'].$countBlock);
        $tabs["help"] = array("fa"=>"fas fa-circle-question", "label"=>$lang['mycap_mobile_app_912']);

        $tabContent = '<div id="sub-nav" class="project_setup_tabs d-none d-sm-block" style="padding-left: 0px !important;"><ul>';

        foreach ($tabs as $this_param => $this_set) {
            if ($this_param == 'messages') {
                $class = ((isset($_GET[$this_param]) &&  $_GET[$this_param] == 1)
                            || (isset($_GET['outbox']) &&  $_GET['outbox'] == 1)
                            || (isset($_GET['announcements']) &&  $_GET['announcements'] == 1)
                            || (isset($_GET['msg_settings']) &&  $_GET['msg_settings'] == 1))
                        ? 'class="active"' : '';
            } else {
                $class = (isset($_GET[$this_param]) &&  $_GET[$this_param] == 1) ? 'class="active"' : '';
            }

            $tabContent .= '<li '.$class .'>';
            $url = APP_PATH_WEBROOT . 'MyCapMobileApp/index.php?' . $this_param . '=1&pid=' . PROJECT_ID;
            $tabContent .= '<a href="'. $url .'" style="font-size:13px;color:#393733;padding:7px 9px;">';
            if (isset($this_set['fa'])) {
                $tabContent .= '<i class="'.$this_set['fa'].'"></i> ';
            } elseif (empty($this_set['icon'])) {
                $tabContent .= '<img src="'.APP_PATH_IMAGES .'spacer.gif" style="height:16px;width:1px;margin-bottom:-1px;">';
            } else {
                $tabContent .= '<img src="'.APP_PATH_IMAGES . $this_set['icon'].'" style="height:16px;width:16px;margin-bottom:-1px;"> ';
            }
            $tabContent .= '<span style="vertical-align:middle;">'.$this_set['label'].'</span></a></li>';
        }
        $tabContent .= '</ul></div>';
        return $tabContent;
    }

    /**
     * Return task id for redcap instrument
     *
     * @param string $form_name
     * @return int
     */
    public static function getTaskId($form_name = null)
    {
        global $Proj, $myCapProj;
        if (empty($form_name)) $form_name = $Proj->firstForm;
        return (isset($myCapProj->tasks[$form_name]['task_id']) ? $myCapProj->tasks[$form_name]['task_id'] : "");
    }

    /**
     * check If instrumen/task_id is valid combination of Project
     *
     * @param string $form_name
     * @param int $task_id
     * @return boolean
     */
    public static function checkIfValidTaskOfProject($form_name, $task_id)
    {
        global $myCapProj;
        return (is_numeric($task_id) && ($myCapProj->tasks[$form_name]['task_id'] == $task_id));
    }

    /**
     * Create a GUID
     *
     * We typically use GUIDs when inserting records because we don't want to deal
     * with numbering records sequentially.
     * http://php.net/manual/en/function.com-create-guid.php
     *
     * @return string
     */
    public static function guid()
    {
        if (function_exists('com_create_guid') === true) {
            return trim(
                com_create_guid(),
                '{}'
            );
        }
        return sprintf(
            '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(
                0,
                65535
            ),
            mt_rand(
                0,
                65535
            ),
            mt_rand(
                0,
                65535
            ),
            mt_rand(
                16384,
                20479
            ),
            mt_rand(
                32768,
                49151
            ),
            mt_rand(
                0,
                65535
            ),
            mt_rand(
                0,
                65535
            ),
            mt_rand(
                0,
                65535
            )
        );
    }

    /**
     * Get Preview HTML Template
     *
     * @param string $title
     * @param string $content
     * @return string
     */
    public static function getPreviewTemplate($title, $content) {
        global $lang;
        $html = '<!doctype html>
                    <html lang="en">                    
                    <head>
                        <meta charset="utf-8" />
                        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
                        <title>'.$lang['mycap_mobile_app_101'].' - '.$title.'</title>
                        <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport" />
                        <meta name="viewport" content="width=device-width" />';
        $html .= loadCSS('bootstrap.min.css', false);
        $html .= loadCSS('mycap-preview.css', false);
        $html .= loadCSS('slick.css', false);
        $html .= loadCSS('slick-theme.css', false);

        $html .= '</head>                    
                    <body>
                    <div class="wrapper">
                        <div class="main-panel phone-background">
                            <div class="container-fluid">
                                <div class="row">
                                    <div class="col-xs-12 vertical-center text-center phone-nav-bar">
                                        '.$title.'
                                    </div>
                                </div>';
        $html .= $content;

        $html .= '</div>
                </div>
            </div>
            </body>';

        $html .= "<script type='text/javascript' src='". APP_PATH_WEBROOT . "Resources/webpack/js/bundle.js'></script>";
        $html .= loadJS("Libraries/slick.min.js", false);
        $html .= '<script type="text/javascript">
                        $(document).ready(function() {
                            $("#pages").slick({
                                infinite: true,
                                slidesToShow: 1,
                                slidesToScroll: 1,
                                dots: true
                            });
                        });
                </script>';
        $html .= '</html>';

        return $html;
    }

    /**
     * Get Project Id from Code
     * @param string $code
     * @return integer
     */
    public static function getProjectIdByCode($code) {
        $query = "SELECT project_id FROM redcap_mycap_projects WHERE code = '".db_escape($code)."' AND status = 1";
        return db_result(db_query($query), 0);
    }

    /**
     * Get All fields belongs to forms enabled for MyCap
     * @return string
     */
    public function getAllMyCapFormFields() {
        # get all fields for a set of forms, if provided
        $fieldArray = array();
        $mycap_enabled_forms = array_keys($this->tasks);

        $formList = prep_implode($mycap_enabled_forms);
        $query = "SELECT field_name FROM redcap_metadata
                        WHERE project_id = '".$this->project_id."' AND form_name IN ($formList)
                        ORDER BY field_order";
        $fieldResults = db_query($query);
        while ($row = db_fetch_assoc($fieldResults))
        {
            $fieldArray[] = $row['field_name'];
        }
        $content = \MetaData::getDataDictionary('json', false, $fieldArray, array(), false, false, null, $this->project_id);
        return $content;
    }

    /**
     * Check if file category is valid or not
     * @param string $fil_category
     * @return boolean
     */
    public static function isValidFileCategory($fil_category) {
        $file_array = array(self::FILE_CATEGORY_PROMIS_FORM, self::FILE_CATEGORY_PROMIS_CALIBRATION, self::FILE_CATEGORY_IMAGE, self::FILE_CATEGORY_CONFIG_VERSION);
        return in_array($fil_category, $file_array);
    }

    /**
     * Get listing of files for specific category for project
     * @param string $fil_category
     * @param int $projectId
     * @return array|integer
     */
    public static function getFileNames($fil_category, $fil_name, $projectId, $projectCode) {
        $values = array();
        switch ($fil_category) {
            case self::FILE_CATEGORY_IMAGE:
                $sql = "SELECT custom_logo FROM redcap_mycap_aboutpages WHERE project_id = '".$projectId."' AND image_type = '".Page::IMAGETYPE_CUSTOM."'";
                $q = db_query($sql);
                while ($row = db_fetch_assoc($q))
                {
                    $values[] = \Files::getEdocName($row['custom_logo'], true);
                }
                break;
            case self::FILE_CATEGORY_PROMIS_FORM:
            case self::FILE_CATEGORY_PROMIS_CALIBRATION:

                if ($fil_category == self::FILE_CATEGORY_PROMIS_FORM) {
                    $category = 1;
                } else if ($fil_category == self::FILE_CATEGORY_PROMIS_CALIBRATION) {
                    $category = 2;
                }
                $sql = "SELECT * FROM redcap_mycap_projectfiles WHERE project_code = '".$projectCode."' AND category = '".$category."' AND name = '".$fil_name."'";

                $q = db_query($sql);
                if (db_num_rows($q) > 0) {
                    while ($row = db_fetch_assoc($q))
                    {
                        $values = $row['doc_id'];
                    }
                }
                break;
            default:
                break;
        }
        return $values;
    }

    /**
     * Export zip file generated while add/edit/delete about page functionality
     * @param string $projectCode
     * @param int $projectId
     * @return string
     */
    public static function exportImagesPack($projectCode, $projectId) {
        # get the file information
        $sql = "SELECT doc_id FROM redcap_mycap_projectfiles
                WHERE project_code = '".$projectCode."' AND category = '3' LIMIT 1";
        $doc_id = db_result(db_query($sql), 0);

        $sql = "SELECT *
                FROM redcap_edocs_metadata
                WHERE project_id = $projectId
                    AND doc_id = '".$doc_id."'";
        $q = db_query($sql);
        if (db_num_rows($q) == 0) {
            return "There is no file to download.";
        }

        $this_file = db_fetch_array($q);

        global $edoc_storage_option;
        if ($edoc_storage_option == '0' || $edoc_storage_option == '3')
        {
            # verify that the edoc folder exists
            if (!is_dir(EDOC_PATH)) {
                return "The server folder ".EDOC_PATH." does not exist! Thus it is not a valid directory for edoc file storage";
            }

            # create full path to the file
            $local_file = EDOC_PATH . \Files::getLocalStorageSubfolder($projectId, true) . $this_file['stored_name'];

            # determine of the file exists on the server
            if (file_exists($local_file) && is_file($local_file)) {
                # log the request
                //logEvent();
                # Send the response to the requestor
                \RestUtility::sendFile(200, $local_file, $this_file['doc_name'], $this_file['mime_type']);
            }
            else {
                return "The file \"$local_file\" (\"{$this_file['doc_name']}\") does not exist";
            }
        }

        elseif ($edoc_storage_option == '2')
        {
            // S3
            $local_file = APP_PATH_TEMP . $this_file['stored_name'];
            try {
                $s3 = \Files::s3client();
                $object = $s3->getObject(array('Bucket'=>$GLOBALS['amazon_s3_bucket'], 'Key'=>$this_file['stored_name'], 'SaveAs'=>$local_file));
                # log the request
                //logEvent();
                # Send the response to the requestor
                \RestUtility::sendFile(200, $local_file, $this_file['doc_name'], $this_file['mime_type']);
                // Now remove file from temp directory
                //unlink($local_file);
            } catch (\Aws\S3\Exception\S3Exception $e) {
                return "Error obtaining the file \"{$this_file['doc_name']}\"";
            }
        }
        elseif ($edoc_storage_option == '4')
        {
            // Azure
            $local_file = APP_PATH_TEMP . $this_file['stored_name'];
            $blobClient = new \AzureBlob();
            $data = $blobClient->getBlob($this_file['stored_name']);
            file_put_contents($local_file, $data);
            # log the request
            //logEvent();
            # Send the response to the requestor
            \RestUtility::sendFile(200, $local_file, $this_file['doc_name'], $this_file['mime_type']);
            // Now remove file from temp directory
            //unlink($local_file);
        }
        elseif ($edoc_storage_option == '5')
        {
            // Google
            $local_file = APP_PATH_TEMP . $this_file['stored_name'];
            $googleClient = \Files::googleCloudStorageClient();
            $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);
            $googleClient->registerStreamWrapper();

            $data = file_get_contents('gs://'.$GLOBALS['google_cloud_storage_api_bucket_name'].'/' . $this_file['stored_name']);

            file_put_contents($local_file, $data);
            # log the request
            //logEvent();
            # Send the response to the requestor
            \RestUtility::sendFile(200, $local_file, $this_file['doc_name'], $this_file['mime_type']);
            // Now remove file from temp directory
            //unlink($local_file);
        }
        else
        {
            # Download using WebDAV
            if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
            $wdc = new \WebdavClient();
            $wdc->set_server($webdav_hostname);
            $wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
            $wdc->set_user($webdav_username);
            $wdc->set_pass($webdav_password);
            $wdc->set_protocol(1); //use HTTP/1.1
            $wdc->set_debug(false);
            if (!$wdc->open()) {
                return "Could not open server connection";
            }
            if (substr($webdav_path,-1) != '/') {
                $webdav_path .= '/';
            }
            $http_status = $wdc->get($webdav_path . $this_file['stored_name'], $contents); //$contents is produced by webdav class
            $wdc->close();

            # log the request
            //logEvent();

            # Send the response to the requestor
            \RestUtility::sendFileContents(200, $contents, $this_file['doc_name'], $this_file['mime_type']);
        }
    }

    /**
     * Load file by name and optionally by category
     *
     * @param string $name
     * @param string|null $category
     */
    public static function loadFileByName($name, $category = '')
    {
        global $myCapProj;
        $projectCode = $myCapProj->project['code'];

        if ($category == self::FILE_CATEGORY_PROMIS_FORM) {
            $fil_category = 1;
        } else if ($category == self::FILE_CATEGORY_PROMIS_CALIBRATION) {
            $fil_category = 2;
        }
        $sql = "SELECT * FROM redcap_mycap_projectfiles
                WHERE project_code = '".$projectCode."' AND name = '".$name."' AND category = '".$fil_category."' LIMIT 1";

        $q = db_query($sql);
        if (db_num_rows($q) > 0) {
            while ($row = db_fetch_assoc($q))
            {
                $values['name'] = $row['name'];
                $values['category'] = $category;
                $values['file'] = \Files::getEdocName($row['doc_id'], true);
            }
        }
        return $values;
    }

    /**
     * Update project config - copied project
     *
     * @param int $new_project_id
     * @param void
     */
    public function updateProjectConfig($new_project_id) {

        // Generate Project Config
        $projectConfig = $this->generateProjectConfigJSON($new_project_id);
        $sql_config = "UPDATE redcap_mycap_projects SET config = '" . db_escape($projectConfig) . "' WHERE project_id = $new_project_id";

        // Update redcap_mycap_projects to set json config
        db_query($sql_config);
    }

    /**
     * Copy Project Participants - copied project
     *
     * @param int $copyof_project_id
     * @param int $new_project_id
     * @param array $eventid_translate
     * @param void
     */
    public function copyProjectParticipants($copyof_project_id, $new_project_id, $eventid_translate) {
        $sql = "SELECT * FROM redcap_mycap_participants WHERE project_id = $copyof_project_id";
        $q = db_query($sql);

        $records = array();
        while ($row = db_fetch_assoc($q))
        {
            $code = Participant::generateUniqueCode($new_project_id);
            db_query("INSERT INTO redcap_mycap_participants (code, project_id, record, event_id, join_date, push_notification_ids, is_deleted) 
                            VALUES ('" . db_escape($code) . "', {$new_project_id}, '".db_escape($row['record'])."',
                                    {$eventid_translate[$row['event_id']]}, NULL, '".db_escape($row['push_notification_ids'])."', '".db_escape($row['is_deleted'])."')");

            $records[$code] = $row['record'];
        }

        foreach ($records as $code=>$record) {
            Participant::updateMyCapParticipantCodeFields($new_project_id, $record, $code);
        }
    }

    /**
     * Copy Project Participants Settings - copied project
     *
     * @param int $copyof_project_id
     * @param int $new_project_id
     * @param array $eventid_translate
     * @param void
     */
    public function copyProjectParticipantSettings($copyof_project_id, $new_project_id, $eventid_translate) {
        $Proj = new Project($new_project_id);
        $isLongitudinal = $Proj->longitudinal;
        $sql = "SELECT * FROM redcap_mycap_projects WHERE project_id = $copyof_project_id";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q))
        {
            $baseline_dt_field = $row['baseline_date_field'];
            if ($isLongitudinal) {
                if (isset($baseline_dt_field) && $baseline_dt_field != '') {
                    if ($Proj->multiple_arms) {
                        $baseline_date_field = [];
                        $date_arr = explode('|', $baseline_dt_field);
                        if (count($date_arr) > 0) {
                            foreach ($date_arr as $dateArm) {
                                $arr = explode('-', $dateArm);
                                if (count($arr) == 2 && $arr[0] !='') {
                                    $baseline_date_field[] = $eventid_translate[$arr[0]].'-'.$arr[1];
                                }
                            }
                        }
                        $baseline_dt_field = implode("|", $baseline_date_field);
                    } else {
                        $arr = explode('-', $baseline_dt_field);
                        if (count($arr) == 2 && $arr[0] !='') {
                            $eventId = $eventid_translate[$arr[0]];
                        }
                        $baseline_dt_field = $eventId.'-'.$arr[1];
                    }
                }
            }
            $sql = "UPDATE redcap_mycap_projects SET allow_new_participants = '".$row['allow_new_participants']."', participant_custom_field = '".$row['participant_custom_field']."',
                            participant_custom_label = '".$row['participant_custom_label']."', participant_allow_condition = '".$row['participant_allow_condition']."',
                            baseline_date_field = '".$baseline_dt_field."', baseline_date_config = '".db_escape($row['baseline_date_config'])."',
                            status = '".$row['status']."'
                        WHERE project_id = $new_project_id";
            db_query($sql);
        }
    }

    /**
     * Display MyCap to REDCap migration confirm box
     *
     * @param boolean $proceed
     * @param string $flag
     * @return string
     */
    public static function renderMigrateMyCapEMtoREDCapDialog($proceed = false, $flag = '')
    {
        if ($proceed == true) {
            $output = self::migrateMyCap($proceed);
        } else {
            global $lang;
            $details = self::migrateMyCap($proceed);

            $taskDetails = "<td valign='top'>
                            <div style='font-size:11px;margin:15px 0 0;padding-left:8px;padding-top: 20px; padding-bottom: 10px; border:1px solid #e4cc5d;background-color:#fdfbf4;'>
                                <b style='color: #800000;'>{$lang['mycap_mobile_app_573']}{$lang['colon']}</b><br>
                                {$lang['mycap_mobile_app_574']} <b>".$details['tasksCount']."</b> {$lang['mycap_mobile_app_575']}
                                <br><br>{$lang['mycap_mobile_app_351']}";
            // Add task to list
            foreach ($details['tasksTitles'] as $title) {
                $taskDetails .=  "<br> &bull; <b>".RCView::escape(strip_tags($title))."</b>";
            }
            $taskDetails.=  "</div></td>";

            $otherDetails = "
				<td valign='top'>
					<div style='font-size:11px;margin:15px 0 0;padding-left:8px;padding-top: 20px; padding-bottom: 10px;border:1px solid #e4cc5d;background-color:#fdfbf4;'>
						<b style='color: #800000'>{$lang['mycap_mobile_app_576']}{$lang['colon']}</b><br>".$lang['mycap_mobile_app_577'];
            $otherDetails .=  "<br> &bull; ".$lang['mycap_mobile_app_01']." ".$lang['leftparen'].$lang['data_entry_495']." <b>".$details['parCount']."</b>".$lang['rightparen'];
            $otherDetails .=  "<br> &bull; ".$lang['mycap_mobile_app_432']." ".$lang['leftparen'].$lang['data_entry_495']." <b>".$details['inboxCount']."</b>".$lang['rightparen'];
            $otherDetails .=  "<br> &bull; ".$lang['mycap_mobile_app_418']." ".$lang['mycap_mobile_app_415']." ".$lang['leftparen'].$lang['data_entry_495']." <b>".$details['outboxCount']."</b>".$lang['rightparen'];
            $otherDetails .=  "<br> &bull; ".$lang['mycap_mobile_app_419']." ".$lang['leftparen'].$lang['data_entry_495']." <b>".$details['annCount']."</b>".$lang['rightparen'];
            $otherDetails .=  "<br> &bull; ".$lang['mycap_mobile_app_02']." ".$lang['leftparen'].$lang['data_entry_495']." <b>".$details['pagesCount']."</b>".$lang['rightparen'];
            $otherDetails .=  "<br> &bull; ".$lang['mycap_mobile_app_03']." ".$lang['leftparen'].$lang['data_entry_495']." <b>".$details['contactsCount']."</b>".$lang['rightparen'];
            $otherDetails .=  "<br> &bull; ".$lang['mycap_mobile_app_04']." ".$lang['leftparen'].$lang['data_entry_495']." <b>".$details['linksCount']."</b>".$lang['rightparen'];
            $otherDetails .=  "<br> &bull; ".$lang['mycap_mobile_app_05']." ".$lang['leftparen']."<b>".$details['themeDetails']."</b>".$lang['rightparen'];
            $otherDetails .=  "<br> &bull; ".$lang['mycap_mobile_app_501']." ".$lang['leftparen'].$lang['data_entry_495']." <b>".$details['syncIssuesCount']."</b>".$lang['rightparen'];
            $otherDetails.=  "</div></td>";

            // Output html
            $output = '';
            if ($flag == 'showWarning') {
                $output = RCView::div(array('style'=>'color:red;margin-top:10px;'), $lang['mycap_mobile_app_578']);
            }

            $instructionsText = MyCap::getMyCapMigrationInstructions();
            $output .= RCView::div(array(),
                    RCView::table(array('cellspacing'=>0, 'style'=>'width:100%;table-layout:fixed; border-bottom:1px solid #ddd;'),
                        RCView::tr(array(),
                            RCView::td(array('valign'=>'top', 'colspan' => 2, 'style'=>'width:360px;padding-right:10px; border-right:1px solid #ddd;'),
                                RCView::b($lang['mycap_mobile_app_570']) .
                                RCView::ol(array('style'=>'margin:1px 0;'),
                                    RCView::li(array(), $lang['mycap_mobile_app_564']) .
                                    RCView::li(array(), $lang['mycap_mobile_app_565']) .
                                    RCView::li(array(), $lang['mycap_mobile_app_566'])
                                )
                            ) .
                            RCView::td(array('valign'=>'top', 'colspan' => 2, 'style'=>'width:360px;padding-left:10px;'),
                                RCView::b($lang['mycap_mobile_app_571']) .
                                RCView::br() .
                                RCView::span(array('style'=>''),
                                    $lang['mycap_mobile_app_572']
                                ) .
                                RCView::ol(array('style'=>'margin:1px 0;'),
                                    RCView::li(array(), $lang['mycap_mobile_app_567']) .
                                    RCView::li(array(), $lang['mycap_mobile_app_568']) .
                                    RCView::li(array(), $lang['mycap_mobile_app_569'])
                                )
                            )
                        )
                    )
                ) .
                (\UserRights::isSuperUserNotImpersonator() ? "" : RCView::div(array('class' => 'yellow mt-4 mb-3'), "<i class=\"fa-solid fa-circle-exclamation\"></i> ".$lang['mycap_mobile_app_643'])).
                RCView::div(array('style' => 'font-weight: bold; padding-top: 10px;'), "<i class='fas fa-list-alt'></i> ".$lang['mycap_mobile_app_652']." ".
                    RCView::a(array('href'=>'javascript:;', 'id' => 'div_initial_setup_instr_show_link', 'style'=>'color:blue;'),
                            '[<i class="fas fa-plus"></i> ' . $lang['rights_432']."]"
                        )
                ).
                RCView::fieldset(array('style'=>'display:none;border:1px solid #ddd;', 'id' => 'div_initial_setup_instr'),
                    RCView::legend(array('id' => 'div_initial_setup_instr_hide_link', 'style'=>'margin-left: 10px;font-weight: bold; '),
                        RCView::a(array('href'=>'javascript:;', 'id' => 'div_initial_setup_instr_hide_link', 'style'=>'color:blue;'),
                            ' [' . $lang['rights_433']."]"
                        )).
                        RCView::div(array('style' => 'padding-left: 15px; padding-right: 5px;'), $instructionsText)) .
                RCView::table(array('cellspacing'=>0, 'style'=>'width:100%;table-layout:fixed;'),
                    RCView::tr(array(),
                        RCView::td(array('valign'=>'top', 'colspan' => 2, 'style'=>'width:360px;padding-right:20px;'),
                            RCView::fieldset(array('id'=>'export_whole_project_fieldset', 'style'=>'margin:15px 0;padding-left:8px;border:1px solid #ddd;background-color:#f9f9f9;'),
                                RCView::legend(array('style'=>'padding:0 3px;margin-left:15px;color:#800000;font-weight:bold;font-size:15px;'),
                                    $lang['mycap_mobile_app_580']
                                ) .
                                RCView::div(array('style'=>'padding:15px 5px 15px 25px;'),
                                    // Explanation
                                    RCView::div(array('style'=>'float:left;'),
                                        RCView::img(array('src'=>'mycap_logo_black.png', 'style'=>'vertical-align:middle;')).
                                        '<i style="vertical-align: middle; padding: 10px;" class="fas fa-long-arrow-alt-right fa-5x"></i>'.
                                        RCView::img(array('src'=>'odm_redcap.gif', 'style'=>'vertical-align:middle;'))
                                    ) .
                                    RCView::div(array('style'=>'float:left;margin-left:15px;max-width:340px;line-height:14px;'),
                                        RCView::span(array('style'=>'font-weight:bold;font-size:13px;'),
                                            $lang['mycap_mobile_app_581']
                                        ) .
                                        RCView::br() .
                                        RCView::br() .
                                        RCView::span(array('style'=>''), $lang['mycap_mobile_app_582'])
                                    )
                                )
                            )
                        )
                    ) .
                    RCView::tr(array(),
                        RCView::td(array('valign'=>'top', 'colspan' => 2, 'style'=>'width:360px;padding-right:20px;'),
                            RCView::span(array('style'=>'color:#C00000;'), $lang['mycap_mobile_app_583'])
                        )
                    ) .
                    RCView::tr(array(),
                        RCView::td(array('valign'=>'top', 'colspan' => 2, 'style'=>'width:360px;padding-right:20px;'),
                            RCView::span(array('style'=>''), $lang['mycap_mobile_app_584'])
                        )
                    ) .
                    // Display Stats
                    RCView::tr(array(),
                        $taskDetails.
                        $otherDetails
                    )
                );
        }
        return $output;
    }

    /**
     * Execute MyCap to REDCap migration SQL or return statistics
     *
     * @param boolean $proceed
     * @param int $project_id
     *
     * @return array
     */
    public static function migrateMyCap($proceed = false, $project_id = null) {
        if (is_null($project_id)) {
            $project_id = PROJECT_ID;
            global $Proj;
        } else {
            $Proj = new Project($project_id);
        }

        // Get current modules enabled in project
        $enabledModules = ExternalModules::getEnabledModules($project_id);
        if (!isset($enabledModules['mycap'])) {
            // Module is not enabled, so nothing to do
            exit("ERROR: The MyCap module is not enabled for this project.");
        }
        $metaDataProjectSettings = self::getMetaDataProjectSettings($project_id);

        $hMacKey = $metaDataProjectSettings['hMacKey'];
        $metaDataPid = $metaDataProjectSettings['metaDataPid'];

        foreach ($metaDataProjectSettings['data'] as $metaDataProjRecord => $arr) {
            foreach ($arr as $event => $key) {
                $stuCode = $arr[$event]['stu_code'];
                $stuName = $arr[$event]['stu_name'];
                $stuImages = $arr[$event]['stu_images'];
                $stuLogo = $arr[$event]['stu_logo'];
                $stuConfig = $arr[$event]['stu_config'];
            }
        }

        // Insert Participants data
        $dictionary = \REDCap::getDataDictionary($project_id, 'array');

        foreach ($dictionary as $field => $fieldAttr) {
            if (strpos($fieldAttr['field_annotation'], Annotation::PARTICIPANT_CODE) !== false) {
                $par_code_field = $field;
            }
            if (strpos($fieldAttr['field_annotation'], Annotation::PARTICIPANT_JOINDATE) !== false) {
                $par_joindate_field = $field;
            }
            if (strpos($fieldAttr['field_annotation'], Annotation::PARTICIPANT_PUSHNOTIFICATIONIDS) !== false) {
                $par_pushid_field = $field;
            }
            if (strpos($fieldAttr['field_annotation'], Annotation::PARTICIPANT_ZERODATE) !== false) {
                $par_baselinedate_field = $field;
            }
            if (strpos($fieldAttr['field_annotation'], Annotation::PARTICIPANT_FIRSTNAME) !== false) {
                $firstname_field = $field;
            }
            if (strpos($fieldAttr['field_annotation'], Annotation::PARTICIPANT_LASTNAME) !== false) {
                $lastname_field = $field;
            }
        }

        if ($firstname_field != '') {
            $par_identifier_field = "[".$firstname_field."]";
        }
        if ($lastname_field != '') {
            $par_identifier_field .= " [".$lastname_field."]";
        }

        $errors = array();
        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        $emConfig = json_decode($stuConfig, true);
        $projectTitle = $Proj->project['app_title'];
        if ($hMacKey != '' && $proceed == true) {
            if (isset($emConfig['tasks']['instructionStep']['content'])) {
                $emConfig['tasks']['instructionStep']['content'] = str_replace(PHP_EOL, '\r\n', $emConfig['tasks']['instructionStep']['content']);
            }
            if (isset($emConfig['tasks']['completionStep']['content'])) {
                $emConfig['tasks']['completionStep']['content'] = str_replace(PHP_EOL, '\r\n', $emConfig['tasks']['completionStep']['content']);
            }

            if (isset($emConfig['contacts']['notes'])) {
                $emConfig['contacts']['notes'] = str_replace(PHP_EOL, '\r\n', $emConfig['contacts']['notes']);
            }

            if (isset($emConfig['zeroDateTask']['instructionStep']['content'])) {
                $emConfig['zeroDateTask']['instructionStep']['content'] = str_replace(PHP_EOL, '\r\n', $emConfig['zeroDateTask']['instructionStep']['content']);
            }
            $zeroDateTask = json_encode($emConfig['zeroDateTask']);

            // Project is enabled for MyCap first time
            $sql = "INSERT INTO redcap_mycap_projects (code, hmac_key, project_id, `name`, config, status, baseline_date_field, baseline_date_config, participant_custom_label)
                        VALUES ('".$stuCode."',
                            '".$hMacKey."',
                            '".$project_id."',
                            '".db_escape($projectTitle)."',
                            '".db_escape($stuConfig)."',
                            '1',
                            '".$par_baselinedate_field."',
                            '".db_escape($zeroDateTask)."',
                            '".$par_identifier_field."');";
            if (!db_query($sql))  $errors[] = $sql;

            $sql = "UPDATE redcap_projects SET mycap_enabled = '1' WHERE project_id = $project_id";
            if (!db_query($sql))  $errors[] = $sql;
        }

        $projectRecords = \Records::getData(
            $project_id,
            'array',
            array()
        );
        $parCodes = array();
        $parCount = 0;
        foreach ($projectRecords as $record => $arr) {
            // Save Participants
            foreach ($arr as $event => $fieldsArr) {
                foreach ($fieldsArr as $field => $value) {
                    if ($field == $par_code_field) {
                        if (empty($value)) {
                            $code = Participant::generateUniqueCode($project_id);
                        } else {
                            $code = $value;
                        }
                        $parCodes[] = $code;
                    }
                    if ($field == $par_joindate_field) {
                        $install_date = $value;
                    }
                    if ($field == $par_pushid_field) {
                        $pushids = $value;
                    }
                }
            }
            $parCount++;
            if ($proceed == true) {
                // Insert participants for this project
                $sql = "INSERT INTO redcap_mycap_participants (code, project_id, record, join_date, push_notification_ids) VALUES
                            ('".db_escape($code)."', ".$project_id.", '".db_escape($record)."', ".checkNull($install_date).", '".db_escape($pushids)."');";
                if (!db_query($sql))  $errors[] = $sql;
            }
        }
        $MetaDataProj = new Project($metaDataPid);
        $data = \REDCap::getData(
            $metaDataPid,
            'array',
            array($metaDataProjRecord)
        );
        $msgArr = array();
        foreach ($data as $record=>$event_data) {
            foreach ($event_data as $event_id => $arr) {
                if ($event_id == 'repeat_instances') {
                    $eventNormalized = $event_data['repeat_instances'];
                    foreach ($eventNormalized[$MetaDataProj->firstEventId]['images'] as $key => $image) {
                        $imagesArr[$image['img_name']] = $image['img_file'];
                    }
                    foreach ($eventNormalized[$MetaDataProj->firstEventId]['messages'] as $key => $message) {
                        $msgArr[$key]['uuid'] = $message['msg_id'];
                        $msgArr[$key]['sent_date'] = $message['msg_sentdate'];
                        $msgArr[$key]['received_date'] = $message['msg_receiveddate'];
                        $msgArr[$key]['read_date'] = $message['msg_readdate'];
                        $msgArr[$key]['type'] = $message['msg_type'];
                        $msgArr[$key]['from'] = $message['msg_from'];
                        $msgArr[$key]['to'] = $message['msg_to'];
                        $msgArr[$key]['body'] = $message['msg_body'];
                        $msgArr[$key]['title'] = $message['msg_title'];
                        $msgArr[$key]['processed'] = $message['messages_complete'];
                    }
                }
            }
        }

        // Insert messages data
        $annCount = $inboxCount = $outboxCount = 0;
        if (!empty($msgArr)) {
            foreach ($msgArr as $msg) {
                $msg['project_id'] = $project_id;
                $msg['from_server'] = (in_array($msg['from'], $parCodes)) ? 0 : 1;
                if ($msg['type'] == Message::ANNOUNCEMENT) {
                    $annCount++;
                    $msg['to'] = '';
                } else {
                    if ($msg['from_server'] == 1) $outboxCount++;
                    else $inboxCount++;
                }
                if ($proceed == true) {
                    //$msg['to'] = (in_array($msg['to'], $parCodes)) ? $msg['to'] : '';
                    $msg['sent_date'] = ($msg['sent_date'] != '') ? date('Y-m-d H:i:s', $msg['sent_date']) : NULL;
                    $msg['received_date'] = ($msg['received_date'] != '') ? date('Y-m-d H:i:s', $msg['received_date']) : NULL;
                    $msg['read_date'] = ($msg['read_date'] != '') ? date('Y-m-d H:i:s', $msg['read_date']) : NULL;
                    // Insert
                    $sql = "INSERT INTO redcap_mycap_messages (uuid, project_id, `type`, from_server, `from`, `to`, body, sent_date, received_date, read_date, processed) VALUES
                            ('".$msg['uuid']."', '".$msg['project_id']."', '".$msg['type']."', '".$msg['from_server']."', '".$msg['from']."', '".$msg['to']."', '".db_escape($msg['body'])."', ".checkNull($msg['sent_date']).", ".checkNull($msg['received_date']).", ".checkNull($msg['read_date']).", '".$msg['processed']."');";
                    if (!db_query($sql))  $errors[] = $sql;
                }
            }
        }

        // Fetch Pages data
        $pagesArr = $emConfig['pages'];
        $pagesCount = (is_null($pagesArr)) ? 0 : count($pagesArr);
        if ($pagesCount > 0 && $proceed == true) {
            foreach ($pagesArr as $page) {
                if ($page['imageType'] == Page::IMAGETYPE_CUSTOM) {
                    $edoc_id = $imagesArr[$page['imageName']];
                    // Copy file on server
                    $imgLogo = copyFile($edoc_id, $project_id);
                } else {
                    if ($page['subType'] == Page::SUBTYPE_HOME) {
                        $imgLogo = Page::uploadSystemImageFile($project_id, $page['imageName']);
                        $page['imageType'] = Page::IMAGETYPE_CUSTOM;
                        $page['imageName'] = '';
                    } else {
                        $imgLogo = 0;
                    }
                }
                // About pages are inserted for project
                $sql = "INSERT INTO redcap_mycap_aboutpages (project_id, identifier, page_title, page_content, sub_type, image_type, system_image_name, custom_logo, page_order) 
				                VALUES (".$project_id.", '".db_escape($page['identifier'])."', '".db_escape($page['title'])."', '".db_escape($page['content'])."', '".db_escape($page['subType'])."', '".db_escape($page['imageType'])."', '".db_escape($page['imageName'])."', '".db_escape($imgLogo)."', '".db_escape($page['sortOrder'])."');";
                if (!db_query($sql))  $errors[] = $sql;
            }
        }

        $contactsArr = $emConfig['contacts'];
        $contactsCount = (is_null($contactsArr)) ? 0 : count($contactsArr);
        if ($contactsCount > 0 && $proceed == true) {
            foreach ($contactsArr as $contact) {
                if (isset($contact['notes'])) {
                    $contact['notes'] = str_replace(PHP_EOL, '\r\n', $contact['notes']);
                }
                // Contacts are inserted for project
                $sql = "INSERT INTO redcap_mycap_contacts (project_id , identifier, contact_header, contact_title, phone_number, email, website, additional_info, contact_order) 
                        VALUES (".$project_id.", '".db_escape($contact['identifier'])."', '".db_escape($contact['name'])."', '".db_escape($contact['title'])."', '".db_escape($contact['phone'])."', '".db_escape($contact['email'])."', '".db_escape($contact['website'])."', '".db_escape($contact['notes'])."', '".db_escape($contact['sortOrder'])."');";
                if (!db_query($sql))  $errors[] = $sql;
            }
        }

        $linksArr = $emConfig['links'];
        $linksCount = (is_null($linksArr)) ? 0 : count($linksArr);
        if ($linksCount > 0 && $proceed == true) {
            foreach ($linksArr as $link) {
                $appendProjCode = ($link['appendProjectCode'] == 1) ? $link['appendProjectCode'] : 0;
                $appendParCode = ($link['appendParticipantCode'] == 1) ? $link['appendParticipantCode'] : 0;
                // Links are inserted for project
                $sql = "INSERT INTO redcap_mycap_links (project_id , identifier, link_name, link_url, link_icon, append_project_code, append_participant_code, link_order) 
				            VALUES (".$project_id.", '".db_escape($link['identifier'])."', '".db_escape($link['name'])."', '".db_escape($link['url'])."', '".db_escape($link['imageName'])."', '".$appendProjCode."', '".$appendParCode."', '".db_escape($link['sortOrder'])."');";
                if (!db_query($sql))  $errors[] = $sql;
            }
        }

        $themeArr = $emConfig['theme'];
        if (!empty($themeArr) > 0) {
            $themeDetails = $themeArr['type']." ".$themeArr['systemType'];
            // Theme is inserted for project
            $sql = "INSERT INTO redcap_mycap_themes (project_id , primary_color, light_primary_color, accent_color, dark_primary_color, light_bg_color, theme_type, system_type) 
				        VALUES (".$project_id.", '".db_escape($themeArr['primaryColor'])."', '".db_escape($themeArr['lightPrimaryColor'])."', '".db_escape($themeArr['accentColor'])."', '".db_escape($themeArr['darkPrimaryColor'])."', '".db_escape($themeArr['lightBackgroundColor'])."', '".db_escape($themeArr['type'])."', '".db_escape($themeArr['systemType'])."');";
            if (!db_query($sql))  $errors[] = $sql;
        }

        $tasksArr = $emConfig['tasks'];
        $taskTitles = array();
        $tasksCount = (is_null($tasksArr)) ? 0 : count($tasksArr);
        $enabledTasksCount = 0;
        $updateConfig = false;
        if ($tasksCount > 0) {
            foreach ($tasksArr as $task) {
                if ($task['enabled'] == true) { // Ignore/do not save inadequately enabled task in EM to DB
                    $enabledTasksCount++;
                    $task_title = ($task['title'] == '') ? $Proj->forms[$task['identifier']]['menu'] : $task['title'];
                    $taskTitles[] = $task_title;

                    if ($proceed == true) {
                        if (is_array($task['card']['dateLineCard']) && !empty($task['card']['dateLineCard'])) {
                            $x_date_field = $task['card']['dateLineCard']['xDateField'];
                            $x_time_field = $task['card']['dateLineCard']['xTimeField'];
                            $y_numeric_field = $task['card']['dateLineCard']['yNumericField'];
                        } else {
                            $x_date_field = $x_time_field = $y_numeric_field = '';
                        }

                        if (is_array($task['instructionStep']) && !empty($task['instructionStep'])) {
                            $includeInstructionStep = 1;
                            $instructionStepTitle = $task['instructionStep']['title'];
                            $instructionStepContent = $task['instructionStep']['content'];
                        } else {
                            $includeInstructionStep = 0;
                            $instructionStepTitle = $instructionStepContent = '';
                        }

                        if (is_array($task['completionStep']) && !empty($task['completionStep'])) {
                            $includeCompletionStep = 1;
                            $completionStepTitle = $task['completionStep']['title'];
                            $completionStepContent = $task['completionStep']['content'];
                        } else {
                            $includeCompletionStep = 0;
                            $completionStepTitle = $completionStepContent = '';
                        }
                        if ($task['schedule']['frequency'] == Task::FREQ_DAILY) {
                            $schedule_interval_week = $schedule_interval_month = '';
                        } elseif ($task['schedule']['frequency'] == Task::FREQ_WEEKLY) {
                            $schedule_interval_month = '';
                            $schedule_interval_week = $task['schedule']['interval'];
                        } elseif ($task['schedule']['frequency'] == Task::FREQ_MONTHLY) {
                            $schedule_interval_month = $task['schedule']['interval'];
                            $schedule_interval_week = '';
                        }
                        $allowsRetro = ($task['allowsRetroactiveCompletion'] == 1) ? 1 : 0;
                        $allowsSaving = ($task['allowsSaving'] == 1) ? 1 : 0;
                        $task['enabled'] = (isset($task['enabled']) && $task['enabled'] == 1) ? 1 : 0;

                        // Set "allow retroactive completion" to off if task is scheduled infinite times
                        if ($task['schedule']['type'] == Task::TYPE_INFINITE) $allowsRetro = 0;
                        // Tasks are inserted for project
                        $sql = "INSERT INTO redcap_mycap_tasks (project_id, form_name, enabled_for_mycap, task_title, question_format,
                                    card_display, x_date_field, x_time_field, y_numeric_field,
                                    allow_retro_completion, allow_save_complete_later, include_instruction_step, include_completion_step, instruction_step_title,
                                    instruction_step_content, completion_step_title, completion_step_content, schedule_relative_to, schedule_type, schedule_frequency,
                                    schedule_interval_week, schedule_days_of_the_week, schedule_interval_month, schedule_days_of_the_month, schedule_days_fixed, schedule_relative_offset,
                                    schedule_ends, schedule_end_count, schedule_end_after_days, schedule_end_date, extended_config_json) 
                                VALUES
                                    ($project_id, " . checkNull($task['identifier']) . ", {$task['enabled']}, " . checkNull($task_title) . ", " . checkNull($task['format']) . ",
                                    " . checkNull($task['card']['type']) . ", " . checkNull($x_date_field) . "," . checkNull($x_time_field) . "," . checkNull($y_numeric_field) . ",
                                    {$allowsRetro}, {$allowsSaving}, {$includeInstructionStep}, {$includeCompletionStep},
                                    " . checkNull($instructionStepTitle) . ", " . checkNull($instructionStepContent) . ", " . checkNull($completionStepTitle) . ", 
                                    " . checkNull($completionStepContent) . ", " . checkNull($task['schedule']['relativeTo']) . ", " . checkNull($task['schedule']['type']) . ", 
                                    " . checkNull($task['schedule']['frequency']) . ", " . checkNull($schedule_interval_week) . ", " . checkNull($task['schedule']['daysOfTheWeek']) . ", 
                                    " . checkNull($schedule_interval_month) . ", " . checkNull($task['schedule']['daysOfTheMonth']) . ", " . checkNull($task['schedule']['daysFixed']) . ", 
                                    " . checkNull($task['schedule']['relativeOffset']) . ", " . checkNull($task['schedule']['ends']) . ", " . checkNull($task['schedule']['endCount']) . ", 
                                    " . checkNull($task['schedule']['endAfterDays']) . ", " . checkNull($task['schedule']['endDate']) . ", " . checkNull($task['extendedConfigJson']) . ");";
                        if (!db_query($sql)) $errors[] = $sql;
                        if (!$Proj->isRepeatingForm($Proj->firstEventId, $task['identifier'])) {
                            // Make this form as repeatable with default eventId as project is classic
                            $sql = "INSERT INTO redcap_events_repeat (event_id, form_name) 
                                            VALUES ({$Proj->firstEventId}, '" . db_escape($task['identifier']) . "');";
                            if (!db_query($sql)) $errors[] = $sql;
                        }
                    }
                } else {
                    $updateConfig = true;
                }
            }
        }

        if ($proceed == false) {
            $sql = "SELECT COUNT(*) AS total FROM redcap_mycap_syncissues WHERE project_code = '".$stuCode."'";
            $q = db_query($sql);
            $syncIssuesCount = db_result($q, 0, 'total');
            // Return Statistics array
            return array(
                'parCount' => $parCount,
                'annCount' => $annCount,
                'inboxCount' => $inboxCount,
                'outboxCount' => $outboxCount,
                'tasksCount' => $enabledTasksCount,
                'tasksTitles' => $taskTitles,
                'pagesCount' => $pagesCount,
                'contactsCount' => $contactsCount,
                'linksCount' => $linksCount,
                'themeDetails' => $themeDetails,
                'syncIssuesCount' => $syncIssuesCount
            );
        } else {
            // Any errors?
            if (empty($errors)) {
                // Disable the MyCap module (we have to do this manually via SQL since on admins can do it normally via EM methods)
                ExternalModules::setProjectSetting('mycap', $project_id, ExternalModules::KEY_ENABLED, false);
                $logText = "Disable external module MyCap for project";
                \REDCap::logEvent($logText);

                // Success
                db_query("COMMIT");
                db_query("SET AUTOCOMMIT=1");

                // Fix About pages images issue
                Page::fixAboutImages($project_id);

                Page::createAboutImagesZip($project_id);

                // Convert all QR code images from EM format to REDCap core format
                self::updateParticipantAccessHTML($project_id, $par_code_field);

                // Update config to generated config JSON to remove inadequately enabled tasks
                if ($updateConfig == true) {
                    // Generate Project Config
                    $myCapProj = new MyCap($project_id);
                    $projectConfig = $myCapProj->generateProjectConfigJSON($project_id);
                    $sql_config = "UPDATE redcap_mycap_projects SET config = '".db_escape($projectConfig)."' WHERE project_id = $project_id";
                    // Update redcap_mycap_projects to set json config
                    db_query($sql_config);
                }

                // Set Use longitudinal data collection to disabled if it's enabled at project
                global $repeatforms, $lang;
                if ($repeatforms) {
                    $sql = "UPDATE redcap_projects SET repeatforms = '0' WHERE project_id = '".$project_id."'";
                    db_query($sql);
                }

                // Translate MyCap config rights into new mycap_particiapnts rights - Get the global config permission that dictates who can use/config MyCap in a project
                $sql = "select count(*) from redcap_external_module_settings s, redcap_external_modules m
                        where s.external_module_id = m.external_module_id and s.project_id is null 
                        and s.`key` = 'config-require-user-permission' and m.directory_prefix = 'mycap' and s.`value` = 'true'";
                $q = db_query($sql);
                $designRightsNeededToConfigMyCap = (db_result($q, 0) == '0');
                if ($designRightsNeededToConfigMyCap) {
                    // Update all users and roles to have mycap_participants rights if they have design rights
                    $sql = "update redcap_user_rights set mycap_participants = 1 where design = 1 and project_id = ".$project_id;
                    $q = db_query($sql);
                    $sql = "update redcap_user_roles set mycap_participants = 1 where design = 1 and project_id = ".$project_id;
                    $q = db_query($sql);
                } else {
                    // Update all users and roles to have mycap_participants rights if they have explicitly been given rights to configure mycap
                    $rights = \UserRights::getPrivileges($project_id);
                    foreach ($rights[$project_id] as $username=>$these_rights) {
                        if (isset($these_rights['external_module_config']) && is_array($these_rights['external_module_config']) && in_array('mycap', $these_rights['external_module_config'])) {
                            if ($these_rights['role_id'] == null) {
                                // User not in a role
                                $sql = "update redcap_user_rights set mycap_participants = 1 where username = '".db_escape($username)."' and project_id = ".$project_id;
                            } else {
                                // User in a role
                                $sql = "update redcap_user_roles set mycap_participants = 1 where role_id = '".db_escape($these_rights['role_id'])."' and project_id = ".$project_id;
                            }
                            $q = db_query($sql);
                        }
                    }
                }

                // Logging
                \Logging::logEvent($sql,"redcap_mycap_projects","MANAGE",$project_id,"project_id = $project_id", "Migrate MyCap External Module data to REDCap");
                $msg = $lang['mycap_mobile_app_540'];
                $success = true;
            } else {
                // Failed: Display error message and roll back
                db_query("ROLLBACK");
                db_query("SET AUTOCOMMIT=1");
                $adminMsg = "";
                if (SUPER_USER) {
                    $adminMsg = "Administrator Message - The following SQL queries failed:<br> - ".implode("<br> - ", $errors);
                }
                // Return msg
                $msg = "<div class='red'><i class=\"fas fa-times\"></i> <b>ERROR: An error occurred during the migration of MyCap External Module into REDCap. No conversions were made.</b> $adminMsg</div>";
                $success = false;
            }
        }
        return array('success' => $success, 'message' => $msg);
    }

    /**
     * Get MyCap Metadata Projects settings
     *
     * @param int $project_id
     *
     * @return array
     */
    public static function getMetaDataProjectSettings($project_id = null) {
        if (is_null($project_id)) {
            $project_id = PROJECT_ID;
        }
        
        $metaDataPid = $hMacKey = null;

        $sql2 = "SELECT s.project_id, `key`, `value` 
                FROM redcap_external_modules e, redcap_external_module_settings s 
                WHERE e.directory_prefix = 'mycap' 
                    AND (s.key = 'con_hmackey' OR s.key='con_metadataprojectid')  
                    AND e.external_module_id = s.external_module_id 
                    AND project_id IS NULL";

        $q = db_query($sql2);
        while ($row = db_fetch_assoc($q)) {
            if ($row['key'] == 'con_hmackey') {
                $hMacKey = $row['value'];
            } else if ($row['key'] == 'con_metadataprojectid') {
                $metaDataPid = $row['value'];
            }
        }
        if ($metaDataPid > 0) {
            $data = \Records::getData($metaDataPid, 'array', array(), array('stu_code', 'stu_name', 'stu_images', 'stu_logo', 'stu_config'), array(), array(), false, false, false, '[stu_datapid] = ' . $project_id);
        } else {
            $data = [];
        }
        $metaDataProjectSettings = array('hMacKey' => $hMacKey,
            'data' => $data,
            'metaDataPid' => $metaDataPid);
        return $metaDataProjectSettings;
    }

    /**
     * Check if MyCap was enabled in past and setting exists in db
     *
     * @param int $project_id
     *
     * @return boolean
     */
    public static function isMyCapSetupExists($project_id = null) {
        if (is_null($project_id)) {
            $project_id = PROJECT_ID;
        }
        // Get count of projects utilizing mycap
        $sql = "SELECT COUNT(*) as mycap_project FROM redcap_mycap_projects WHERE project_id = '".$project_id."'";
        $q = db_query($sql);
        return (db_result($q, 0, 'mycap_project') > 0);
    }

    /**
     * Return MyCap Setup Instructions Text
     *
     * @return string
     */
    public static function getMyCapSetupInstructions() {
        global $lang;
        // User Rights
        $text = RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_641']) .
                RCView::div(array('style'=>''), $lang['mycap_mobile_app_642']);
        // Design your instruments in the Online Designer
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:5px 0 2px; padding-top: 10px;'), $lang['mycap_mobile_app_546']) .
                RCView::div(array('style'=>''), $lang['mycap_mobile_app_547']) .
                RCView::div(array('style'=>''), $lang['mycap_mobile_app_762']);
        // Enable the use of a baseline date, if needed.
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:5px 0 2px; padding-top: 10px;'), $lang['mycap_mobile_app_644']) .
            RCView::div(array('style'=>''), $lang['mycap_mobile_app_696'].$lang['mycap_mobile_app_645']);
        // Enable your instruments for MyCap in the Online Designer
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_548']) .
                RCView::div(array('style'=>''), $lang['mycap_mobile_app_549']);
        // Configure the look and feel using the MyCap Mobile App settings
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_880']) .
                RCView::div(array('style'=>''), $lang['mycap_mobile_app_881']);
        $text .= RCView::ul(array(), implode('', array(
                RCView::li(array(), $lang['mycap_mobile_app_545']),
                RCView::li(array(), $lang['mycap_mobile_app_557']),
                RCView::li(array(), $lang['mycap_mobile_app_558']),
                RCView::li(array(), $lang['mycap_mobile_app_559'])
            )));
        // Use the MyCap Mobile App page? to view and manage
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_552']) .
        RCView::ul(array(), implode('', array(
                    RCView::li(array(), $lang['mycap_mobile_app_560']),
                    RCView::li(array(), $lang['mycap_mobile_app_561']),
                    RCView::li(array(), $lang['mycap_mobile_app_562'])
                )));
        // Publish your MyCap Tasks and Design
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_646']) .
                RCView::div(array('style'=>''), $lang['mycap_mobile_app_882']);
        // Test your project on iOS and Android
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_648']) .
                RCView::div(array('style'=>''), $lang['mycap_mobile_app_649']);
        // Publish new MyCap version anytime you make changes.
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_650']) .
                RCView::div(array('style'=>''), $lang['mycap_mobile_app_651']);
        return $text;
    }

    /**
     * MyCap - set baseline settings / participant related settings of project
     *
     * @return void
     */
    public static function setMyCapSettings($settings, $project_id = null) {
        if (is_null($project_id)) {
            $project_id = PROJECT_ID;
        }
        if (!empty($settings)) {
            $sql = "UPDATE redcap_mycap_projects SET allow_new_participants = '".$settings['allow_new_participants']."', participant_custom_field = '".$settings['participant_custom_field']."',
                            participant_custom_label = '".$settings['participant_custom_label']."', participant_allow_condition = '".$settings['participant_allow_condition']."',
                            baseline_date_field = '".$settings['baseline_date_field']."', baseline_date_config = '".db_escape($settings['baseline_date_config'])."',
                            status = '".$settings['status']."', converted_to_flutter = '".$settings['converted_to_flutter']."'
                    WHERE project_id = $project_id";
            db_query($sql);
        }
    }

    /**
     * MyCap - update all alert messages and survey completion texts having participant joining info
     *
     * @param int $project_id
     * @param string $par_code_field
     * @return void
     */
    public static function updateParticipantAccessHTML($project_id, $par_code_field)
    {
        if (is_null($project_id)) {
            $project_id = PROJECT_ID;
            global $Proj;
        } else {
            $Proj = new Project($project_id);
        }
        // Update access html in all alerts
        $alertOb = new \Alerts();
        foreach ($alertOb->getAlertSettings($project_id) as $alert_id => $alert) {
            $alert_message = Participant::translateJoiningInfoImages($alert['alert_message'], $par_code_field);
            if ($alert_message != $alert['alert_message']) {
                $sql = "UPDATE redcap_alerts SET alert_message = ".checkNull($alert_message)." WHERE project_id = ".$project_id." AND alert_id = $alert_id";
                db_query($sql);
            }
        }

        foreach ($Proj->surveys as $this_survey_id => $survey_attr) {
            // Update access html in all surveys - completion texts
            $acknowledgement = Participant::translateJoiningInfoImages($survey_attr['acknowledgement'], $par_code_field);
            if ($acknowledgement != $survey_attr['acknowledgement']) {
                $sql = "UPDATE redcap_surveys SET acknowledgement = ".checkNull($acknowledgement)." WHERE project_id = ".$project_id." AND survey_id = $this_survey_id";
                db_query($sql);
            }

            // Update access html in all surveys - Confirmation Email content text
            $confirmation_email_content = Participant::translateJoiningInfoImages($survey_attr['confirmation_email_content'], $par_code_field);
            if ($confirmation_email_content != $survey_attr['confirmation_email_content']) {
                $sql = "UPDATE redcap_surveys SET confirmation_email_content = ".checkNull($confirmation_email_content)." WHERE project_id = ".$project_id." AND survey_id = $this_survey_id";
                db_query($sql);
            }

            // Update access html in all surveys - Survey Scheduler Email content text
            $email_contents  = array();
            $sql = "SELECT ss_id, email_content FROM redcap_surveys_scheduler WHERE survey_id = '".$this_survey_id."'";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q))
            {
                $email_contents[$row['ss_id']] = $row['email_content'];
            }

            if (count($email_contents) > 0) {
                foreach ($email_contents as $ssId => $emailContent) {
                    $newEmailContent = Participant::translateJoiningInfoImages($emailContent, $par_code_field);
                    if ($newEmailContent != $emailContent) {
                        $sql = "UPDATE redcap_surveys_scheduler SET email_content = ".checkNull($newEmailContent)." WHERE ss_id = '".$ssId."'";
                        db_query($sql);
                    }
                }
            }
        }
    }

    /**
     * Return MyCap Basic "About" Instructions Text
     *
     * @return string
     */
    public static function getMyCapAboutInstructions()
    {
        global $lang;
		$leftsideWidth = "500px";
        return
            RCView::div(array('class' => 'clearfix mx-2'),
                RCView::div(array('class' => 'float-start pt-2 pe-4', 'style' => "width:$leftsideWidth;"),
                    RCView::img(['src' => 'mycap_screen1.png', 'class' => 'ms-4 me-5', 'style' => 'height:380px;']) .
                    RCView::img(['src' => 'mycap_screen2.png', 'style' => 'height:360px;']) .
					RCView::div(array('class' => 'mt-4 py-2 px-3', 'style'=>'background-color:#f5f5f5;border:1px solid #ddd;'),
						RCView::div(array('class' => 'mb-3'),
							RCView::span(['class' => 'text-dangerrc boldish'], "<img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:24px;position:relative;top:-2px;margin-right:5px;'>" . $lang['mycap_mobile_app_671']) .
							RCView::ul(['class' => 'mb-0'],
								RCView::li(['class' => 'mt-1'], RCView::a(['href'=>'javascript:;', 'onclick'=>"popupvid('mycap_01.mp4','".RCView::tt_js('mycap_mobile_app_672')."');", 'target' => '_blank', 'style' => 'text-decoration:underline'], '<i class="fa-solid fa-film me-1"></i>' . $lang['mycap_mobile_app_672'])) .
								RCView::li(['class' => 'mt-1'], RCView::a(['href'=>'javascript:;', 'onclick'=>"popupvid('mycap_02.mp4','".RCView::tt_js('mycap_mobile_app_876')."');", 'target' => '_blank', 'style' => 'text-decoration:underline'], '<i class="fa-solid fa-film me-1"></i>' . $lang['mycap_mobile_app_876'])) .
								RCView::li(['class' => 'mt-1'], RCView::a(['href'=>'javascript:;', 'onclick'=>"popupvid('mycap_03.mp4','".RCView::tt_js('mycap_mobile_app_628')."');", 'target' => '_blank', 'style' => 'text-decoration:underline'], '<i class="fa-solid fa-film me-1"></i>' . $lang['mycap_mobile_app_628']))
							)
						) .
						RCView::div(array('class' => ''),
							'<i class="fa-solid fa-mobile-screen-button fs13"></i> ' . RCView::span(['class'=>'boldish'], $lang['mycap_mobile_app_673']) . " " . $lang['mycap_mobile_app_674'] . RCView::br() .
							RCView::a(['href' => 'https://redcap.link/mycapdemo2', 'target' => '_blank', 'style' => 'text-decoration:underline'], $lang['mycap_mobile_app_675'])
						)
					)
                ) .
                RCView::div(array('style' => "margin-left:$leftsideWidth;"),
                    RCView::div(array('class' => 'mb-3'),
                        $lang['mycap_mobile_app_553']
                    ) .
                    RCView::div(array('class' => 'mb-3'),
                        $lang['mycap_mobile_app_563']
                    ) .
                    RCView::div(array('class' => 'mb-4'),
                        '<i class="fa-solid fa-circle-info"></i> ' . $lang['mycap_mobile_app_669']
                    ) .
                    RCView::div(array('class' => 'mb-4 fs14 py-2 px-3', 'style'=>'border:1px solid #eee;'),
                        RCView::div(array('class' => 'boldish'), $lang['mycap_mobile_app_686']) .
                        RCView::div(array('class' => 'boldish'),
                            $lang['mycap_mobile_app_687'] . " " .
                            RCView::a(['href' => APP_PATH_WEBROOT."Resources/misc/mycap_help.pdf", 'target' => '_blank', 'class'=>'text-dangerrc fs14', 'style' => 'text-decoration:underline'],
                                $lang['mycap_mobile_app_688']
                            )
                        ) .
                        RCView::div(array('class'=>'mt-2'),
                            $lang['mycap_mobile_app_766'] . " " .
                            RCView::a(['href' => APP_PATH_WEBROOT."Resources/misc/mycap_features.pdf", 'target' => '_blank', 'class'=>'text-dangerrc fs14', 'style' => 'text-decoration:underline'],
                                $lang['mycap_mobile_app_767']
                            )
                        )
                    ) .
					RCView::div(array('class' => 'text-secondary fs12', 'style'=>'line-height:1.3;'),
						RCView::b($lang['mycap_mobile_app_684']) . " " . $lang['mycap_mobile_app_685']
					)
                )
            );
    }

    /**
     * Return MyCap Migration Instructions Text
     *
     * @return string
     */
    public static function getMyCapMigrationInstructions() {
        global $lang;
        $text = RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_653']);
        // User Rights
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_654']) .
            RCView::div(array('style'=>''), $lang['mycap_mobile_app_642']);
        // Design your instruments in the Online Designer
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:5px 0 2px; padding-top: 10px;'), $lang['mycap_mobile_app_655']) .
            RCView::div(array('style'=>''), $lang['mycap_mobile_app_656']);
        // Enable the use of a baseline date, if needed.
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:5px 0 2px; padding-top: 10px;'), $lang['mycap_mobile_app_657']) .
            RCView::div(array('style'=>''), $lang['mycap_mobile_app_658']);
        // Enable your instruments for MyCap in the Online Designer
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_659']) .
            RCView::div(array('style'=>''), $lang['mycap_mobile_app_660']." ".$lang['mycap_mobile_app_697'].$lang['mycap_mobile_app_645']);
        // Configure the look and feel using the MyCap Mobile App settings
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_661']) .
            RCView::div(array('style'=>''), $lang['mycap_mobile_app_662']." ".$lang['mycap_mobile_app_551']);
        $text .= RCView::ul(array(), implode('', array(
            RCView::li(array(), $lang['mycap_mobile_app_545']),
            RCView::li(array(), $lang['mycap_mobile_app_557']),
            RCView::li(array(), $lang['mycap_mobile_app_558']),
            RCView::li(array(), $lang['mycap_mobile_app_559'])
        )));
        // Use the MyCap Mobile App page? to view and manage
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_552']) .
            RCView::ul(array(), implode('', array(
                RCView::li(array(), $lang['mycap_mobile_app_560']),
                RCView::li(array(), $lang['mycap_mobile_app_561']),
                RCView::li(array(), $lang['mycap_mobile_app_562'])
            )));
        // Publish your MyCap Tasks and Design
        $text .= RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin:15px 0 2px;'), $lang['mycap_mobile_app_663']) .
            RCView::div(array('style'=>''), $lang['mycap_mobile_app_664']);
        return $text;
    }

    /**
     * Return the current MyCap configuration version number
     *
     * @return integer
     */
    public function getConfigVersion()
    {
        $mycapConfig = json_decode($this->config);
        return (!isset($mycapConfig->version) || $mycapConfig->version == '0') ? 0 : (int)$mycapConfig->version;
    }

    /**
     * Output HTML for setup table for project title in MyCap mobile app
     *
     * @return string
     */
    public static function renderEditProjectTitleSetup()
    {
        global $myCapProj, $lang;
        // Build the setup table
        $html = RCView::div(array('class'=>'p', 'style'=>'margin-top:0;'), $lang['mycap_mobile_app_692']) .
            RCView::div(array('class'=>'round chklist', 'style'=>'padding:10px 20px; max-width:800px; margin: 12px 0;'),
                RCView::form(array('id'=>'project_title_setup_form'),
                    RCView::table(array('width'=>'100%;', 'cellpadding'=>'0', 'cellspacing'=>'0'),
                        RCView::tr(array(),
                            RCView::td(array('style'=>'width:225px; font-weight:bold;'),
                                $lang['create_project_01']
                            ) .
                            RCView::td(array('class'=>'nowrap', 'style'=>'padding-bottom:10px'),
                                RCView::text(array('name'=>'project_title', 'id'=>'project_title', 'value'=>str_replace("'", "&#039;", filter_tags(html_entity_decode($myCapProj->project['name'], ENT_QUOTES))), 'style' => 'font-size:15px;width:95%;max-width:500px;', 'class'=>'x-form-text x-form-field'))
                            )
                        )
                    )
                )
            );

        // Output the HTML
        return $html;
    }

    /**
     * Delete All MyCap data :: Participants + messages + sync issues
     *
     * @param integer $project_id     *
     * @return void
     */
    public static function eraseAllData($project_id)
    {
        // Delete all MyCap Data
        $project_code = db_result(db_query("SELECT code FROM redcap_mycap_projects WHERE project_id = $project_id"), 0);
        if ($project_code != "") {
            // Delete Sync issues if any
            db_query("DELETE FROM redcap_mycap_syncissues WHERE project_code = '".$project_code."'");
        }
        // Delete row in redcap_mycap_participants
        db_query("DELETE FROM redcap_mycap_participants WHERE project_id = $project_id");
        // Delete row in redcap_mycap_messages
        db_query("DELETE FROM redcap_mycap_messages WHERE project_id = $project_id");
    }

    /**
     * MyCap - Reset baseline date and join dates of project
     * @param int $new_project_id
     *
     * @return void
     */
    public static function resetBaselineJoinDates($new_project_id = null) {
        $foundInstallDtAnnotation = false;
        $dd_array = \MetaData::getDataDictionary('array', false);
        foreach ($dd_array as $fieldName => $props) {
            if (strpos($props['field_annotation'], Annotation::PARTICIPANT_JOINDATE) !== false) {
                $foundInstallDtAnnotation = true;
                break;
            }
        }

        // Get all fields (with @MC-PARTICIPANT-JOINDATE annotations) values
        if ($foundInstallDtAnnotation)
        {
            $sql = "select distinct d.* from redcap_metadata m, ".\Records::getDataTable($new_project_id)." d where m.project_id = $new_project_id
                    and m.project_id = d.project_id and m.field_name = d.field_name and m.misc LIKE '%" . Annotation::PARTICIPANT_JOINDATE . "%'";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $sql = "UPDATE ".\Records::getDataTable($row['project_id'])." SET value = '' where project_id = {$row['project_id']} and event_id = {$row['event_id']}
                        and record = '" . db_escape($row['record']) . "' and field_name = '{$row['field_name']}'";
                $sql .= " and instance " . ($row['instance'] == '' ? "is NULL" : "= '" . db_escape($row['instance']) . "'");
                db_query($sql);
            }
        }

        // Get baseline date field
        $baseline_date_field = ZeroDateTask::getBaselineDateField($new_project_id);
        if ($baseline_date_field != '')
        {
            $sql = "UPDATE ".\Records::getDataTable($new_project_id)." SET value = '' where project_id = {$new_project_id} and field_name = '{$baseline_date_field}'";
            db_query($sql);
        }
    }

    /**
     * Display Custom Settings form
     *
     * @return string
     */
    public static function displayCustomSetupTable()
    {
        global $myCapProj;
        $event_display_format = $myCapProj->project['event_display_format'];

        ob_start();
        ?>
        <table cellspacing="2" cellpadding="2" border="0" width="100%">
            <tr>
                <td>
                    <i class="fa-regular fa-calendar-minus"></i>
                    <b><u><?php echo RCView::tt('mycap_mobile_app_849') ?></u></b>
                </td>
            </tr>
            <tr><td style="padding-left: 30px;"><?php echo RCView::tt('mycap_mobile_app_848') ?></td></tr>
            <tr>
                <td style="padding-left: 60px;">
                    <input type="radio" name="event_display_format" id="id" <?php echo ($event_display_format == self::EVENT_DISPLAY_ID_FORMAT ? "checked" : "") ?> value="<?php echo self::EVENT_DISPLAY_ID_FORMAT; ?>">
                    <label for="id"><span style="color:#800000; font-weight: bold;"><?php echo RCView::tt('global_243') ?></span> -
                    <span class="newdbsub" style="font-weight:normal;"><i><?php echo RCView::tt('mycap_mobile_app_850') ?></i></span></label>
                </td>
            </tr>
            <tr>
                <td style="padding-left: 60px;">
                    <input type="radio" name="event_display_format" id="label" <?php echo ($event_display_format == self::EVENT_DISPLAY_LABEL_FORMAT ? "checked" : "") ?> value="<?php echo self::EVENT_DISPLAY_LABEL_FORMAT; ?>">
                        <label for="label"><span style="color:#800000; font-weight: bold;"><?php echo RCView::tt('global_242') ?></span> - </label>
                        <span class="newdbsub" style="font-weight:normal;"><i><?php echo RCView::tt('mycap_mobile_app_851') ?></i></span>
                </td>
            </tr>
            <tr>
                <td style="padding-left: 60px;">
                    <input type="radio" name="event_display_format" id="none" <?php echo ($event_display_format == self::EVENT_DISPLAY_NONE_FORMAT ? "checked" : "") ?> value="<?php echo self::EVENT_DISPLAY_NONE_FORMAT; ?>">
                        <label for="none"><span style="color:#800000; font-weight: bold;"><?php echo RCView::tt('global_75') ?></span> -
                            <span class="newdbsub" style="font-weight:normal;"><i><?php echo RCView::tt('mycap_mobile_app_852') ?></i></span></label>
                </td>
            </tr>
        </table>
        <?php
        $html = ob_get_clean();

        // Return all html to display
        return $html;
    }

    /**
     * Update MyCap config JSON version number to version++ and update language list in config upon saving changes from MLM
     *
     * @return bool
     */
    public function updateMLMConfigJSON ($project_id = null) {
        if ($project_id == null) {
            $project_id = PROJECT_ID;
        }
        if ($this->config == "") return false;
        $projectConfig = json_decode($this->config);
        if (!is_object($projectConfig)) return false;
        $currentLanguages = $projectConfig->languages;
        // Fetch updated list of languages
        $projectConfig->languages = MultiLanguage::getMyCapActiveLanguages($project_id);
        if (json_encode($currentLanguages) != json_encode($projectConfig->languages)) { // If languages part is different then ONLY update config JSON
            // Increment Version by "1"
            $projectConfig->version++;

            $newProjectConfig = json_encode($projectConfig);
            $sql = "UPDATE redcap_mycap_projects 
                SET config = '".db_escape($newProjectConfig)."'
                WHERE project_id = ".$project_id;
            if (db_query($sql)) {
                // Logging
                \Logging::logEvent($sql,"redcap_mycap_projects","MANAGE",$project_id,"project_id = ".$project_id,
                    "Multi-Language Management: Updated MyCap version (Version: ".$projectConfig->version.") and translations");
                return true;
            }
        }
        return false;
    }

    /**
     * Display Additional settings form
     *
     * @return string
     */
    public static function displayAdditionalSetupTable()
    {
        global $Proj, $myCapProj;
        $baseline_setup_html = ZeroDateTask::displaySetupTable();
        if ($Proj->longitudinal) {
            $custom_event_lable_html = self::displayCustomSetupTable();
        }
        $task_completion_status = $Proj->project['task_complete_status'];

        $mtb_english_tasks_exists = ActiveTask::isLangMTBActiveTaskExists('English');
        $mtb_spanish_tasks_exists = ActiveTask::isLangMTBActiveTaskExists('Spanish');

        $prevent_lang_switch_mtb = $myCapProj->project['prevent_lang_switch_mtb'];
        ob_start();
        ?>
        <div>
            <p>
                <?php echo RCView::tt('setup_52') ?>
            </p>
            <div id="errMsgContainerModal" class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;"></div>
            <div class="round chklist" style="padding:10px 20px;max-width:900px;">
                <form id="MyCapAdditionalSetting">
                    <table style="width:100%;" cellspacing=0 border="0">
                        <!-- Record Status Dashboard Status upon task completion -->
                        <tr>
                            <td valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px; border-bottom: 2px solid #ddd;">
                                <i class="fas fa-circle-check" style="text-indent: 0;"></i>
                                <b style=""><u><?php echo RCView::tt('mycap_mobile_app_906')?></u></b><br>
                                <?php
                                echo RCView::tt('mycap_mobile_app_887'). ' "'. RCView::tt('global_92').'" ['.RCView::img(array('src'=>'circle_red.png', 'width'=>'14', 'alt'=>RCView::tt('global_92'))).'] '.
                                    ', "'.RCView::tt('global_93').'" ['.RCView::img(array('src'=>'circle_yellow.png', 'width'=>'14', 'alt'=>RCView::tt('global_93'))).'] '.
                                    RCView::tt('global_47').' "'.RCView::tt('survey_28').'" ['.RCView::img(array('src'=>'circle_green.png', 'width'=>'14', 'alt'=>RCView::tt('survey_28'))).']. '.
                                RCView::tt('mycap_mobile_app_888');
                                ?>
                                <div style="text-indent:0em;padding:10px 0 0;">
                                    <b>Set Status as: </b><select name="task_completion_status" id="task_completion_status" class="x-form-text x-form-field" style="margin-top:3px;max-width:375px;">
                                        <option value='0' <?php if ($task_completion_status == '0') print "selected"; ?>><?php print RCView::tt('global_92'); ?></option>
                                        <option value='1' <?php if ($task_completion_status == '1') print "selected"; ?>><?php print RCView::tt('global_93'); ?></option>
                                        <option value='2' <?php if ($task_completion_status == '2') print "selected"; ?>><?php print RCView::tt('survey_28'); ?></option>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        <!-- Baseline Date Setting -->
                        <tr>
                            <td valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 10px;<?php echo ($Proj->longitudinal) ? 'border-bottom: 2px solid #ddd;' : '';?>">
                                <?php echo $baseline_setup_html; ?>
                            </td>
                        </tr>
                        <?php if ($Proj->longitudinal) { ?>
                            <!-- Custom Event Label Setting -->
                            <tr>
                                <td valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 10px;">
                                    <?php echo $custom_event_lable_html; ?>
                                </td>
                            </tr>
                        <?php }
                        if ($mtb_english_tasks_exists && $mtb_spanish_tasks_exists) {
                        ?>
                        <!-- MTB: Setting to remove the ability for participant to switch languages (for Spanish to English and a vice versa) in the app -->
                        <tr>
                            <td valign="top" style="margin-left:1.5em;text-indent:-2.2em;padding:10px 5px 10px 40px;">
                                <input type="checkbox" id="prevent_lang_switch_mtb" name="prevent_lang_switch_mtb" <?php if ($prevent_lang_switch_mtb) print "checked"; ?>>
                                <i class="fas fa-mobile-alt" style="text-indent: 0;"></i> <i class="fas fa-globe" style="text-indent: 0;margin:0 1px 0 2px;"></i>
                                <b><u><?php echo RCView::tt('mycap_mobile_app_967')?></u></b><br>
                                <?php echo RCView::tt('mycap_mobile_app_968')?>
                            </td>
                        </tr>
                        <?php } ?>
                    </table>
                </form>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        // Return all html to display
        return $html;
    }

    /**
     * Process stored config JSON and remove disabled tasks resulted from Form Display Logic
     *
     * @param int $projectId
     * @param string $record
     * @param int $event
     * @return string
     */
    public function processConfigJSONForFDL($projectId, $record, $event) {
        $projectConfig = $this->config;
        $configArr = json_decode($projectConfig, true);

        $Proj = new Project($projectId);
        if (!empty($configArr['tasks'])) {
            if (!$Proj->longitudinal) {
                $eventId = $Proj->firstEventId;
                $formsAccess = \FormDisplayLogic::getAccess('record_status_dashboard', $record, $eventId, null, 1, [], $projectId);
            }
            foreach ($configArr['tasks'] as $taskIndex => $taskArr) {
                $form_name = $taskArr['identifier'];
                if ($Proj->longitudinal) {
                    $allowedArm = $Proj->eventInfo[$event]['arm_num'];
                    foreach ($taskArr['longitudinalSchedule'] as $i => $schedule) {
                        $eventId = $schedule['identifier'];
                        $formsAccess = \FormDisplayLogic::getAccess('record_status_dashboard', $record, $eventId, $form_name, 1, [], $projectId);
                        //$configArr['tasks'][$taskIndex]['longitudinalSchedule'][$i]['show'] = true;
                        if ((isset($formsAccess[$record][$eventId][$form_name]) && $formsAccess[$record][$eventId][$form_name] != 1)
                            || $schedule['arm'] != $allowedArm) {
                            unset($configArr['tasks'][$taskIndex]['longitudinalSchedule'][$i]);
                            // Future Requirement - Include this task schedule with "show" flag set to "false" - If not from arm that participant belongs to OR not accessible as per FDL set
                            //$configArr['tasks'][$taskIndex]['longitudinalSchedule'][$i]['show'] = false;
                        }
                    }
                } else {
                    //$configArr['tasks'][$taskIndex]['show'] = true;
                    if (isset($formsAccess[$record][$eventId][$form_name]) && $formsAccess[$record][$eventId][$form_name] != 1) {
                        unset($configArr['tasks'][$taskIndex]);
                        // Future Requirement - Include this task schedule with "show" flag set to "false" - If not from arm that participant belongs to OR not accessible as per FDL set
                        //$configArr['tasks'][$taskIndex]['show'] = false;
                    }
                }

            }
        }
        // Add Server configuration info
        $serverInfo['redcapVersion'] = REDCAP_VERSION;
        $serverInfo['phpVersion'] = PHP_VERSION;
        $configArr['serverConfiguration'] = $serverInfo;

        // Add fields to config JSON
        $fields = $this->getAllMyCapFormFields();
        $config = json_encode($configArr);
        $mergeResults = ProjectHandler::mergeConfig(
            $config,
            $fields
        );

        if ($mergeResults['success']) {
            return json_encode($mergeResults['config']);
        } else {
            return $config;
        }
    }
}
