<?php namespace ExternalModules;

class TestModuleTwo extends AbstractExternalModule {
	const API_FAILURE_MESSAGE = 'This API request is not kosher.';

	public $framework;
	public $testHookArguments;

	function redcap_pdf($project_id, $metadata, $data){
		$metadata[] = 'metadata added by ' . $this->PREFIX;
		$data[] = 'data added by ' . $this->PREFIX;

		return [
			'metadata' => $metadata,
			'data' => $data
		];
	}

	function redcap_module_api_before($project_id, $post){
		$this->testHookArguments = func_get_args();

		if(in_array($this->PREFIX, $post['prefixes_that_return_errors'])){
			return static::API_FAILURE_MESSAGE;
		}
	}
}
