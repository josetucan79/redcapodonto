<?php
namespace ExternalModules;

AbstractExternalModule::init();

if (class_exists('ExternalModules\AbstractExternalModule')) {
	return;
}

use Exception;

class AbstractExternalModule
{
	const UI_STATE_OBJECT_PREFIX = 'external-modules.';
	const EXTERNAL_MODULE_ID_STANDARD_WHERE_CLAUSE_PREFIX = "redcap_external_modules_log.external_module_id = (SELECT external_module_id FROM redcap_external_modules WHERE directory_prefix";

	public static $RESERVED_LOG_PARAMETER_NAMES = ['log_id', 'external_module_id', 'ui_id'];
	public static $RESERVED_LOG_PARAMETER_NAMES_FLIPPED;

	public static $OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE = ['timestamp', 'username', 'ip', 'project_id', 'record', 'message'];
	public $PREFIX;
	public $VERSION;

	/**
	 * @psalm-suppress PossiblyUnusedProperty
	 * @var Framework
	 */
	public $framework;

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	function __construct()
	{
		list($prefix, $version) = ExternalModules::getParseModuleDirectoryPrefixAndVersion(ExternalModules::getModuleDirectoryName($this));

		$this->PREFIX = $prefix;
		$this->VERSION = $version;

		// The framework instance must be cached in this constructor so that it is available
		// for any calls to framework provided methods in the constructor of any module subclasses.
		// We used to cache framework instances in ExternalModules::getFrameworkInstance() after the module instance was created,
		// but the above scenario caused infinite loops: https://github.com/vanderbilt/redcap-external-modules/issues/329
		ExternalModules::cacheFrameworkInstance($this);
	}

	/**
	 * @return string
	 */
	protected function getSettingKeyPrefix(){
		return '';
	}

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return string
	 */
	public function prefixSettingKey($key){
		return $this->getSettingKeyPrefix() . $key;
	}

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @psalm-suppress PossiblyUnusedParam
	 */
	public function validateSettings($settings){
		return null;
	}

	public function redcap_module_link_check_display($project_id, $link)
	{
		if(ExternalModules::isNoAuth()){
			// Anyone can view NOAUTH pages.
			// Remember, redcap_module_link_check_display() is currently only called for pages defined in config.json.
			return $link;
		}

		if(ExternalModules::isSuperUser()){
			// Super users can see all pages
			return $link;
		}

		$username = ExternalModules::getUsername();
		if(!empty($project_id) && (\REDCap::getUserRights($username)[$username]['design'] ?? false)) {
			return $link;
		}

		return null;
    }

    /**
     * @return bool
     */
    public function redcap_module_configure_button_display(){
        return true;
    }

    public function __call($name, $arguments){
		if(!isset($this->PREFIX)){
			// The module's parent constructor has not finished yet.
			// Simulate the standard error.
			throw new Exception("Call to undefined method: $name");
		}
		
		// The version argument is required here for the case where a module is in the process of being enabled
		// (so the current version has not yet been set in the database)
		// and the constructor references a method on the framework
		// or a method that doesn't exist.
		return ExternalModules::getFrameworkInstance($this->PREFIX, $this->VERSION)->callFromModuleInstance($name, $arguments);
	}

	// Allow framework object references like `records` to be returned directly.
	public function __get($name){
		if(!isset($this->PREFIX)){
			// The module's parent constructor has not finished yet.  Just return null.
			return null;
		}

		// The version argument is required here for the case where a module is in the process of being enabled
		// (so the current version has not yet been set in the database)
		// and the constructor references a property on the framework
		// or a property that doesn't exist.
		return ExternalModules::getFrameworkInstance($this->PREFIX, $this->VERSION)->{$name};
	}

	/**
	 * @return void
	 */
	public static function init()
	{
		self::$RESERVED_LOG_PARAMETER_NAMES_FLIPPED = array_flip(self::$RESERVED_LOG_PARAMETER_NAMES);
	}

	/**
	 * The following methods have been defined directly on the module instance ever since they were added.
	 * New methods should NOT be added here, but should be added to the Framework class instead.
	 * These method stubs exist to allow forwarding to the Framework class pre-v5,
	 * and to ensure that method_exists() calls continue working indefinitely for existing modules.
	 * We know some modules at Vanderbilt require that, and it's safer for backward compatibility not to change any behavior.
	 */
	function getProjectSettings(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function setProjectSettings(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getSubSettings(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function setData(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
    /**
     * @psalm-taint-sink sql $sql
	 * @return \mysqli_result
     */
	function queryLogs($sql, $parameters = null){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function removeLogs(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getChoiceLabel(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getChoiceLabels(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function initializeJavascriptModuleObject(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getFirstEventId(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function isSurveyPage(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getPublicSurveyHash(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getPublicSurveyUrl(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function exitAfterHook(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function resetSurveyAndGetCodes(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function createPassthruForm(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getRecordId(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function setRecordId(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getValidFormEventId(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getSurveyId(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getRecordIdOrTemporaryRecordId(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getProjectId(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	/**
	 * @psalm-taint-sink sql $sql
	 * @return \mysqli_result
	 */
	function query($sql, $parameters = null){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function requireProjectId(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getProjectDetails(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getParticipantAndResponseId(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function generateUniqueRandomSurveyHash(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getModulePath(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getSettingConfig(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getUserSetting(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function setUserSetting(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function removeUserSetting(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function addAutoNumberedRecord(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function disableUserBasedSettingPermissions(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function setDAG(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function renameDAG(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function deleteDAG(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function createDAG(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getFieldLabel(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function saveFile(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function delayModuleExecution(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getConfig(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getModuleDirectoryName(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function setSystemSetting(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getSystemSetting(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function removeSystemSetting(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function setProjectSetting(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getProjectSetting(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function removeProjectSetting(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getUrl(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getModuleName(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getProjectAndRecordFromHashes(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getMetadata(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function saveData(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function saveInstanceData(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	/**
     * @psalm-taint-sink sql $sql
     */
	function getQueryLogsSql($sql){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getData(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function sendAdminEmail(){
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * No modules should ever have called these methods, but we'll leave the definition here
	 * for full backward compatibility just in case.
	 */
	protected function checkSettings(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	protected function isSettingKeyValid(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function areSettingPermissionsUserBased(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function hasPermission(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getSystemSettings(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
}
