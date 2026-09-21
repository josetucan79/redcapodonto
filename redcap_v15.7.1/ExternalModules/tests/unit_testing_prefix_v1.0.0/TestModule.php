<?php namespace TestModule;

use Exception;
use ExternalModules\ExternalModules;

require_once __DIR__ . '/../unit_testing_prefix_two_v1.0.0/TestModuleTwo.php';

class TestModule extends \ExternalModules\TestModuleTwo {
	public $recordIdFromGetRecordId;
	public $function;
	public $pageLoadOutput;
	private $linkCheckDisplayReturnValue;
	private $settingKeyPrefix;

	function getModulePath()
	{
		return __DIR__;
	}

	function redcap_test()
	{
		$this->testHookArguments = func_get_args();
	}

	function hook_legacy_prefix()
	{
		$this->testHookArguments = func_get_args();
	}

	function redcap_email()
	{
		$this->testHookArguments = func_get_args();
	}

	function redcap_test_call_function($function = null){
		// We must check if the arg is callable b/c it could be cron attributes for a cron job.
		if(!is_callable($function)){
			$function = $this->function;
		}

		$function($this);
	}
	
	function redcap_every_page_test()
	{
		call_user_func_array([$this, 'redcap_test'], func_get_args());
	}

	function redcap_save_record()
	{
		$this->recordIdFromGetRecordId = $this->getRecordId();
	}

	protected function getSettingKeyPrefix()
	{
		if($this->settingKeyPrefix){
			return $this->settingKeyPrefix;
		}
		else{
			return parent::getSettingKeyPrefix();
		}
	}

	function setSettingKeyPrefix($settingKeyPrefix)
	{
		$this->settingKeyPrefix = $settingKeyPrefix;
	}

	function redcap_module_link_check_display($project_id, $link){
		// This block behaves like a test assertion.
		if(ExternalModules::getProjectId() !== $project_id){
			throw new Exception('The expected project ID was not passed to redcap_module_link_check_display()!');
		}

		if($this->linkCheckDisplayReturnValue !== null){
			return $this->linkCheckDisplayReturnValue;
		}

		return parent::redcap_module_link_check_display($project_id, $link);
	}

	function setLinkCheckDisplayReturnValue($value){
		$this->linkCheckDisplayReturnValue = $value;
	}

	function redcap_module_ajax(){
		
	}

	function redcap_module_api(){

	}
}
