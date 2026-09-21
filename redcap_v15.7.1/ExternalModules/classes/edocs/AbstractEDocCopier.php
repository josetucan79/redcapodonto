<?php namespace ExternalModules;

abstract class AbstractEDocCopier
{
	private $pid;
	private $prefixes;
	private $warnings = [];

	function __construct($pid, $prefixes = null)
	{
		$this->pid = ExternalModules::requireInteger($pid);
		$this->prefixes = $prefixes;
	}

	function getProjectId()
	{
		return $this->pid;
	}

	private function getPrefixes()
	{
		return $this->prefixes;
	}

	function run(): array
	{
		$pid = $this->getProjectId();

		// Temporarily override the pid so that hasProjectSettingSavePermission() works properly.
		$originalPid = ExternalModules::getProjectId();
		ExternalModules::setProjectId($pid);

		ExternalModules::requireDesignRights();

		$richTextSettingsByPrefix = $this->recreateEDocSettings();
		$this->recreateRichTextEDocs($pid, $richTextSettingsByPrefix);

		ExternalModules::setProjectId($originalPid);

		return $this->warnings;
    }

	/**
	 * @param string $selectClause
	 * @param null|string $key
	 */
	private function querySettings($selectClause, $key = null)
	{
		$q = ExternalModules::createQuery();
		$q->add("
			select $selectClause
			from redcap_external_module_settings where project_id = ?
		", $this->getProjectId());

		if($key !== null){
			$q->add('and `key` = ?', $key);
		}

		$prefixes = $this->getPrefixes();
		if($prefixes !== null){
			$questionMarks = ExternalModules::getQuestionMarks($prefixes);

			$q->add("and external_module_id in (
				select external_module_id
				from redcap_external_modules
				where directory_prefix in ($questionMarks) 
			)", $prefixes);
		}

		return $q->execute();
	}

	/**
	 * @return array[]
	 */
	private function recreateEDocSettings()
	{
		$pid = $this->getProjectId();

		$result = $this->querySettings('CAST(external_module_id as CHAR) as external_module_id, `key`');

		$richTextSettingsByPrefix = [];
		while($row = $result->fetch_assoc()){
			$prefix = ExternalModules::getPrefixForID($row['external_module_id']);
            $key = $row['key'];

            $details = ExternalModules::getSettingDetails($prefix, $key);
			$value = ExternalModules::getProjectSetting($prefix, $pid, $key);

            $type = $details['type'] ?? null;
            if($type === 'file'){
                $value = ExternalModules::processNestedSettingValues($value, function($value){
					return $this->recreateEDocIfExists($value);
				});

                ExternalModules::setProjectSetting($prefix, $pid, $key, $value);
            }
            else if($type === 'rich-text'){
                // Use the value returned by getProjectSetting() to handle arrays for subsettings/repeatables.
                $row['value'] = $value;
                $richTextSettingsByPrefix[$prefix][] = $row;
            }
		}

		return $richTextSettingsByPrefix;
	}

	/**
	 * @return void
	 *
	 * @param array[] $richTextSettingsByPrefix
	 */
	private function recreateRichTextEDocs($pid, $richTextSettingsByPrefix)
	{
		$results = $this->querySettings('CAST(external_module_id as CHAR) as external_module_id', ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST);
		
		while($row = $results->fetch_assoc()){
			$prefix = ExternalModules::getPrefixForID($row['external_module_id']);
			$files = ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST);
			$settings = &$richTextSettingsByPrefix[$prefix];

			foreach($files as &$file){
				$name = $file['name'];

				$oldId = $file['edocId'];
				$newId = $this->recreateEDocIfExists($oldId);
				if(empty($newId)){
					// The edocId was either invalid or the file has been deleted.  Just skip this one.
					continue;
				}

				$file['edocId'] = $newId;

				$handleValue = /**
				 * @return array|null|string
				 */
				function($value) use ($pid, $prefix, $oldId, $newId, $name, &$handleValue){
					if(gettype($value) === 'array'){
						for($i=0; $i<count($value); $i++){
							$value[$i] = $handleValue($value[$i]);
						}
					}
					else{ // it's a string
						$oldPidPlaceHolder = 'EDOC_COPIER_OLD_PID_PLACEHOLDER';
						$search = htmlspecialchars(ExternalModules::getRichTextFileUrl($prefix, $oldPidPlaceHolder, $oldId, $name));
						$search = preg_quote($search, '/');
						$search = str_replace($oldPidPlaceHolder, '[0-9]+', $search);

						$replace = htmlspecialchars(ExternalModules::getRichTextFileUrl($prefix, $pid, $newId, $name));
						$value = preg_replace("/$search/", $replace, $value);
					}

					return $value;
				};

				foreach($settings as $i=>$setting){
					$setting['value'] = $handleValue($setting['value']);
					$settings[$i] = $setting;
				}
			}

			ExternalModules::setProjectSetting($prefix, $pid, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST, $files);
		}

		foreach($richTextSettingsByPrefix as $prefix=>$settings){
			foreach($settings as $setting){
				ExternalModules::setProjectSetting($prefix, $pid, $setting['key'], $setting['value']);
			}
		}
	}

	private function recreateEDocIfExists($edocId)
	{
		if(empty($edocId)){
			// The stored id is already empty.
			return null;
		}

		$result = $this->recreateEDoc($edocId);
		if($result === null){
			return null;
		}

		$newEdocId = $result['newEdocId'];

		/**
		 * We cast to an int because ProjectCopyEDocCopier oddly returns a string.
		 */
		if(((int)$newEdocId) === 0){
			$this->warnings[] = ExternalModules::tt('em_manage_152', $result['name']);
		}

		return $newEdocId;
	}

	protected abstract function recreateEDoc($edocId);
}