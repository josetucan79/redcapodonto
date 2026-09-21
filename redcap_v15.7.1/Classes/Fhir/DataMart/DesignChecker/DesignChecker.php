<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker;

use Project;
use Exception;
use UserRights;
use Vanderbilt\REDCap\Classes\ProjectDesigner;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use Vanderbilt\REDCap\Classes\Fhir\Utility\ArrayUtils;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Blueprint\Document;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\AbstractCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\CommandInterface;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\CreateFormCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\AddRevisionCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\SetFormOrderCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\CreateVariableCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\EnableDataMartCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\MoveSectionHeaderCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\CommitDraftChangesCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\MakeFormRepeatableCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\CreateSectionHeaderCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\AssignVariableToFormCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\MakeFormNotRepeatableCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\ClonePrimaryKeyEntriesCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\UpdateVariableSettingsCommand;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands\SetVariableRelativeOrderCommand;

class DesignChecker
{
    /**
     *
     * @var string
     */
    private $project_id;

    /**
     *
     * @var Project
     */
    private $project;

    /**
     *
     * @var Document
     */
    private $blueprint;

    /**
     *
     * @var int
     */
    private $userid;


    private $cache;
    private $cacheNamespace;

    /**
     *
     * @var AbstractCommand[]
     */
    private $commands = [];

    /**
     *
     * @param int $userid
     * @param Project $project
     */
    public function __construct($project_id, $userid)
    {
        $this->project_id = $project_id;
        $this->userid = $userid;
        // init the blueprint
        $templatePath = DataMart::getProjectTemplatePath();
        $this->blueprint = Document::fromFile($templatePath);
        $this->initProject();
    }

    /**
     * add a command to the list
     * and use a hash as key
     *
     * @param AbstractCommand $command
     * @return void
     */
    public function addCommand(AbstractCommand $command)
    {
        $this->commands[] = $command;
    }

    /**
     *
     * @return AbstractCommand[]
     */
    public function getCommands()
    {
        return $this->commands;
    }

    public function getCommand($id)
    {
        $command = ArrayUtils::find($this->commands, function($command) use($id){
            return $command->getID()==$id; 
        });
        return $command;
    }

    /**
     *
     * @return void
     */
    public function resetCommands() {
        $this->commands = [];
    }
    
    public function initProject() {
        // init the project
        $this->project = new Project($this->project_id);
        $this->project->setRepeatingFormsEvents(); // init the RepeatingFormsEvents property of the project
        if($this->projectIsProduction()) $this->project->loadMetadataTemp();
    }

    public function projectIsDevelopment() { 
        return intval($this->project->project['status'] ?? 0)===0;
    }

    public function projectIsProduction() { 
        return !$this->projectIsDevelopment();
    }

    public function projectIsDraftMode() {
        if($this->projectIsDevelopment()) return false;
        return intval($this->project->project['draft_mode'] ?? 0)===1;
    }


    /**
     * return metadata.
     * if in production, but the array is empty (no draft changes),
     * then return the original ones, else the temp ones
     *
     * @return array
     */
    public function getProjectMetadata($temp=false)
    {
        if($temp) return $this->project->metadata_temp ?? [];
        return $this->project->metadata ?? [];
    }

    /**
     * return forms.
     * if in production, but the array is empty (no draft changes),
     * then return the original ones, else the temp ones
     *
     * @return array
     */
    public function getProjectForms($temp=false)
    {
        if($temp) return $this->project->forms_temp ?? null;
        return $this->project->forms ?? null;
    }

    /**
     * perform all checks
     * - missing instrument
     * - mark instrument as repeatable
     * - missing fields
     * - fix custom labels
     * @return array
     */
    public function check() {
        $this->resetCommands();
        $blueprintForms = $this->blueprint->getForms();
        $blueprintRepeatingForms = $this->blueprint->getRepeatingForms();
        $blueprintRepeatingFormsNames = array_column($blueprintRepeatingForms, 'name');

        // create an instance of ProjectDesigner that will be used as receiver for the commands
        $projectDesigner = new ProjectDesigner($this->project);

        // check if Data Mart is enabled in the project
        $datamartEnabled = DataMart::isEnabled($this->project->project_id);
        if(!$datamartEnabled) $this->addCommand(new EnableDataMartCommand($this->project)); // the receiver is the project

        // check if there is at least 1 DataMart revision
        $dataMart = new DataMart($this->userid);
        $revisions = $dataMart->getRevisions($this->project_id);
        if(empty($revisions)) $this->addCommand(new AddRevisionCommand($dataMart, $this->project_id, $this->userid));

        // check primary key
        $primaryKey = $this->blueprint->getPrimaryKey();
        $currentPrimaryKey = $this->primaryKeyIsMatching($primaryKey);
        if($currentPrimaryKey!==true) {
            $this->addCommand(new ClonePrimaryKeyEntriesCommand($this, $currentPrimaryKey, $primaryKey));
        }

        $checkVariable = function($formName, $variableIndex, $variableSettings) use($projectDesigner) {
            $variableName = $variableSettings['field_name'] ?? '';
            $variableExists = $this->variableExists($variableName);
            if($variableExists) {
                // check if needs to be moved to another form
                if($otherForm = $this->variableBelongsToAnotherForm($variableName, $formName)) {
                    $this->addCommand(new AssignVariableToFormCommand($projectDesigner, $formName, $variableSettings, $otherForm));
                    $this->addCommand(new SetVariableRelativeOrderCommand($projectDesigner, $variableName, $variableIndex));
                } else {
                    // check if the relative position in the form is correct
                    $variablePositionIsCorrect = $this->variableRelativePositionIsCorrect($variableName,  $variableIndex);
                    if(!$variablePositionIsCorrect)
                        $this->addCommand(new SetVariableRelativeOrderCommand($projectDesigner, $variableName, $variableIndex));
                    
                }
                // check if the settings are correct
                $settingsMismatch = $this->getVariableSettingsMismatch($variableName, $variableSettings);
                if(!empty($settingsMismatch)) {
                    $this->addCommand(new UpdateVariableSettingsCommand($projectDesigner, $formName, $variableSettings, $settingsMismatch));
                }
            }else {
                $this->addCommand(new CreateVariableCommand($projectDesigner, $formName, $variableSettings));
                // $this->addCommand(new SetVariableRelativeOrderCommand($projectDesigner, $variableName, $variableIndex));
            }
        };

        $checkHeaders = function($formName, $sectionHeader) use($projectDesigner){
            $redcapField = $sectionHeader['redcapField'] ?? '';
            $text = $sectionHeader['text'] ?? '';
            if(!$this->sectionHeaderExists($formName, $text)) {
                $this->addCommand(new CreateSectionHeaderCommand($projectDesigner, $formName, $redcapField, $text));
            }else if($otherVariable = $this->sectionHeaderBelongsToAnotherVariable($formName, $redcapField, $text)) {
                $this->addCommand(new MoveSectionHeaderCommand($projectDesigner, $formName, $text, $redcapField, $otherVariable));
            }
        };
        
        // check all instruments
        foreach ($blueprintForms as $formIndex => $form) {
            $formName = $form['name'];
            $eventID = $this->project->firstEventId; // event where forms should exist in a Data Mart
            $isRepeating = in_array($formName, $blueprintRepeatingFormsNames);

            // collect all form data
            $blueprintVariables = $this->blueprint->getFormVariables($formName);
            $blueprintSectionHeaders = $this->blueprint->getSectionHeaders($formName);

            // check for missing forms
            $formExists = $this->formExists($formName);
            if(!$formExists) {
                $this->addCommand(new CreateFormCommand($projectDesigner, $formName, $form['label']));
                // check all variables
                foreach ($blueprintVariables as $variableIndex => $variableSettings) {
                    $checkVariable($formName, $variableIndex, $variableSettings);
                }
                // check section headers
                foreach ($blueprintSectionHeaders as $sectionHeader) {
                    $checkHeaders($formName, $sectionHeader);
                }
                if($isRepeating) $this->addCommand(new MakeFormRepeatableCommand($projectDesigner, $formName, $eventID));
                $this->addCommand(new SetFormOrderCommand($projectDesigner, $formName, $formIndex));
                continue; // skip the rest since we have done everything related to the newly created form
            }else {
                $formPositionIsCorrect = $this->formPositionIsCorrect($formName,  $formIndex);
                if(!$formPositionIsCorrect) {
                    $this->addCommand(new SetFormOrderCommand($projectDesigner, $formName, $formIndex));
                }
                // fix repeatable option
                if($isRepeating && !$this->project->isRepeatingFormOrEvent($eventID, $formName)) {
                    $this->addCommand(new MakeFormRepeatableCommand($projectDesigner, $formName, $eventID));
                }else if(!$isRepeating && $this->project->isRepeatingFormOrEvent($eventID, $formName)) {
                    $this->addCommand(new MakeFormNotRepeatableCommand($projectDesigner, $formName, $eventID));
                }
            }
            
            // check all variables
            foreach ($blueprintVariables as $variableIndex => $variableSettings) {
                $checkVariable($formName, $variableIndex, $variableSettings);
            }


            // check section headers
            foreach ($blueprintSectionHeaders as $sectionHeader) {
                $checkHeaders($formName, $sectionHeader);
            }

            
        }
        if($this->draftsMustBeCommitted()) $this->addCommand(new CommitDraftChangesCommand($this));
        return $this->getCommands();
    }

    /**
     * check if the position of a form is correct
     *
     * @param string $formName
     * @param int $formIndex
     * @return bool
     */
    public function formPositionIsCorrect($formName, $formIndex) {
        $formPositionIsCorrect = function($temp=false) use($formName, $formIndex) {
            $formsData = $this->getProjectForms($temp);
            $key = array_search($formName, array_keys($formsData));
            if(!is_numeric($key)) return false;
            return intval($key)===$formIndex;
        };
        if($this->projectIsDraftMode()) return $positionIsCorrectAsDraft = $formPositionIsCorrect(true);
        return $positionIsCorrect = $formPositionIsCorrect();        
    }

    /**
     * get a zero based position of a field relative
     * to its container form
     *
     * @param string $variableName
     * @param boolean $temp
     * @return int|false
     */
    public function getVariableRelativePosition($variableName, $temp=false) {
        $metadata = $this->getProjectMetadata($temp);
        $fieldMetadata = $metadata[$variableName] ?? [];
        $formName = $fieldMetadata['form_name'] ?? '';
        if(!$formName) return false;
        $forms = $this->getProjectForms($temp);
        $formData = $forms[$formName] ?? '';
        if(!$formData) return false;
        $fields = $formData['fields'] ?? [];
        $key = array_search($variableName, array_keys($fields));
        if(!is_numeric($key)) return false;
        return intval($key);

    }

    public function variableRelativePositionIsCorrect($variableName, $variableIndex) {
        $variableRelativePositionIsCorrect = function($temp=false) use($variableName, $variableIndex) {
            $variableRelativePosition = $this->getVariableRelativePosition($variableName, $temp);
            return $variableRelativePosition == $variableIndex;
        };
        if($this->projectIsDraftMode()) return $positionIsCorrectAsDraft = $variableRelativePositionIsCorrect(true);
        return $positionIsCorrect = $variableRelativePositionIsCorrect();        
    }

    public function executeCachedCommands() {
        $commands = $this->restoreCommands();
        if(empty($commands)) return false;
        $executed = $this->executeCommands();
        $this->emptyCache();
        return $executed;
    }

    /**
     * execute all provided commands
     *
     * @param CommandInterface[] $commands
     * @return void
     */
    public function executeCommands() {
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");
        $executed = [];

        try {
            $commands = $this->getCommands();
            foreach ($commands as $command) {
                $command->execute();
                $executed[] = $command;
            }
            db_query("COMMIT");
            return $executed;
        } catch (\Exception $e) {
            db_query("ROLLBACK");
            $message = 'The process was stopped because a command could not be executed.';
            $message .= PHP_EOL.$e->getMessage();
            throw new Exception($message, 400);
        }finally {
			db_query("SET AUTOCOMMIT=1");
        }

    }

    /**
     * backup a list of commands in a cache file
     *
     * @param CommandInterface[] $commands
     * @return void
     */
    public function backupCommands() {
        $cache = $this->getCache();
        $list = [];
        foreach ($this->getCommands() as $command) {
            $ID = $command->getID();
            $list[$ID] = $command;
        }
        
        $cache->set('commands', serialize($list));
    }

    public function restoreCommands() {
        $cache = $this->getCache();
        $cachedCommands = $cache->get('commands') ?? '';
        $commands = unserialize($cachedCommands, ['allowed_classes'=>[
            AbstractCommand::class,
            AddRevisionCommand::class,
            AssignVariableToFormCommand::class,
            ClonePrimaryKeyEntriesCommand::class,
            CommandInterface::class,
            CommitDraftChangesCommand::class,
            CreateFormCommand::class,
            CreateSectionHeaderCommand::class,
            CreateVariableCommand::class,
            DataMart::class,
            DesignChecker::class,
            EnableDataMartCommand::class,
            MakeFormNotRepeatableCommand::class,
            MakeFormRepeatableCommand::class,
            MoveSectionHeaderCommand::class,
            Project::class,
            ProjectDesigner::class,
            SetFormOrderCommand::class,
            SetVariableRelativeOrderCommand::class,
            UpdateVariableSettingsCommand::class,
            ]]) ?: [];
        $this->commands = $commands;
        return $this->getCommands();
    }

    public function emptyCache() {
        $cache = $this->getCache();
        $cache->set('commands', '');
    }

    public function draftsMustBeCommitted()
    {
        if(!$this->projectIsDraftMode()) return false;
        $forms = $this->project->forms ?? [];
        $forms_temp = $this->project->forms_temp ?? [];
        if(!empty($forms_temp) && $forms!=$forms_temp) return true;
        $metadata = $this->project->metadata ?? [];
        $metadata_temp = $this->project->metadata_temp ?? [];
        if(!empty($metadata_temp) && $metadata!=$metadata_temp) return true;
        return false;
    }

    /**
     * check if a form exists in the project.
     * the check is performed using the first event ID
     * since a Data Mart project should have all forms
     * associated to the first event.
     * if in production check also for drafts
     *
     * @param string $formName
     * @return boolean
     */
    public function formExists($formName)
    {
        $formExists = function($temp=false) use($formName){
            $project_forms =  $this->getProjectForms($temp);
            return array_key_exists($formName, $project_forms);
        };
        if($this->projectIsDraftMode()) return $existsAsDraft = $formExists(true);
        return $exists = $formExists();
    }

    /**
     * check if a variable exists.
     * if in production check also for drafts
     *
     * @param string $variableName
     * @return bool
     */
    public function variableExists($variableName)
    {
        $variableExists = function($temp=false) use($variableName) {
            $metadata = $this->getProjectMetadata($temp);
            return array_key_exists($variableName, $metadata);
        };
        if($this->projectIsDraftMode()) return $existsAsDraft = $variableExists(true);
        return $exists = $variableExists();
    }

    /**
     * check if a section header exists.
     * if in production check also for drafts
     *
     * @param string $formName
     * @param string $text
     * @return bool
     */
    public function sectionHeaderExists($formName, $text)
    {
        $sectionHeaderExists = function($temp=false) use($formName, $text) {
            $metadata = $this->getProjectMetadata($temp);
            $found = array_filter($metadata, function($field) use($formName, $text) {
                $field_form_name = $field['form_name'] ?? null;
                $field_element_preceding_header = $field['element_preceding_header'] ?? null;
                if($field_form_name != $formName) return false;
                return $field_element_preceding_header == $text;
            });
            return !empty($found);
        };
        if($this->projectIsDraftMode()) return $existsAsDraft = $sectionHeaderExists(true);
        return $exists = $sectionHeaderExists();
    }

    
    /**
     * check if header is in the wrong place
     *
     * @param string $formName
     * @param string $variableName
     * @param string $text
     * @return bool
     */
    public function sectionHeaderBelongsToAnotherVariable($formName, $variableName, $text)
    {
        $sectionHeaderBelongsToAnotherVariable = function($temp=false) use($formName, $variableName, $text) {
            $metadata = $this->getProjectMetadata($temp);
            foreach ($metadata as $key => $field) {
                $field_form_name = $field['form_name'] ?? null;
                $field_element_preceding_header = $field['element_preceding_header'] ?? null;
                if($field_form_name != $formName) continue;
                if($field_element_preceding_header != $text) continue;
                $otherName = $field['field_name'] ?? '';
                if($otherName != $variableName) return $otherName;
            }
            return false;
        };
        if($this->projectIsDraftMode()) return $belongsAsDraft = $sectionHeaderBelongsToAnotherVariable(true);
        return $belongs = $sectionHeaderBelongsToAnotherVariable();  
    }

    /**
     * alter the keys of a variable's metadata
     * to match both the blueprint and the ProjectDesigner
     * keys used in crete/update variables
     *
     * @param string $variableName
     * @param bool $temp
     * @return array
     */
    public function getVariableMetadataFromProject($variableName, $temp=false)
    {
        $projectMetadata = $this->getProjectMetadata($temp);
        $metadata = $projectMetadata[$variableName] ?? [];
        $projectToBlueprintMapping = [
            'element_label' => 'field_label',
            'element_type' => 'field_type',
            'element_note' => 'field_note',
            'element_validation_type' => 'val_type',
            'misc' => 'field_annotation',
            'element_validation_min' => 'val_min',
            'element_validation_max' => 'val_max',
        ];
        foreach ($projectToBlueprintMapping as $projectKey => $blueprintKey) {
            $metadata[$blueprintKey] = $metadata[$projectKey] ?? null;
        }
        // adjust enums so they always match
        $enumKey = 'element_enum';
        if($metadata[$enumKey]) {
            $metadata[$enumKey] = preg_replace("/\s*(\\\\r|\\\\n|\\\\r\\\\n)\s*/", '\n', $metadata[$enumKey]);
        }
        return $metadata;
    }

    /**
     * compare the settings of the blueprint for a variable
     * with the ones of the matching variable in the project
     *
     * @param string $variableName
     * @param array $settings
     * @return array keys of settings not matching the blueprint
     */
    public function getVariableSettingsMismatch($variableName, $settings=[])
    {
        $draftMode = $this->projectIsDraftMode();
        $variable = $this->getVariableMetadataFromProject($variableName, $draftMode);
        if(!$variable) return false;
        $notMatching = [];

            // list of settings that should not be checked
        $skipSettings = [
            'field_annotation', // ignore any user created field annotation
        ];
        
        foreach ($settings as $key => $blueprintSetting) {
            if(Document::isVariablePrivateSetting($key)) continue;
            if(in_array($key, $skipSettings)) continue;
            $variableSetting = $variable[$key] ?? null;
            if($variableSetting!=$blueprintSetting) $notMatching[$key] = $blueprintSetting;
        }
        return $notMatching;
    }

    public function getProjectPrimaryKey($temp=false) {
        if(!$temp) return $this->project->table_pk ?? null;
        else return $this->project->table_pk_temp ?? null;
    }

    /**
     * check if the primary key in the project
     * matches the one expected for the Data Mart
     *
     * @param string $primaryKey
     * @return true|string true if matches or the current primary kley if different
     */
    public function primaryKeyIsMatching($primaryKey) {
        $primaryKeyIsMatching = function($temp=false) use($primaryKey) {
            $formPrimaryKey = $this->getProjectPrimaryKey($temp);
            $match = $formPrimaryKey==$primaryKey;
            if($match===false) return $formPrimaryKey;
            return true;
        };
        if($this->projectIsDraftMode()) return $matchAsDraft = $primaryKeyIsMatching(true);
        return $match = $primaryKeyIsMatching();
    }

    /**
     * check if a variable belongs to another form
     *
     * @param string $variableName
     * @param string $formName
     * @return string|false name of the parent form or false if belongs to the expected form
     */
    public function variableBelongsToAnotherForm($variableName, $formName)
    {
        $variableBelongsToAnotherForm = function($temp=false) use($variableName, $formName) {
            $variable = $this->getVariableMetadataFromProject($variableName, $temp);
            if(!$variable) return false;
            $parentForm = $variable['form_name'] ?? '';
            if($parentForm == $formName) return false;
            return $parentForm;
        };
        if($this->projectIsDraftMode()) return $belongsAsDraft = $variableBelongsToAnotherForm(true);
        return $belongs = $variableBelongsToAnotherForm();
    }

    /**
     * check if the custom labels of a (repeating) form
     * match the expected ones
     *
     * @param string $formName
     * @param string $expectedCustomLabels
     * @return boolean
     */
    function hasEqualFormCustomLabel($formName, $expectedCustomLabels)
    {
        $getFormCustomLabels = function($formName) {
            /**
             * NOTE: the project property RepeatingFormsEvents is only available after
             * that the function Project::setRepeatingFormsEvents has been called (i.e. after Project::isRepeatingFormAnyEvent)
             */
            $repeatingForms = $this->project->RepeatingFormsEvents; // see note above for this variable
            $customLabels = array_column($repeatingForms, $formName);
            return  current($customLabels);
        };
        $formCustomLabels = $getFormCustomLabels($formName);
        return strcmp($formCustomLabels, $expectedCustomLabels)==0;
    }

    /**
     * get settings related to the current user
     * and the project
     *
     * @return array [privileges, project_metadata]
     */
    public function getSettings()
    {
        $getUserPrivileges = function() {
            $userinfo = \User::getUserInfoByUiid($this->userid);
            $username = $userinfo['username'] ?? '';
            $userRightsPrivileges = UserRights::getPrivileges($this->project_id, $username) ?: [];
            $projectPrivileges = current($userRightsPrivileges) ?: [];
            $userPrivileges = current($projectPrivileges) ?: [];
            // extract only the data needed by the design checker
            $settings = [
                'design' => boolval($userPrivileges['design'] ?? 0),
            ];
            return $settings;
        };
        $getProjectMetadata = function() {
            $statusMapping = [
                0 => 'development',
                1 => 'production',
            ];
            $projectMetadata = $this->project->project;
            $settings = [
                'draft_mode' => $draftMode = boolval($projectMetadata['draft_mode'] ?? 1),
                'status' => $status = $statusMapping[intval($projectMetadata['status'] ?? 0)] ?? $statusMapping[1], //defaults to production so modification is prevented
                'can_be_modified' => ($status==$statusMapping[0] || $draftMode==true),
            ];
            return $settings;
        };
        $settings = [
            'privileges' => $getUserPrivileges(),
            'project_metadata' => $getProjectMetadata(),
        ];
        return $settings;
    }

    /**
     *
     * @return FileCache
     */
    private function getCache() {
        if(!$this->cache) $this->setCache();
        return $this->cache;
    }

    private function setCache() {
        $this->setCacheNamespace();
        $namespace = $this->getCacheNamespace();
        $this->cache = new FileCache($namespace);
    }

    private function getCacheNamespace(): string { return $this->cacheNamespace; }
    private function setCacheNamespace(): void { $this->cacheNamespace = sprintf('%s %u %u', __CLASS__, $this->project_id, $this->userid); }


    /**
     * access all properties (also private ones, but in read only mode)
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if(!property_exists($this, $name)) return;
        return $this->{$name};
    }
    


    
}

