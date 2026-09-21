<?php
namespace ExternalModules;

use Exception;

class Project
{
    private $framework;
    private $project_id;
    private $redcap_project_object;
    private $forms;
    private $fields;

    function __construct($framework, $project_id){
        $this->framework = $framework;
        $this->project_id = $framework->requireInteger($project_id);
    }

    function getFramework(){
        return $this->framework;
    }

    /**
     * This method should only ever be used by framework classes, and should never be documented for use by modules.
     */
    function getREDCapProjectObject(){
        if(!isset($this->redcap_project_object)){
            $this->redcap_project_object = ExternalModules::getREDCapProjectObject($this->project_id);
        }

        return $this->redcap_project_object;
    }

    /**
     * @return User[]
     */
    function getUsers(){
        $results = $this->framework->query("
			-- always return lowercase usernames so that UserRights calls work properly in all cases
			select lower(username) as username
			from redcap_user_rights
			where project_id = ?
			order by username
		", $this->project_id);

        $users = [];
        while($row = $results->fetch_assoc()){
            $users[] = new User($this->framework, $row['username']);
        }

        return $users;
    }

    function getProjectId() {
        return $this->project_id;
    }

    function getTitle(){
        return $this->getREDCapProjectObject()->project['app_title'];
    }

    /**
     * @return (int|string)|null
     */
    function getRecordIdField(){
        $metadata = $this->getREDCapProjectObject()->metadata;
        return array_keys($metadata)[0] ?? null;
    }

    function getEventId(){
        $eventId = $_GET['event_id'] ?? null;
        if($eventId){
            return $eventId;
        }

        $arms = $this->getREDCapProjectObject()->events;
        $armKeys = array_keys($arms);
        $arm = $arms[$armKeys[0]];
        $events = $arm['events'] ?? []; 

        if(count($events) === 0){
            throw new Exception("No events found for project " . $this->getProjectId());
        }
        else if(count($events) > 1){
            throw new Exception("Multiple events found for project " . $this->getProjectId());
        }

        return array_keys($events)[0];
    }

    function addOrUpdateInstances($instances, $keyFieldNames){
        if(empty($instances)){
            return;
        }

        if(!is_array($keyFieldNames)){
            $keyFieldNames = [$keyFieldNames];
        }

        if(empty($keyFieldNames)){
            throw new Exception(ExternalModules::tt('em_errors_132'));
        }

        $instrumentName = null;
        foreach($keyFieldNames as $field){
            $instrumentNameForField = $this->getFormForField($field);
            if(empty($instrumentNameForField)){
                throw new Exception(ExternalModules::tt('em_errors_139', $field));
            }
            else if($instrumentName === null){
                $instrumentName = $instrumentNameForField;
            }
            else if($instrumentNameForField !== $instrumentName){
                throw new Exception(ExternalModules::tt('em_errors_133'));
            }
        }
        
        $recordIdFieldName = $this->getRecordIdField();
        array_unshift($keyFieldNames, $recordIdFieldName);

        $recordIds = array_unique(array_column($instances, $recordIdFieldName));

        $fields = $this->getForm($instrumentName)->getFieldNames();
        $existingInstances = json_decode(\REDCap::getData($this->getProjectId(), 'json', $recordIds, array_merge([$recordIdFieldName], $fields)), true);
        
        $getInstanceIndex = /**
         * @return false|string
         */
        function($instance) use ($keyFieldNames){
            $instanceIndex = [];
            foreach($keyFieldNames as $field){
                $value = $instance[$field] ?? null;
                if($value === null){
                    throw new Exception(ExternalModules::tt('em_errors_134', $field));
                }

                // Use string values to make sure comparisons work properly
                // regardless of whether string or integer values are used.
                $instanceIndex[] = (string) $value;
            }

            return json_encode($instanceIndex);
        };

        $existingInstancesIndexed = [];
        $remainingIndexes = [];
        $lastInstanceNumbers = [];
        foreach($existingInstances as $instance){
            if($instance['redcap_repeat_instrument'] === ''){
                // The is the non-repeating row for the record itself.  Skip it.
                continue;
            }

            $instanceIndex = $getInstanceIndex($instance);
            if(isset($existingInstancesIndexed[$instanceIndex])){
                throw new Exception(ExternalModules::tt('em_errors_135', $instrumentName) . json_encode($instance, JSON_PRETTY_PRINT));
            }

            $existingInstancesIndexed[$instanceIndex] = $instance;
            $recordId = $instance[$recordIdFieldName];
            $redcapRepeatInstance = $instance['redcap_repeat_instance'];
            $lastInstanceNumbers[$recordId] = max($redcapRepeatInstance, $lastInstanceNumbers[$recordId] ?? null);
            $remainingIndexes[$redcapRepeatInstance] = true;
        }

        $dataToSave = [];
        $uniqueIndexes = [];
        foreach($instances as $instance){
            if(!is_array($instance)){
                throw new Exception(ExternalModules::tt('em_errors_136'));
            }

            $instanceInstrumentName = $instance['redcap_repeat_instrument'] ?? null;
            if(empty($instanceInstrumentName)){
                // Assume the correct instrument.
                $instance['redcap_repeat_instrument'] = $instrumentName;
            }
            elseif($instanceInstrumentName !== $instrumentName){
                throw new Exception(ExternalModules::tt('em_errors_137', $instrumentName, $instanceInstrumentName));
            }

            $instanceIndex = $getInstanceIndex($instance);
            if(isset($uniqueIndexes[$instanceIndex])){
                throw new Exception(ExternalModules::tt('em_errors_138') . json_encode($instance, JSON_PRETTY_PRINT));
            }
            else{
                $uniqueIndexes[$instanceIndex] = true;
            }

            $recordId = $instance[$recordIdFieldName];
            $existingInstance = $existingInstancesIndexed[$instanceIndex] ?? null;
            if($existingInstance === null){
                if(!isset($lastInstanceNumbers[$recordId])){
                    $lastInstanceNumbers[$recordId] = 0;
                }

                $instance['redcap_repeat_instance'] = ++$lastInstanceNumbers[$recordId];
            }
            else{
                $instance = array_merge($existingInstance, $instance);
                $remainingIndexes[$recordId][$instance['redcap_repeat_instance']] ?? null;
            }
            
            $dataToSave[] = $instance;
        }
        
        $results = \REDCap::saveData(
            $this->getProjectId(),
            'json',
            json_encode($dataToSave),
            'overwrite'
        );

        // TODO - In the future maybe add a flag (or an additional replaceInstance() method) to remove old instances that no longer exist.
        // foreach($remainingIndexes as $recordId=>$instanceNumber){
        //     $instanceNumbers = array_keys($instanceNumbers);
        //     $this->removeInstances($recordId, $instrumentName, $instanceNumbers);
        // }

        return $results;
    }

    function getFormForField($fieldName){
        $result = $this->framework->query('select form_name from redcap_metadata where project_id = ? and field_name = ?', [$this->getProjectId(), $fieldName]);
        return $result->fetch_row()[0] ?? null;
    }

    /**
     * @return void
     */
    function addUser($username, $rights = []){
        $rights = array_merge($rights, [
            'username' => $username
        ]);

        $rights = $this->addMissingKeys($rights, \UserRights::getApiUserPrivilegesAttr());

        \UserRights::addPrivileges($this->getProjectId(), $rights);
    }

    /**
     * @return void
     */
    function setRights($username, $rights){
        $rights = array_merge($rights, [
            'username' => $username
        ]);

        $rights = $this->addMissingKeys($rights, [
            'data_access_group'
        ]);
        
        \UserRights::updatePrivileges($this->getProjectId(), $rights);
    }

    /**
     * @return void
     */
    function removeUser($username){
        \UserRights::removePrivileges($this->getProjectId(), $username);
    }

    function getRights($username){
        // Some users are stored with an uppercase first letter on REDCap Test.
        // The getRights() still expects them to be lowercase though.
        // Not sure if this is the best location for this fix...
        $username = strtolower($username);

        return $this->framework->getUser($username)->getRights($this->getProjectId());
    }

    function addMissingUserRightsKeys($rights){
        $keys = [
            'project_id',
            'role_name',
            'data_export_tool',
            'data_import_tool',
            'data_comparison_tool',
            'data_logging',
            'file_repository',
            'double_data',
            'user_rights',
            'design',
            'lock_record',
            'lock_record_multiform',
            'lock_record_customize',
            'data_access_groups',
            'graphical',
            'reports',
            'calendar',
            'record_create',
            'record_rename',
            'record_delete',
            'dts',
            'participants',
            'data_quality_design',
            'data_quality_execute',
            'data_quality_resolution',
            'api_export',
            'api_import',
            'api_modules',
            'mobile_app',
            'mobile_app_download_data',
            'random_setup',
            'random_dashboard',
            'random_perform',
            'realtime_webservice_mapping',
            'realtime_webservice_adjudicate',
            'external_module_config',
            'data_entry',
            'group_id',
            'mycap_participants',
            'alerts',
            'email_logging',
        ];

        $redcapProject = $this->getREDCapProjectObject();
        foreach($redcapProject->forms as $formName=>$formDetails){
            $keys[] = "form-$formName";
            $keys[] = "export-form-$formName";
        }

        return $this->addMissingKeys($rights, $keys);
    }

    /**
     * @return void
     */
    function addRole($roleName, $rights = []){
        $rights = $this->addMissingUserRightsKeys($rights);

        $redcapProject = $this->getREDCapProjectObject();

        $originalPost = $_POST;
        
        $_POST = $rights;
        \UserRights::addRole($redcapProject, $roleName, ExternalModules::getUsername());

        $_POST = $originalPost;
    }

    /**
     * @param array $array
     * @param string[] $keys
     *
     * @return (mixed|null)[]
     */
    private function addMissingKeys($array, $keys){
        foreach($keys as $key){
            if(!isset($array[$key])){
                // This avoids missing key PHP warnings in REDCap core.
                $array[$key] = null;
            }
        }

        return $array;
    }

    /**
     * @return void
     */
    function removeRole($roleName){
        \UserRights::removeRole($this->getProjectId(), $this->getRoleId($roleName), $roleName);
    }

    function setRoleForUser($roleName, $username): bool{
        return \UserRights::updateUserRoleMapping($this->getProjectId(), $username, $this->getRoleId($roleName));
    }

    function getRoleId($roleName){
        return ExternalModules::getRoleId($this->getProjectId(), $roleName);
    }

    function getLogTable(){
        return $this->getREDCapProjectObject()->project['log_event_table'];
    }
    
    /**
     * @return never
     */
    function deleteRecords(){
        /**
         * This method could fairly easily be implemented by:
         * 1. Creating a PR for REDCap core to extract the record deletion code in API\record\delete.php to a shared method (perhaps Records::deleteRecords())
         * 2. Calling that new method here.
         */

        throw new Exception('Not implemented');
    }
    
    /**
     * @psalm-taint-sink sql $sql
     * @return \mysqli_result
     */
    function queryData($sql, $parameters){
        $query = new DataPseudoQuery($this);
        $sql = $query->getActualSQL($sql);
        // var_dump($sql, $parameters);
        return $this->framework->query($sql, $parameters);
    }

    function getForm($formName){
        return $this->cache($this->forms, $formName, Form::class);
    }

    function getField($fieldName){
        return $this->cache($this->fields, $fieldName, Field::class);
    }

    /**
     * @param string $class
     */
    private function cache(&$cache, $key, $class){
        $instance = $cache[$key] ?? null;
        if($instance === null){
            $instance = new $class($this, $key);
            $cache[$key] = $instance;
        }

        return $instance;
    }

    function getRepeatingForms($eventId = null){
        return $this->getFramework()->getRepeatingForms($eventId, $this->getProjectId());
    }

    /**
     * @return ((int|string)|mixed)[]
     */
    function getFormsForEventId($eventId){
        $project = $this->getREDCapProjectObject();
        if(count($project->getUniqueEventNames()) === 1){
            return array_keys($project->forms);
        }
        else{
            $result = $this->framework->query('select form_name from redcap_events_forms where event_id = ?', $eventId);
            
            $formNames = [];
            while($row = $result->fetch_assoc()){
                $formNames[] = $row['form_name'];
            }

            return $formNames;
        }
    }
}
