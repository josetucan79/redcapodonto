<?php

namespace ExternalModules;

require_once __DIR__ . '/Project.php';
require_once __DIR__ . '/Records.php';
require_once __DIR__ . '/ProjectChild.php';
require_once __DIR__ . '/Form.php';
require_once __DIR__ . '/Field.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/ClassicalDataRetriever.php';
require_once __DIR__ . '/pseudo-queries/AbstractPseudoQuery.php';
require_once __DIR__ . "/pseudo-queries/LogPseudoQuery.php";
require_once __DIR__ . '/pseudo-queries/DataPseudoQuery.php';

use Exception;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use UIState;
use InvalidArgumentException;
use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;

class Framework
{
	public const NESTED_REPEATABLE_SETTING_FIX_FRAMEWORK_VERSION = 9;
	public const LOGGING_IMPROVEMENTS_FRAMEWORK_VERSION = 11;
	public const HIDDEN_SETTING_FIX_FRAMEWORK_VERSION = 13;
	public const SYSTEM_SUB_SETTINGS = 14;
	public const INCLUDE_BY_DEFAULT_IN_PROJECTS_WITH_MODULE_ENABLED_FRAMEWORK_VERSION = 15;
	public const TWIG_FRAMEWORK_VERSION = 16;

	public const REDCAP_PAGE_PREFIX = "<!DOCTYPE HTML>";
	public const LOGIN_PAGE_EXCERPT = "<input type='hidden' id='redcap_login_a38us_09i85' name='redcap_login_a38us_09i85'";

	private static $CSRF_DOUBLE_SUBMIT_COOKIE = null;
	private static $removeLogsLimit = null;

	/**
	 * The framework version
	 */
	private $VERSION;

	/**
	 * The module for which the framework is initialized
	 */
	private $module;

	/**
	 * @psalm-suppress PossiblyUnusedProperty
	 */
	public $records;

	private $recordId;
	private $userBasedSettingPermissions = true;

	/**
	 * Instance of twig to be used by module
	 * @var Environment
	 */
	private $twig;

	/**
	 * Constructor
	 * @param AbstractExternalModule $module The module for which the framework is initialized.
	 */
	public function __construct($module) {
		if (!($module instanceof AbstractExternalModule)) {
			throw new Exception(ExternalModules::tt("em_errors_70")); //= Initializing the framework requires a module instance.
		}

		$this->module = $module;
		$this->initialize();

		// Initialize language support (parse language files etc.).
		ExternalModules::initializeLocalizationSupport($module->PREFIX, $module->VERSION);

		$this->records = new Records($module);

		/**
		 * Disallow invalid configuration options at module instantiation (and enable) time.
		 * We don't want to perform these checks at getConfig() time, because it will break
		 * the list of modules available to enable.
		 */
		$this->checkSettings();
	}

	// This method is used by unit testing.
	/**
	 * @return void
	 */
	private function initialize() {
		$frameworkVersion = ExternalModules::getFrameworkVersion($this->getModuleInstance());
		if ($frameworkVersion > ExternalModules::getMaxSupportedFrameworkVersion()) {
			throw new Exception(ExternalModules::tt('em_errors_130', $frameworkVersion));
		} elseif ($frameworkVersion > 1) {
			$this->getModuleInstance()->framework = $this;
		}

		$this->VERSION = $frameworkVersion;
	}

	public function __call($methodName, $args) {
		/**
		 * Forwards to the Project object are restricted to v7 forward to avoid the following scenario:
		 * - A developer utilizes this feature and releases a module update.
		 * - Someone installs that module update on an older REDCap version that does not yet include this forward code.
		 * - The module crashes with a method not found error.
		 */
		if ($this->shouldForwardToProject($methodName)) {
			return call_user_func_array([$this->getProject(), $methodName], $args);
		}

		throw new \Exception("Call to undefined method: $methodName");
	}

	/**
	 * @return bool
	 */
	public function shouldForwardToProject($methodName) {
		return $this->VERSION >= 7 && method_exists(Project::class, $methodName);
	}

	/**
	 * @return void
	 */
	public function checkSettings() {
		$config = $this->getConfig();
		$systemSettings = $config['system-settings'] ?? null;
		$projectSettings = $config['project-settings'] ?? null;

		$hiddenReservedSettingKeys = [];
		foreach (ExternalModules::getReservedSettings() as $setting) {
			if (($setting['hidden'] ?? null) === true) {
				$hiddenReservedSettingKeys[$setting['key']] = true;
			}
		}

		$settingKeys = [];
		$checkSettings = function ($settings) use ($hiddenReservedSettingKeys, &$settingKeys, &$checkSettings): void {
			if ($settings === null) {
				return;
			}

			$keysToRemove = [];
			foreach ($settings as $details) {
				$key = $details['key'] ?? null;
				if (!$this->isSettingKeyValid($key)) {
					//= The '{0}' module has a setting named '{1}' that contains invalid characters. Only lowercase characters, numbers, and dashes are allowed.
					throw new Exception(ExternalModules::tt("em_errors_62", $this->getPrefix(), $key));
				}

				if (isset($settingKeys[$key]) || isset($hiddenReservedSettingKeys[$key])) {
					if (ExternalModules::isReservedSettingKey($key)) {
						//= The '{0}' setting key is reserved for internal use.  Please use a different setting key in your module.
						throw new Exception(ExternalModules::tt("em_errors_6", $key));
					} else {
						//= The '{0}' module defines the '{1}' setting multiple times!
						throw new Exception(ExternalModules::tt("em_errors_61", $this->getPrefix(), $key));
					}
				} else {
					$settingKeys[$key] = true;

					if (($details['allow-project-overrides'] ?? null) === true) {
						$keysToRemove[] = $key;
					}
				}

				if (($details['type'] ?? null) === 'sub_settings') {
					$checkSettings($details['sub_settings']);
				}
			}

			foreach ($keysToRemove as $key) {
				unset($settingKeys[$key]);
			}
		};

		$checkSettings($systemSettings);
		$checkSettings($projectSettings);
	}

	/**
	 * @return bool
	 */
	public function isSettingKeyValid($key) {
		// Only allow lowercase characters, numbers, dashes, and underscores to ensure consistency between modules (and so we don't have to worry about escaping).
		return !preg_match("/[^a-z0-9-_]/", $key ?? '');
	}

    /**
     * Gets the module's unique prefix
     * @return string
     */
	private function getPrefix() {
		return $this->getModuleInstance()->PREFIX;
	}

	//region Language features

	/**
	 * Returns the translation for the given language file key.
	 *
	 * @param string $key The language file key.
	 *
	 * Note: Any further arguments are used for interpolation. When the first additional parameter is an array, it's members will be used and any further parameters ignored.
	 *
	 * @return string The translation (with interpolations).
	 */
	public function tt($key) {
		// Get all arguments and send off for processing.
		return ExternalModules::tt_process(func_get_args(), $this->getPrefix(), false);
	}

	/**
	 * Transfers one (interpolated) or many strings (without interpolation) to the module's JavaScript object.
	 *
	 * @param mixed $key (optional) The language key or an array of language keys.

	 * Note: When a single language key is given, any number of arguments can be supplied and these will be used for interpolation. When an array of keys is passed, then any further arguments will be ignored and the language strings will be transfered without interpolation. If no key or null is passed, all language strings will be transferred.
	 *
	 * @return void
	 */
	public function tt_transferToJavascriptModuleObject($key = null) {
		// Get all arguments and send off for processing.
		ExternalModules::tt_prepareTransfer(func_get_args(), $this->getPrefix());
	}

	/**
	 * Adds a key/value pair directly to the language store for use in the JavaScript module object.
	 * Value can be anything (string, boolean, array).
	 *
	 * @param string $key The language key.
	 * @param mixed $value The corresponding value.
	 *
	 * @return void
	 */
	public function tt_addToJavascriptModuleObject($key, $value) {
		ExternalModules::tt_addToJSLanguageStore($key, $value, $this->getPrefix(), $key);
	}

	//endregion

	/**
	 * Gets all project settings as an array. Useful for cases when you may
	 * be creating a custom config page for the external module in a project.
	 * Each setting is formatted as: [ 'yourkey' => 'value' ]
	 * (in case of repeating settings, value will be an array).
	 * This return value can be used as input for setProjectSettings().
	 *
	 * @param int|null $pid
	 * @return array containing settings
	 */
	public function getProjectSettings($pid = null) {
		$pid = self::requireProjectId($pid);
		$prefix = $this->getPrefix();

		if ($this->VERSION < 5) {
			return ExternalModules::getProjectSettingsAsArray($prefix, $pid);
		}

		$vSettings = ExternalModules::getProjectSettingsAsArray($prefix, $pid, false);
		// Transform settings to match the output from ExternalModules::formatRawSettings,
		// i.e. remove 'value' keys, preserving the project values "one level up"
		$settings = [];
		foreach ($vSettings as $key => $values) {
			$settings[$key] = $values["value"];
		}
		return $settings;
	}

	/**
	 * Saves all project settings (to be used with getProjectSettings). Useful
	 * for cases when you may create a custom config page or need to overwrite all
	 * project settings for an external module.
	 *
	 * @param array $settings Array of project-specific settings
	 * @param int|null $pid
	 *
	 * @return void
	 */
	public function setProjectSettings($settings, $pid = null) {
		$pid = self::requireProjectId($pid);
		if ($this->VERSION >= 5) {
			ExternalModules::saveProjectSettings($this->getPrefix(), $pid, $settings);
		} else {
			// In older framework versions, this method existed but did nothing (besides require the $pid).
		}
	}

	/**
	 * @return array
	 */
	public function getProjectsWithModuleEnabled() {
		$includeEnabledByDefault = false;
		if ($this->VERSION >= static::INCLUDE_BY_DEFAULT_IN_PROJECTS_WITH_MODULE_ENABLED_FRAMEWORK_VERSION) {
			$includeEnabledByDefault = $this->getSystemSetting(ExternalModules::KEY_ENABLED);
		}

		$sql = "
			SELECT CAST(s.project_id AS CHAR) AS project_id
			FROM redcap_external_modules m
			JOIN redcap_external_module_settings s
				ON m.external_module_id = s.external_module_id
			JOIN redcap_projects p
				ON s.project_id = p.project_id
			WHERE
				m.directory_prefix = ?
				AND s.key = ?
				AND s.value = ?
		";

		$params = [$this->getPrefix(), ExternalModules::KEY_ENABLED];

		if ($includeEnabledByDefault) {
			$sql = "
				SELECT CAST(p.project_id as CHAR) AS project_id
				FROM redcap_projects p
				WHERE p.project_id NOT IN ($sql)
			";

			$params[] = 'false';
		} else {
			$params[] = 'true';
		}

		$sql .= ExternalModules::getActiveProjectWhereClauses();

		$results = $this->query($sql, $params);

		$pids = [];
		while ($row = $results->fetch_assoc()) {
			$pids[] = $row['project_id'];
		}

		return $pids;
	}

	public function callFromModuleInstance($name, $arguments) {
		if ($this->isSafeToForwardMethodToFramework($name)) {
			if (
				($name === 'getSubSettings' && $this->VERSION < 5)
				||
				($name === 'getData' && $this->VERSION < 7)
			) {
				$name .= '_v1';
			}

			return call_user_func_array([$this, $name], $arguments);
		}

		//= The following method does not exist: {0}
		throw new Exception(ExternalModules::tt("em_errors_69", $name));
	}

	/**
	 * @return array
	 */
	public function getSubSettings($key, $pid = null) {
		$settingConfig = $this->getSettingConfig($key);
		$settingsAsArray = ExternalModules::getProjectSettingsAsArray($this->getPrefix(), $this->getSubSettingProjectId($settingConfig, $pid));

		return $this->getSubSettings_internal($settingsAsArray, $settingConfig);
	}

	private function getSubSettingProjectId($setting, $pid) {
		if (
			$this->VERSION < static::SYSTEM_SUB_SETTINGS
			||
			$setting['project-setting']
		) {
			$pid = $this->requireProjectId($pid);
		} else {
			$pid = ExternalModules::SYSTEM_SETTING_PROJECT_ID;
		}

		return $pid;
	}

	/**
	 * @return array
	 *
	 * @param array[] $settingsAsArray
	 */
	private function getSubSettings_internal($settingsAsArray, $settingConfig) {
		$subSettings = [];
		foreach ($settingConfig['sub_settings'] as $subSettingConfig) {
			$subSettingKey = $subSettingConfig['key'];

			if (($subSettingConfig['type'] ?? null) === 'sub_settings') {
				// Handle nested sub_settings recursively
				$values = $this->getSubSettings_internal($settingsAsArray, $subSettingConfig);

				$recursionCheck = function ($value): bool {
					// We already know the value must be an array.
					// Recurse until we're two levels away from the leaves, then wrap in $subSettingKey.
					// If index '0' is not defined, we know it's a leaf since only setting key names will be used as array keys (not numeric indexes).
					return isset($value[0][0]);
				};
			} else {
				$values = $settingsAsArray[$this->getModuleInstance()->prefixSettingKey($subSettingKey)]['value'] ?? null;
				if ($values === null) {
					continue;
				} elseif (!is_array($values)) {
					/**
					 * This setting was likely moved from a plain old setting into sub-settings.
					 * Preserve the existing value as if it was saved under the current setting configuration.
					 */
					$values = [$values];
				}

				$recursionCheck = function ($value) use ($subSettingConfig): bool {
					// Only recurse if this is an array, and not a leaf.
					// If index '0' is not defined, we know it's a leaf since only setting key names will be used as array keys (not numeric indexes).
					// Using array_key_exists() instead of isset() is important since there could be a null value set.
					return
						is_array($value)
						&&
						array_key_exists(0, (array) $value)
						&&
						!(
							($subSettingConfig['repeatable'] ?? null)
							&&
							(
								$this->VERSION < static::NESTED_REPEATABLE_SETTING_FIX_FRAMEWORK_VERSION
								||
								!is_array($value[0])
							)
						);
				};
			}

			$formatValues = function ($values) use ($subSettingKey, $recursionCheck, &$formatValues) {
				for ($i = 0; $i < count($values); $i++) {
					$value = $values[$i];

					if ($recursionCheck($value)) {
						$values[$i] = $formatValues($value);
					} else {
						$values[$i] = [
							$subSettingKey => $value
						];
					}
				}

				return $values;
			};

			$values = $formatValues($values);

			$subSettings = ExternalModules::array_merge_recursive_distinct($subSettings, $values);
		}

		return $subSettings;
	}

	// This method is not in the main method documentation, but does exist in the docs for v5,
	// and should remain supported long term for backward compatibility.
	/**
	 * @return array[]
	 */
	public function getSubSettings_v1($key, $pid = null) {
		$keys = [];
		$config = $this->getSettingConfig($key);
		foreach ($config['sub_settings'] as $subSetting) {
			$keys[] = $this->getModuleInstance()->prefixSettingKey($subSetting['key']);
		}

		$rawSettings = ExternalModules::getProjectSettingsAsArray($this->getPrefix(), $this->getSubSettingProjectId($config, $pid));

		$subSettings = [];
		foreach ($keys as $key) {
			$values = $rawSettings[$key]['value'] ?? null;
			if ($values === null) {
				continue;
			}

			for ($i = 0; $i < count($values); $i++) {
				$value = $values[$i];
				$subSettings[$i][$key] = $value;
			}
		}

		return $subSettings;
	}

	public function getSQLInClause($columnName, $values) {
		if ($this->VERSION >= 4) {
			throw new Exception(ExternalModules::tt('em_errors_122'));
		}

		return ExternalModules::getSQLInClause($columnName, $values);
	}

	/**
	 * @return User
	 */
	public function getUser($username = null) {
		if (empty($username)) {
			$username = ExternalModules::getUsername();
			if ($username === null) {
				//= A username was not specified and could not be automatically detected.
				throw new Exception(ExternalModules::tt("em_errors_71"));
			}
		}

		return new User($this, $username);
	}

	/**
	 * @return Project
	 */
	public function getProject($project_id = null) {
		$project_id = $this->requireProjectId($project_id);
		return new Project($this, $project_id);
	}

	/**
	 * @return int
	 */
	public function requireInteger($mixed) {
		return ExternalModules::requireInteger($mixed);
	}

	/**
	 * @return string
	 */
	public function getJavascriptModuleObjectName() {
		return ExternalModules::getJavascriptModuleObjectName($this->getModuleInstance());
	}

	/**
	 * @return bool
	 */
	public function isRoute($routeName) {
		return ExternalModules::isRoute($routeName);
	}

	/**
	 * @return (int|string)|null
	 */
	public function getRecordIdField($pid = null) {
		return $this->getProject($pid)->getRecordIdField();
	}

	/**
	 * @return array
	 */
	public function getRepeatingForms($eventId = null, $projectId = null) {
		if ($eventId === null) {
			$eventId = $this->getEventId($projectId);
		}

		$result = $this->query('select * from redcap_events_repeat where event_id = ?', $eventId);

		$forms = [];
		while ($row = $result->fetch_assoc()) {
			$forms[] = $row['form_name'];
		}

		return $forms;
	}

	/**
	 * @return Query
	 */
	public function createQuery() {
		return ExternalModules::createQuery();
	}

	public function getEventId($projectId = null) {
		if (!$projectId) {
			$eventId = $_GET['event_id'] ?? null;
			if ($eventId) {
				return $eventId;
			}
		}

		return $this->getProject($projectId)->getEventId();
	}

	/**
	 * @return string
	 */
	public function getSafePath($path, $root = null) {
		$moduleDirectory = $this->getModulePath();
		if (!$root) {
			$root = $moduleDirectory;
		} elseif (!file_exists($root)) {
			$moduleDirectory = rtrim($moduleDirectory, '/\\');
			$root = $moduleDirectory . DIRECTORY_SEPARATOR . $root;
		}

		return ExternalModules::getSafePath($path, $root);
	}

	public function convertIntsToStrings($row) {
		return ExternalModules::convertIntsToStrings($row);
	}

	/**
	 * @deprecated
	 */
	public function isPage($path): bool {
		return $this->isREDCapPage($path);
	}

	public function isREDCapPage($path): bool {
		$path = APP_PATH_WEBROOT . $path;
		return strpos($_SERVER['REQUEST_URI'], $path) === 0;
	}

	public function isModulePage($path = null): bool {
		$page = $_GET["page"] ?? null;

		if (
			!starts_with($_SERVER['REQUEST_URI'], APP_URL_EXTMOD_RELATIVE . '?')
			||
			$this->getPrefix() !== ExternalModules::getPrefix()
			||
			empty($page)
		) {
			return false;
		}

		return $path === null || $path === $page;
	}

	public function createProject($title, $purpose, $project_note = null) {
		$userid = ExternalModules::getUsername();
		$userInfo = \User::getUserInfo($userid);
		if (!($userInfo['allow_create_db'] ?? false)) {
			throw new Exception("ERROR: You do not have Create Project privileges!");
		}

		return ExternalModules::createProject($title, $purpose, $project_note);
	}

	/**
	 * @return void
	 */
	public function importDataDictionary($project_id, $path, $delimiter = ",") {
		if (!file_exists($path)) {
			throw new \Exception("File not found for data dictionary import: $path");
		}

		$dictionary_array = $this->dataDictionaryCSVToMetadataArray($path, $delimiter);

		// Save data dictionary in metadata table
		$this->saveMetadataCSV($dictionary_array, $project_id);
	}

	/**
	 * @return (false|null|string)[][]|false
	 *
	 * @param null|string $returnType
	 */
	public function dataDictionaryCSVToMetadataArray($csvFilePath, $delimiter = ",") {
		return \Design::excel_to_array($csvFilePath, $delimiter);
	}

	// Save metadata when in DD array format
	private function saveMetadataCSV($metadata, $pid) {
		if (!is_array($metadata) || empty($metadata)) {
			throw new \Exception('The metadata specified is not valid.');
		}

		global $Proj;
		$originalProject = $Proj;
		$Proj = ExternalModules::getREDCapProjectObject($pid, false);
		list($errors, $warnings, $dd_array) = \MetaData::error_checking($metadata);
		$Proj = $originalProject;

		if (!empty($errors)) {
			ExternalModules::psalmSuppress($warnings);
			throw new \Exception("The following errors were encountered while trying to save project metadata: " . $this->escape(implode("\n", $errors)));
		}

		// Create a data dictionary snapshot, like data_dictionary_upload.php does
		if (!\MetaData::createDataDictionarySnapshot($pid)) {
			throw new \Exception("Error calling createDataDictionarySnapshot() for project $pid: " . db_error());
		}

		/**
		 * Temporarily hack the project status to make save_metadata() changes live immediately.
		 */
		ExternalModules::updateProjectCache(function ($cache) use ($pid) {
			$cache[$pid]->project['status'] = '0';
		});

		$errors = \MetaData::save_metadata($dd_array, false, false, $pid);

		// Ensure the metadata changes we just imported are respected, AND reset the 'status' after our temporary hack
		ExternalModules::clearProjectCache($pid);

		if (count($errors) > 0) {
			throw new \Exception("Failed to save metadata due to the following errors: " . $this->escape(implode("\n", $errors)));
		}
	}

	/**
	 * @psalm-suppress PossiblyUnusedParam
	 */
	public function saveMetadata($project_id, $metadata, $preventLogging = false) {
		/**
		 * This method has been documented for a long time, but never actually worked,
		 * and simply did nothing in some cases.
		 * The broken implementation has been removed, but the method remains in case
		 * anyone has a stray reference to it in their code that would break
		 * if it were removed.
		 */
	}

	/**
	 * @param string $metadata_table
	 * @param int $field_order
	 */
	private function insertFormStatusField($metadata_table, $project_id, $form_name, $field_order) {
		return $this->query("insert into $metadata_table (project_id, field_name, form_name, field_order, element_type, "
		. "element_label, element_enum, element_preceding_header) values (?,?,?,?,?,?,?,?)", [$project_id,$form_name . "_complete",$form_name,$field_order,'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', 'Form Status']);
	}

	/*
	** Give null value if equals "" (used inside queries)
	*/
	/**
	 * @param string $value
	 *
	 * @return null|string
	 */
	private function checkNull($value) {
		if ($value === "" || $value === null || $value === false) {
			return null;
		}
		return $value;
	}

	/**
	 * @param string $whereClause
	 * @param (false|mixed|string)[] $parameters
	 */
	public function countLogs($whereClause, $parameters) {
		$result = $this->queryLogs("select count(*) where $whereClause", $parameters);
		$row = $result->fetch_row();
		return $row[0];
	}

	// Pass through for any method was added in framework version 5 or greater.
	// We initially allowed pass through for any method without a framework version, but this caused problems when deploying modules using
	// the new/shorter syntax to older REDCap systems that didn't support it.
	// Requiring framework version 5 ensures that module authors write code compatible with older REDCap versions if their module is on an
	// older framework version (even if their REDCap instance supports the new syntax).
	/**
	 * @return bool
	 */
	public function isSafeToForwardMethodToFramework($name) {
		if (
			!$this->shouldForwardToProject($name)
			&&
			!method_exists($this, $name)
		) {
			return false;
		}

		if (
			$this->VERSION >= 5
			||
			method_exists($this->getModuleInstance(), $name) // For methods that have always been accessible from the module object prior to v5.
			||
			/**
			 * This method has always been callable from the module instance via __call(),
			 * but cannot be defined as a method like the others since it conflicts with a
			 * static var by the same name defined by one module.
			 */
			$name === 'log'
		) {
			return true;
		}

		/**
		 * These method have always been callable from AbstractExternalModule::__call(),
		 * even though they never existed on that class as explicit methods.
		 * They exist here rather than as method stubs in AbstractExternalModule
		 * to prevent potential conflicts with existing modules.
		 */
		return in_array($name, [
			'tt',
			'tt_transferToJavascriptModuleObject',
			'tt_addToJavascriptModuleObject',
		]);
	}

	/**
	 * @return void
	 */
	public function enableModule($pid, $prefix = null) {
		if (empty($pid)) {
			// TODO - tt
			throw new Exception("A project ID must be specified on which to enable the module.");
		}
		if ($prefix === null) {
			$prefix = $this->getPrefix();
		}
		$version = ExternalModules::getEnabledVersion($prefix);
		ExternalModules::enableForProject($prefix, $version, $pid);
	}

	public function disableModule($pid, $prefix = null) {
		if (empty($pid)) {
			// TODO - tt
			throw new Exception("A project ID must be specified for which to disable the module.");
		}
		if ($prefix === null) {
			$prefix = $this->getPrefix();
		}
		ExternalModules::setProjectSetting($prefix, $pid, ExternalModules::KEY_ENABLED, false);
	}

	/**
	 * Checks whether a project id is valid.
	 *
	 * Additional conditions can be via a second argument:
	 *  - TRUE: The project must actually exist (with any status).
	 *  - "DEV": The project must be in development mode.
	 *  - "PROD": The project must be in production mode.
	 *  - "AC": The project must be in analysis/cleanup mode.
	 *  - "DONE": The project must be completed.
	 *  - An array containing any of the states listed, e.g. ["DEV","PROD"]
	 *
	 * @param string|int $pid The project id to check.
	 * @param bool|string|array $condition Performs additional checks depending on the value (default: false).
	 * @return bool True/False depending on whether the project id is valid or not.
	 */
	public function isValidProjectId($pid, $condition = false) {
		return ExternalModules::isValidProjectId($pid, $condition);
	}

	/**
	* Checks whether a module is enabled for a project or on the system.
	*
	* @param string $prefix A unique module prefix.
	* @param string $projectId A project id (optional).
	* @return mixed False if the module is not enabled, otherwise the enabled version of the module (string).
	* @throws InvalidArgumentException
	**/
	public function isModuleEnabled($prefix, $pid = null) {
		return ExternalModules::isModuleEnabled($prefix, $pid);
	}

	/**
	* Gets a list of enabled modules for a project or on the system.
	*
	* @param string $pid A project id (optional).
	* @return array An associative array listing the enabled modules, with module prefix as key and version as value.
	* @throws InvalidArgumentException
	**/
	public function getEnabledModules($pid = null) {
		if ($pid !== null && !ExternalModules::isValidProjectId($pid)) {
			throw new InvalidArgumentException(ExternalModules::tt("em_errors_131")); //= Invalid value for project id!
		}
		return ExternalModules::getEnabledModules($pid);
	}

	/**
	 * Gets the status of the current or given project.
	 *
	 * Status can be one of the following:
	 * - DEV: Development mode
	 * - PROD: Production mode
	 * - AC: Analysis/Cleanup mode
	 * - DONE: Completed
	 *
	 * @param int|string|null $pid The project id (when omitted, the project id is determined from context).
	 * @return string|null The status of the project (or NULL in case the project does not exist).
	 */
	public function getProjectStatus($pid = null) {
		$pid = $this->requireProjectId($pid);
		return ExternalModules::getProjectStatus($pid);
	}

	public function getModuleInstance() {
		return $this->module;
	}

	public function getFieldNames($formName, $pid = null) {
		return $this->getProject($pid)->getForm($formName)->getFieldNames();
	}

	public function addOrUpdateInstances($newInstances, $uniqueInstanceField) {
		return $this->getProject()->addOrUpdateInstances($newInstances, $uniqueInstanceField);
	}

	/**
	 * @return void
	 */
	public function setData($record, $fieldName, $values) {
		$pid = self::requireProjectId();
		$eventId = $this->getEventId();

		if (!is_array($values)) {
			$values = [$values];
		}

		$this->query("SET AUTOCOMMIT=0", []);
		$this->query("BEGIN", []);

		$this->query(
			"DELETE FROM ".$this->getDataTable($pid)." where project_id = ? and event_id = ? and record = ? and field_name = ?",
			[$pid, $eventId, $record, $fieldName]
		);

		foreach ($values as $value) {
			$this->query(
				"INSERT INTO ".$this->getDataTable($pid)." (project_id, event_id, record, field_name, value) VALUES (?, ?, ?, ?, ?)",
				[$pid, $eventId, $record, $fieldName, $value]
			);
		}

		$this->query("COMMIT", []);
		$this->query("SET AUTOCOMMIT=1", []);
	}

	/**
	 * @return bool
	 *
	 * @param string $requiredVersion
	 */
	private function isPHPGreaterThan($requiredVersion) {
		return version_compare(PHP_VERSION, $requiredVersion, '>=');
	}

	public function getDAG($recordId) {
		$pid = self::requireProjectId();
		return \Records::getRecordDag($pid, $recordId);
	}

	/**
	 * @param string $sql
	 * @param (false|mixed|string)[]|null $parameters
	 * @psalm-taint-sink sql $sql
	 * @return \mysqli_result
	 */
	public function queryLogs($sql, $parameters = null) {
		if ($parameters === null && $this->VERSION < 6) {
			// Allow the parameters argument to be omitted.
			$parameters = [];
		}

		return $this->query($this->getQueryLogsSql($sql), $parameters);
	}

	public function removeLogs($sql, $parameters = null) {
		if (empty($sql)) {
			throw new Exception('You must specify a where clause.');
		}

		if ($parameters === null) {
			if ($this->VERSION < 6) {
				// Allow the parameters argument to be omitted.
				$parameters = [];
			} else {
				throw new Exception(ExternalModules::tt('em_errors_117'));
			}
		}

		$sql = $this->getQueryLogsSql("select log_id where $sql");
		$parsed = (new PHPSQLParser())->parse($sql);
		$limitAdded = !isset($parsed['LIMIT']);
		$parsed = $this->addRemoveLogsLimit($parsed);

		$sql = (new PHPSQLCreator())->create($parsed);

		/**
		 * We use an inner join to select which rows to delete based on ID because
		 * delete queries with joins do not allow a LIMIT clause.
		 * The inner query's join is required to include parameter values in the where clause.
		 *
		 * If we ever refactor this behavior, we should consider avoiding any use of ORDER BY
		 * per a case Flight Tracker ran into at Northwestern where a DELETE...ORDER BY log_id LIMIT 1
		 * query took 20 minutes when the table contains 187 million rows.  This query seemed to work
		 * fine on other systems with that many rows.  It is still unclear why it was so slow in
		 * Northwestern's case.
		 */
		$sql = "
            delete redcap_external_modules_log
            from redcap_external_modules_log
            inner join ($sql) redcap_external_modules_log_selection
                on redcap_external_modules_log.log_id = redcap_external_modules_log_selection.log_id
        ";

		if (strpos($sql, AbstractExternalModule::EXTERNAL_MODULE_ID_STANDARD_WHERE_CLAUSE_PREFIX) === false) {
			// An external_module_id must have been specified in the where clause, preventing the standard clause from being included.
			// This check also make sure that a bug in the framework doesn't remove logs for all modules (especially important when developing changes to log methods).
			throw new Exception("Specifying an 'external_module_id' in the where clause for removeLogs() is not allowed to prevent modules from accidentally removing logs for other modules.");
		} elseif (strpos($sql, 'redcap_external_modules_log.project_id') === false) {
			throw new Exception(ExternalModules::tt('em_errors_162'));
		}

		$query = $this->createQuery();
		$query->add($sql, $parameters);

		$totalAffectedRows = 0;
		do {
			$query->execute();
			$totalAffectedRows += $query->affected_rows;
		} while ($limitAdded && $query->affected_rows === (int) $parsed['LIMIT']['rowcount']);

		if ($this->VERSION <= 9) {
			return true;
		} else { // > 9
			return $totalAffectedRows;
		}
	}

	public function addRemoveLogsLimit($parsed) {
		if (!isset($parsed['LIMIT'])) {
			/**
			 * Delete time grows exponentially with the number of rows deleted at once.
			 * We ensure delete log queries contain a LIMIT clause to prevent this.
			 * This could be considered a bug/limitation of MySQL/InnoDB.
			 * We saw a case where the Flight Tracker module was hanging indefinitely
			 * while attempting to delete 187 million log rows, and preventing inserts
			 * to the log table from other modules in the meantime.
			 * In that case deleting 50k rows took 1 second, while deleting 75k took 8 seconds.
			 * We semi-arbitrarily choose 10k rows below.
			 */
			$parsed['LIMIT'] = $this->parseSQLClause('LIMIT', static::$removeLogsLimit ?? 10000);
		}

		return $parsed;
	}

	private function parseSQLClause($type, $content) {
		return (new PHPSQLParser())->parse("select 1 $type $content")[$type];
	}

	public static function setRemoveLogsLimit($limit) {
		static::$removeLogsLimit = $limit;
	}

	public function getCSRFToken() {
		if (!ExternalModules::shouldUseCookieforCSRFToken()) {
			return \System::getCsrfToken();
		}

		if (static::$CSRF_DOUBLE_SUBMIT_COOKIE === null) {
			/**
			 * This code to set the cookie exists inside getCSRFToken() to ensure that the cookie is ONLY changed
			 * on requests full page load requests (when getCSRFToken() is called).
			 * See the "Test module.ajax() After Legacy AJAX GET" example for a case that did not work until
			 * we moved CSRF cookie saving to this location.
			 */

			// Set up the token for the next request (or requests if using ajax).
			$newToken = bin2hex(random_bytes(40));

			savecookie('redcap_external_module_csrf_token', $newToken, 0, true);

			static::$CSRF_DOUBLE_SUBMIT_COOKIE = $newToken;
		}

		return static::$CSRF_DOUBLE_SUBMIT_COOKIE;
	}

	/**
	 * @return void
	 */
	public function checkCSRFToken($page) {
		if (in_array($page, $this->getConfig()['no-csrf-pages'])) {
			// Specifying an old framework version here is the simplest way to bypass CSRF checking,
			// while still making sure the $_POST var gets removed.
			$frameworkVersion = 1;
		} else {
			$frameworkVersion = $this->VERSION;
		}

		ExternalModules::checkCSRFToken($frameworkVersion);
	}

	/**
	 * @return string
	 *
	 * @param string $sql
	 *
	 * @psalm-taint-sink sql $sql
	 */
	public function getQueryLogsSql($sql) {
		$query = new LogPseudoQuery($this);
		return $query->getActualSQL($sql);
	}

	/**
	 * @return array
	 * For classical REDCap projects only (no longitudinal projects),
	 * this class replaces REDCap::getData().
	 * It is quickest for queries that don't exceed 40,000 data points.
	 * It's actually slower than REDCap::getData() for queries that
	 * dramatically exceed that number (e.g., 320k data points).
	 * Flight Tracker typically streams data one record at a time,
	 * so this class adequately serves that purpose.
	 * When 40,000 data points are not exceeded, a 50-100% speedup
	 * is observed on redcap.vumc.org compared to REDCap::getData().
	 * The resulting data structures are functionally equivalent
	 * in json-array format.
	 *
	 * @param int $projectId
	 *
	 * @param array $fields
	 *
	 * @param array $records
	 */
	public function getDataClassical($projectId, $fields, $records) {
		if (!is_array($fields) || !is_array($records)) {
			throw new Exception(ExternalModules::tt('em_errors_180'));
		}

		$retriever = new ClassicalDataRetriever($this->module, $projectId);
		return $retriever->getData($fields, $records);
	}

	public function getData_v1($projectId, $recordId, $eventId = "", $format = "array") {
		$data = \REDCap::getData($projectId, $format, $recordId);

		if ($eventId != "") {
			return $data[$recordId][$eventId];
		}
		return $data;
	}

	/**
	 * @psalm-suppress PossiblyUnusedParam
	 * @return false|string
	 */
	public function getData($projectId, $returnFormat, $records = null, $fields = null, $events = null, $groups = null, $combineCheckboxValues = false, $exportDataAccessGroups = false, $exportSurveyFields = false, $filterLogic = null) {
		if ($returnFormat !== 'json') {
			throw new Exception(ExternalModules::tt('em_errors_147'));
		}

		$ensureArray = function ($value): array {
			if (!is_array($value)) {
				$value = [$value];
			}

			return $value;
		};

		$escapeFieldNames = function ($strings) use ($ensureArray): array {
			$strings = $ensureArray($strings);

			$newStrings = [];
			foreach ($strings as $string) {
				if (preg_match('/[^a-z0-9_]/', $string) === 1) {
					throw new Exception(ExternalModules::tt('em_errors_153', $string));
				}

				// db_escape() should do nothing here, but let's leave it in for good measure.
				$newStrings[] = db_escape($string);
			}

			return $newStrings;
		};

		$recordIdFieldName = $this->getRecordIdField($projectId);

		$whereClauses = [];
		$parameters = [];
		if (!empty($records)) {
			$records = $ensureArray($records);

			$questionMarks = [];
			foreach ($records as $record) {
				$questionMarks[] = '?';
				$parameters[] = $record;
			}

			$whereClauses[] = "$recordIdFieldName in (" . implode(',', $questionMarks) . ")";
		}

		if (empty($fields)) {
			throw new Exception(ExternalModules::tt('em_errors_148'));
		} else {
			$fields = $escapeFieldNames($fields);
		}

		$unsupportedArgs = [
			'events' => null,
			'groups' => null,
			'combineCheckboxValues' => false,
			'exportDataAccessGroups' => false,
			'exportSurveyFields' => false,
		];

		foreach ($unsupportedArgs as $arg => $expectedValue) {
			if ($$arg !== $expectedValue) {
				$expectedValueString = json_encode($expectedValue);
				throw new Exception(ExternalModules::tt('em_errors_149', $expectedValueString, $arg));
			}
		}

		if (!empty($filterLogic)) {
			// Wrap in parenthesis so any "OR" clauses don't cause the other top-level clauses to be ignored.
			$whereClauses[] = "($filterLogic)";

			// Verify that the logic can be parsed without exception (bad logic will behave as if there is no logic in REDCap::getData()).
			$parser = new \LogicParser();
			$parser->parse($filterLogic);
		}

		$filterLogicFields = getBracketedFields($filterLogic);
		if (isset($filterLogicFields[$recordIdFieldName])) {
			throw new Exception(ExternalModules::tt('em_errors_150'));
		}

		// This is commented out because it's not quite right.
		// We just excluded form completion values from unit tests for now.
		// $recordIdOnlyArray = [$this->getRecordIdField($projectId)];
		// $filterLogicFields = array_keys(getBracketedFields($filterLogic));
		// if($fields === $recordIdOnlyArray && $filterLogicFields === $recordIdOnlyArray){
		//     $forms = $this->getRepeatingForms();
		//     foreach($forms as $form){
		//         $fields[] = $form . '_complete';
		//     }
		// }

		$sql = "select " . implode(',', $fields);
		if (!empty($whereClauses)) {
			$sql .= " where " . implode(' and ', $whereClauses);
		}

		$query = new DataPseudoQuery($this->getProject($projectId));
		$query->setGetDataCompatible(true);
		$sql = $query->getActualSQL($sql);
		$result = $this->query($sql, $parameters);

		$rows = [];
		while ($row = $result->fetch_assoc()) {
			if (!empty($row['redcap_repeat_instrument'])) {
				$instance = @$row['redcap_repeat_instance'];
				if ($instance !== '') {
					$instance = (int) $instance;
				}

				$row['redcap_repeat_instance'] = $instance;
			}

			$rows[] = $row;
		}

		return json_encode($rows);
	}

	// private function isExtraneousRow($expectedRow, $actualRow, $recordIdFieldName){
	//     if($expectedRow[$recordIdFieldName] !== $actualRow[$recordIdFieldName]
	//         ||
	//         $expectedRow['redcap_repeat_index'] !== ''
	//         ||
	//         $actualRow['redcap_repeat_index'] === ''
	//     ){
	//         return false;
	//     }

	//     foreach($expectedRow as $fieldName => $value){
	//         if($fieldName === $recordIdFieldName){
	//             continue;
	//         }

	//         if($value !== ''){
	//             return false;
	//         }
	//     }

	//     return true;
	// }

	/**
	 * @psalm-suppress PossiblyUnusedParam
	 * @return ((float|mixed|string)[]|bool)[]
	 */
	public function compareGetDataImplementations($projectId, $returnFormat, $records = null, $fields = null, $events = null, $groups = null, $combineCheckboxValues = null, $exportDataAccessGroups = null, $exportSurveyFields = null, $filterLogic = null) {
		$args = [$projectId, $returnFormat, $records, $fields, null, null, false, false, false, $filterLogic];

		$result = [];
		$execute = function ($target) use ($args): array {
			$startMemory = memory_get_usage();
			$startMemoryPeak = memory_get_peak_usage();
			$startTime = microtime(true);
			$results = call_user_func_array([$target, 'getData'], $args);
			$executionTime = microtime(true) - $startTime;
			$memoryPeakIncrease = memory_get_peak_usage() - $startMemoryPeak;

			gc_collect_cycles();
			$memoryLeaked = memory_get_usage() - $startMemory;

			return [
				'results' => json_decode($results, true),
				'execution-time' => $executionTime,
				'memory-peak-increase' => $memoryPeakIncrease / 1024 / 1024 . ' MB',
				'memory-leaked' => $memoryLeaked / 1024 / 1024 . ' MB'
			];
		};

		$result['sql'] = $execute($this);
		$result['php'] = $execute('REDCap');

		$expected = &$result['php']['results'];
		$actual = &$result['sql']['results'];

		$completeSuffix = '_complete';
		for ($i = 0; $i < count($expected); $i++) {
			foreach (array_keys($expected[$i]) as $field) {
				if (isset($actual[$i][$field])) {
					continue;
				}

				if (substr($field, -strlen($completeSuffix)) === $completeSuffix) {
					// This is likely a form completion field, which have some quirks.  Remove them for now.
					// It may be better to fix the quirks in REDCap::getData() rather than re-introduce those quirks to this new implementation.
					unset($expected[$i][$field]);
				}
			}
		}

		// $expectedIndex = 0;
		// $actualIndex = 0;
		// $identical = true;
		// $identicalExceptExtraneousRows = true;
		// while($expectedIndex < count($expected) && $actualIndex < count($actual)){
		//     $expectedRow = $actual[$expectedIndex];
		//     $actualRow = $actual[$actualIndex];

		//     if($this->isExtraneousRow($expectedRow, $actualRow, $recordIdFieldName)){
		//         $identical = false;
		//         $nonRepeatingResultsMissingFromNewImplementation[] = $expectedRow;
		//         $expectedIndex++;
		//         $expectedRow = $actual[$expectedIndex];
		//     }

		//     if($actualRow !== $expectedRow){
		//         $identical = false;
		//         $identicalExceptExtraneousRows = false;
		//         break;
		//     }

		//     $expectedIndex++;
		//     $actualIndex++;
		// }

		// if($identical !== ($expected === $actual)){
		//     var_export([
		//         'args' => $args,
		//         'php results' => $expected,
		//         'sql results' => $actual
		//     ]);
		//     throw new Exception('Inconsistent identical checks detected!');
		// }

		$result['identical'] = $expected === $actual;
		// $result['identical-excluding-extraneous-rows'] = $identicalExceptExtraneousRows;

		return $result;
	}

	/**
	 * Function that returns the label name from checkboxes, radio buttons, etc instead of the value
	 * @param $params, associative array
	 * @param null $value, (to support the old version)
	 * @param null $pid, (to support the old version)
	 * @return mixed|string, label
	 */
	public function getChoiceLabel($params, $value = null, $pid = null) {

		if (!is_array($params)) {
			$params = ['field_name' => $params, 'value' => $value, 'project_id' => $pid];
		}

		//In case it's for a different project
		if ($params['project_id'] != "") {
			$pid = $params['project_id'];
		} else {
			$pid = $this->requireProjectId();
		}

		$fieldName = str_replace('[', '', $params['field_name'] ?? '');
		$fieldName = str_replace(']', '', $fieldName);

		$dateFormats = [
			"date_dmy" => "d-m-Y",
			"date_mdy" => "m-d-Y",
			"date_ymd" => "Y-m-d",
			"datetime_dmy" => "d-m-Y h:i",
			"datetime_mdy" => "m-d-Y h:i",
			"datetime_ymd" => "Y-m-d h:i",
			"datetime_seconds_dmy" => "d-m-Y h:i:s",
			"datetime_seconds_mdy" => "m-d-Y h:i:s",
			"datetime_seconds_ymd" => "Y-m-d  h:i:s"
		];

		$recordId = $params['record_id'] ?? null;
		$eventId = $params['event_id'] ?? null;
		$value = $params['value'] ?? null;

		if (isset($recordId)) {
			/**
			 * This feature is considered deprecated.  It pulls a lot more data than necessary, and modules should do their own data lookups anyway.
			 * This function should really only check metadata, however, we've left this in place since Email Alerts and potentially other modules
			 * are still using this old feature.
			 */

			$data = \REDCap::getData($pid, "array", $recordId);

			if (array_key_exists('repeat_instances', $data[$recordId] ?? [])) {
				if (
					isset($data[$recordId]['repeat_instances'][$eventId][$params['survey_form']][$params['instance']][$fieldName])
					&& $data[$recordId]['repeat_instances'][$eventId][$params['survey_form']][$params['instance']][$fieldName] != ""
				) {
					//Repeat instruments
					$data_event = $data[$recordId]['repeat_instances'][$eventId][$params['survey_form']][$params['instance']];
				} elseif (
					isset($data[$recordId]['repeat_instances'][$eventId][''][$params['instance']][$fieldName])
					&& $data[$recordId]['repeat_instances'][$eventId][''][$params['instance']][$fieldName] != "") {
					//Repeat events
					$data_event = $data[$recordId]['repeat_instances'][$eventId][''][$params['instance']];
				} else {
					$data_event = $data[$recordId][$eventId];
				}
			} else {
				$data_event = $data[$recordId][$eventId] ?? null;
			}
		}

		$metadata = \REDCap::getDataDictionary($pid, 'array', false, $fieldName);
		$type = $metadata[$fieldName]['field_type'] ?? null;

		//event arm is defined
		if (empty($metadata)) {
			preg_match_all("/\[[^\]]*\]/", $fieldName, $matches);
			$event_name = str_replace('[', '', $matches[0][0]);
			$event_name = str_replace(']', '', $event_name);

			$fieldName = str_replace('[', '', $matches[0][1]);
			$fieldName = str_replace(']', '', $fieldName);
			$metadata = \REDCap::getDataDictionary($pid, 'array', false, $fieldName);
		}
		$label = "";
		if ($type == 'checkbox' || $type == 'dropdown' || $type == 'radio') {
			$project = ExternalModules::getREDCapProjectObject($pid);
			$other_event_id = $project->getEventIdUsingUniqueEventName($event_name ?? null);
			$choices = preg_split("/\s*\|\s*/", $metadata[$fieldName]['select_choices_or_calculations']);
			foreach ($choices as $choice) {
				$option_value = preg_split("/,/", $choice)[0];
				if ($value != "") {
					if (is_array($data_event[$fieldName] ?? null)) {
						foreach ($data_event[$fieldName] as $choiceValue => $multipleChoice) {
							if ($multipleChoice === "1" && $choiceValue == $option_value) {
								$label .= $this->filterChoiceLabelFromChoice($choice) . ", ";
							}
						} // strval used to convert ints to strs to pass === type check
					} elseif (strval($value) === $option_value) {
						$label = $this->filterChoiceLabelFromChoice($choice);
					}
				} elseif ($value === $option_value) {
					$label = $this->filterChoiceLabelFromChoice($choice);
					break;
				} elseif ($value == "" && $type == 'checkbox') {
					//Checkboxes for event_arms
					if ($other_event_id == "") {
						$other_event_id = $eventId;
					}
					if (isset($recordId) && $data[$recordId][$other_event_id][$fieldName][$option_value] == "1") {
						$label .= $this->filterChoiceLabelFromChoice($choice) . ", ";
					}
				}
			}
			//we delete the last comma and space
			$label = rtrim($label, ", ");
		} elseif ($type == 'truefalse') {
			if ($value == '1') {
				$label = "True";
			} elseif ($value == '0') {
				$label = "False";
			}
		} elseif ($type == 'yesno') {
			if ($value == '1') {
				$label = "Yes";
			} elseif ($value == '0') {
				$label = "No";
			}
		} elseif ($type == 'sql') {
			if (!empty($value)) {
				$sql = self::replaceDataTableVar($pid, $metadata[$fieldName]['select_choices_or_calculations']);
				$q = $this->query($sql, []);

				while ($row = $q->fetch_row()) {
					if ($row[0] == $value) {
						$label = $row[1];
						break;
					}
				}
			}
		} elseif (in_array($metadata[$fieldName]['text_validation_type_or_show_slider_number'] ?? null, array_keys($dateFormats)) && $value != "") {
			$label = date($dateFormats[$metadata[$fieldName]['text_validation_type_or_show_slider_number']], strtotime($value));
		}
		return $label;
	}

	private function replaceDataTableVar($pid, $sqlData) {
		// If a project_id is provided (e.g., [data-table:435]), then use it, otherwise use the current context's project_id
		$sql = str_replace("[data-table]", $this->getDataTable($pid), $sqlData);

		$search = '!\[data-table:(.*?)\]!';
		preg_match_all($search, $sql, $match);
		$sqlPids = array_unique($match[1]);
		foreach ($sqlPids as $slqPid) {
			$sql = str_replace('[data-table:'.$slqPid.']', $this->getDataTable($slqPid), $sql);
		}
		return $sql;
	}

	/**
	 * @return string[]
	 */
	public function getChoiceLabels($fieldName, $pid = null) {
		$choicesById = [];
		if (!isset($fieldName)) {
			throw new Exception('Field name cannot be blank');
		}

		$pid = $this->requireProjectId($pid);
		$dictionary = \REDCap::getDataDictionary($pid, 'array', false, [$fieldName]);
		$fieldType = $dictionary[$fieldName]['field_type'];

		if ($fieldType == "truefalse") {
			$choicesById[0] = "False";
			$choicesById[1] = "True";
		} elseif ($fieldType == "yesno") {
			$choicesById[0] = "No";
			$choicesById[1] = "Yes";
		} else {
			$choices = explode('|', $dictionary[$fieldName]['select_choices_or_calculations']);
			foreach ($choices as $choice) {
				$parts = explode(', ', $choice);
				$id = trim($parts[0]);
				$label = trim(substr($choice, strpos($choice, ",") + 1));
				$choicesById[$id] = $label;
			}
		}

		return $choicesById;
	}

	private function getRecordIdOrTemporaryRecordId() {
		$recordId = $this->getRecordId();
		if (empty($recordId)) {
			// Use the temporary record id if it exists.
			$recordId = ExternalModules::getTemporaryRecordId();
		}

		return $recordId;
	}


	#region JSMO Ajax Requests

	/**
	 * This sections implements a general purpose AJAX request mechanism for external modules.
	 * A ajax(action, payload) method is added to the JavascriptObjectModule (see Framework.php).
	 * Calls to this method will initiate a call to the server that will be handled by the
	 * corresponding module's implementation of the redcap_module_ajax hook.
	 * The framework will ensure that context is preserved and that basic security is taken
	 * care of (such as spoofing module prefixes, project ids, etc.).
	 */

	/** Name of the config.json setting required for making AJAX log calls */
	public const MODULE_ENABLE_AJAX_LOGGING_SETTING = "enable-ajax-logging";

	/** Name of the config.json setting required for logging in a no-auth context */
	public const MODULE_ENABLE_NOAUTH_LOGGING_SETTING = "enable-no-auth-logging";

	/**
	 * Checks whether AJAX logging is allowed in the module settings (config.json)
	 * Note: This will return true for framework < 11 modules
	 * @return bool
	 */
	public function isAjaxLoggingEnabled() {
		$config = $this->getConfig();
		$setting_name = self::MODULE_ENABLE_AJAX_LOGGING_SETTING;
		$enabled = isset($config[$setting_name]) && is_bool($config[$setting_name]) && $config[$setting_name] == true;
		return $enabled;
	}

	/**
	 * Checks whether no-auth logging is allowed in the module settings (config.json)
	 */
	public function requireNoAuthLoggingEnabled() {
		$config = $this->getConfig();
		$setting_name = self::MODULE_ENABLE_NOAUTH_LOGGING_SETTING;
		$enabled = isset($config[$setting_name]) && is_bool($config[$setting_name]) && $config[$setting_name] == true;
		if (!$enabled) {
			throw new Exception("The config.json setting '" . self::MODULE_ENABLE_NOAUTH_LOGGING_SETTING ."' must be set to 'true' in order to perform logging in a non-authenticated context.");
		}
	}

	/**
	 * Creates the settings necessary for the ajax() method of the JavascriptModuleObjects (i.e. the
	 * data that must be available inside the JSMO to initiate and "authenticate" callbacks to the
	 * server).
	 *
	 * The returned array will have three items:
	 * - verification: Encrypted context data used to verify an ajax request and to provide context
	 * - endpoint: The endpoint that should be called by the ajax() method
	 * - csrfToken: A standard REDCap CSRF token
	 *
	 * @return array{verification:string,endpoint:string,csrfToken:string} JSMO ajax settings
	 */
	public function getAjaxSettings() {
		$module = $this->module;
		$project_id = $this->getProjectId();
		$record = $this->getRecordIdOrTemporaryRecordId();
		$survey_hash = $this->getSurveyHash();
		$survey_queue_hash = $this->getSurveyQueueHash();
		$crypto = ExternalModules::initAjaxCrypto($this->getCSRFToken());
		// Gather context info
		if (ExternalModules::isNoAuth() || (ExternalModules::isSurveyPage() && (isset($_GET["__dashboard"]) || isset($_GET["__report"])))) {
			// Non-auth REDCap page, but not a survey; or a public dashboard or report
			// Other than that there is no further context
			$user = null;
			$record = null;
			$instrument = null;
			$event_id = null;
			$group_id = null;
			$response_id = "";
			$repeat_instance = 1;
		} elseif (!empty($survey_hash)) {
			// Survey
			$user = null; // Use null here to avoid [survey respondent], which we won't get in the ajax request
			// This is taken from Surveys/index.php (redcap_survey_page hook insertion point)
			$response_id = isset($_POST["__response_id__"]) ? $_POST["__response_id__"] : "";
			$instrument = $_GET["page"] ?? null;
			$event_id = $_GET["event_id"] ?? null;
			$group_id = (empty($GLOBALS["Proj"]->groups)) ? null : \Records::getRecordGroupId($project_id, $record);
			if (!is_numeric($group_id)) {
				$group_id = null;
			}
			$repeat_instance = $_GET["instance"] ?? 1;
		} elseif (!empty($survey_queue_hash)) {
			// Survey Queue
			$user = null; // Use null here to avoid [survey respondent], which we won't get in the ajax request
			$response_id = "";
			list($sq_pid, $record) = \Survey::checkSurveyQueueHash($survey_queue_hash);
			if ($project_id != $sq_pid) {
				throw new \Exception("Survey queue hash does not match project id!");
			}
			$instrument = null;
			$event_id = null;
			$group_id = null;
			$repeat_instance = 1;
		} else {
			// Authenticated REDCap page, inside or outside of a project context
			$user = ExternalModules::getUsername();
			// This is taken from DataEntry/index.php (redcap_data_entry_form hook injection point)
			$instrument = $_GET["page"] ?? null;
			$event_id = $_GET["event_id"] ?? null;
			$group_id = (empty($GLOBALS["Proj"]->groups)) ? null : \Records::getRecordGroupId($project_id, $record);
			if (!is_numeric($group_id)) {
				$group_id = null;
			}
			$response_id = "";
			$repeat_instance = $_GET["instance"] ?? 1;
		}
		// Fix user (survey respondent is always null)
		if ($user === \System::SURVEY_RESPONDENT_USERID) {
			$user = null;
		}

		// Build verification (and context) data; prefix, project id / survey_hash, and user will be used for verification in the AJAX handler
		$verification_data = [
			// Random value and timestamp - to ensure each encrypted verfication blob will be unique
			"random" => $crypto->genKey(),
			"timestamp" => time(),
			// Module id
			"prefix" => $module->PREFIX,
			"version" => $module->VERSION,
			// User info
			"user" => $user,
			// Context information
			"project_id" => $project_id,
			"record" => $record,
			"instrument" => $instrument,
			"event_id" => $event_id,
			"group_id" => $group_id,
			"survey_hash" => $survey_hash,
			"survey_queue_hash" => $survey_queue_hash,
			"response_id" => $response_id,
			"repeat_instance" => $repeat_instance,
			"page" => defined("PAGE") ? PAGE : null,
			"page_full" => defined("PAGE_FULL") ? PAGE_FULL : null,
		];
		// Determine the AJAX endpoint to use
		// For surveys, use the survey endpoint; otherwise, use jsmo-ajax.php
		if (self::isSurveyPage()) {
			// Before assigning the survey url, first check whether the page has actually been called via the survey url
			// If not, use the default endpoint. This will prevent CORS issues.
			$host = "//". ($_SERVER["HTTP_HOST"] ?? '');
			$base_url = strpos(APP_PATH_SURVEY_FULL, $host) !== false ? APP_PATH_SURVEY_FULL : (APP_PATH_WEBROOT_FULL."surveys/");
			$ajax_endpoint = $base_url . "?__passthru=ExternalModules&prefix={$module->PREFIX}&ajax=1";
			if (!empty($survey_hash)) {
				$ajax_endpoint .= "&s={$survey_hash}";
			} elseif (!empty($survey_queue_hash)) {
				$ajax_endpoint .= "&sq={$survey_queue_hash}";
			} else {
				// For public dashboards and reports, append the dashboard/report hash
				if (isset($_GET["__dashboard"])) {
					/**
					 * @psalm-taint-escape html
					 * @psalm-taint-escape has_quotes
					 */
					$ajax_endpoint .= "&__dashboard=" . $_GET["__dashboard"];
				} elseif (isset($_GET["__report"])) {
					/**
					 * @psalm-taint-escape html
					 * @psalm-taint-escape has_quotes
					 */
					$ajax_endpoint .= "&__report=" . $_GET["__report"];
				}
			}
        }
        else {
            $ajax_endpoint = APP_PATH_WEBROOT_FULL . "?__passthru=ExternalModules&prefix={$module->PREFIX}&ajax=1";
			if (!empty($project_id)) {
				$ajax_endpoint .= "&pid={$project_id}";
			}

			if (ExternalModules::isNoAuth()) {
				$ajax_endpoint .= "&NOAUTH";
			}
		}
		return [
			"verification" => $crypto->encrypt($verification_data),
			"endpoint" => $ajax_endpoint,
			"prefix" => $module->PREFIX,
			"version" => $module->VERSION,
			// Enable ajax logging only when enabled via enable-ajax-logging flag (framework v11+) and not in a no-auth context
			// unless the enable-no-auth-logging flag is set (all framework versions). This is used to signal failure without
			// issuing a server request (this will still be checked server-side).
		];
	}

	#endregion

	/**
	 * @return void
	 */
	public function initializeJavascriptModuleObject() {
		global $lang;

		$jsObject = ExternalModules::getJavascriptModuleObjectName($this->getModuleInstance());

		ExternalModules::tt_initializeJSLanguageStore();

		$pageUrlPlaceholder = 'some-page-path-that-would-not-ever-really-exist';

		try {
			// Setup endpoint and context/verification data for JSMO ajax
			$ajax_settings = $this->getAjaxSettings();
		} catch (\Throwable $t) {
			/**
			 * We ran into a scenario where the 'redcap_module_import_page_top' hook
			 * failed for the 'vanderbilt_cron_record_piping' module with the
			 * following error: "A token must be specified for ajax encryption".
			 * The exact cause could not be determined, but this try/catch was added to
			 * prevent any AJAX related issues from breaking other functionality.
			 */
			ExternalModules::errorLog($t->__toString());
			$ajax_settings = [];
		}

		?>
		<script>
			(function(){
				if(!window.ExternalModules){
					window.ExternalModules = {}
				}

				if(!ExternalModules.__ajaxQueue){
					/**
					 * We queue ajax requests because concurrent requests for the same browser tab
					 * sometimes fail because they trigger REDCap's duplicate query protection.
					 * See this issue for details: https://github.com/vanderbilt-redcap/external-module-framework/issues/619
					 */
					ExternalModules.__ajaxQueue = (function() {
						let queue = Promise.resolve();
						// Return the "enqueue" function that will add a new request to the queue
						return (requestFunc) => {
							const task = queue.then(requestFunc);
							queue = task.catch(function() {
								/**
								 * Ensure errors don't interrupt the queue.
								 * This error will already be written to the console via a previous catch() call.
								 */
							}); 
							return task;
						};
					})();

					/**
					 * Processes a module ajax request, JSMO.ajax(), using a queue
					 */
					ExternalModules.moduleQueuedAjax = function(ajaxSettings, action, payload) {
						//#region Helpers
						function formatModuleName() {
							return ajaxSettings.prefix + '_' + ajaxSettings.version;
						}
						function parseResponse(response) {
							/**
							 * We explode and join here so that the LOGIN_PAGE_EXCERPT is not directly included in this javascript code,
							 * or the following response.includes(loginPageExcerpt) call will incorrectly match itself in some cases.
							 * Set ajaxSettings.csrfToken to an invalid value to test this case.
							 */
							const loginPageExcerpt = <?=json_encode(explode("'", Framework::LOGIN_PAGE_EXCERPT))?>.join("'")

							try{
								return JSON.parse(response);
							}
							catch(error){
								if(
									response.startsWith(<?=json_encode(Framework::REDCAP_PAGE_PREFIX)?>)
									&&
									response.includes(loginPageExcerpt)
								){
									error = <?=json_encode(ExternalModules::tt('em_errors_171'))?>;
								}
								throw error;
							}
						}
						function handleError(reject, error) {
							if (action == <?=json_encode(ExternalModules::MODULE_AJAX_LOGGING_ACTION)?>) {
								console.error('Module LOG request error for '+formatModuleName()+' - Error:', error, ', Message:', payload.msg, ', Parameters:', payload.params);
							}
							else {
								console.error('Module AJAX request error for '+formatModuleName()+' - Error:', error, ', Action:', action, ', Payload:', payload);
							}
							reject(error);
						}
						function packageData() {
							const formData = new FormData();
							formData.append('verification', ajaxSettings.verification);
							formData.append('action', action);
							formData.append('payload', JSON.stringify(payload));
							// This is required because REDCap core erases 'redcap_csrf_token' on __passthru requests.
							formData.append('redcap_external_module_csrf_token', ajaxSettings.csrfToken);
							// This is required for non-__passthru ajax requests.
							formData.append('redcap_csrf_token', ajaxSettings.csrfToken);
							return formData;
						}
						function makeRequest() {
							return new Promise(function(resolve, reject) {
								fetch(ajaxSettings.endpoint, {
									method: 'POST',
									body: packageData()
								})
								.then(response => response.text())
								.then(response => {
									response = parseResponse(response);
									if (response['success'] === true) {
										ajaxSettings.verification = response['verification'];
										resolve(response.payload ?? null);
									}
									else {
										handleError(reject, response.error);
									}
								})
								.catch(err => {
									handleError(reject, err);
								});
							});
						}
						//#endregion
						
						// Add the request to the queue and return the promise
						return ExternalModules.__ajaxQueue(makeRequest);
					};
				}

				// Create the module object, and any missing parent objects.
				var parent = window
				;<?=json_encode($jsObject)?>.split('.').forEach(function(part){
					if(parent[part] === undefined){
						parent[part] = {}
					}

					parent = parent[part]
				})

				// Shorthand for the external module object.
				const module = <?=$jsObject?>

                // Settings for JSMO.ajax() calls
                const ajaxSettings = <?=json_encode($ajax_settings, JSON_FORCE_OBJECT)?>;

                // Adapted from here: https://onlinecode.org/javascript-equivalent-or-alternative-to-jquery-ready/
				const onReady = function(action){
					if (document.readyState !== 'loading'){
						action()
					} else {
						document.addEventListener('DOMContentLoaded', function(){
							// The action is not passed directly to prevent the event from getting passed into it
							action()
						})
					}
				}

				/** @var {Array} */
				const afterRenderQueue = []
				/** @var {bool} */
				var documentReady = false
				// We need to know whether the document ready event already occurred, because some actions need to be deferred until then
				onReady(function() {
					documentReady = true
					// Register any afterRender actions
					while (afterRenderQueue.length) {
						const action = afterRenderQueue.shift()
						module.afterRender(action)
					}
				})

				// Add methods.

                /**
                 * Executes an AJAX request against the EM Framework. The module calling this from the JSMO
                 * must implement the redcap_module_ajax method (otherwise, an 'Invalid request' response
                 * will be sent back). While the EM Framework will ensure basic security, modules should carefully
                 * inspect/validate any data received before acting on them).
                 * @param {string} action The action to be carried out (i.e. a name/identifier to distinguish the type of request) [required]
                 * @param {any} payload A custom payload [optional]
                 * @returns {Promise} A promise. The `then` and `catch` blocks should be implemented. The `then` callback will be called with the return value of the redcap_module_ajax method as argument.
                 */
                module.ajax = function(action, payload) {
                    if (typeof action !== 'string' || action == '') {
                        throw 'Invalid parameter: action must be a non-empty string';
                    }
                    if (typeof payload == 'undefined') {
                        payload = null;
                    }
                    ajaxSettings.csrfToken = <?=json_encode($this->getCSRFToken())?>;
                    return ExternalModules.moduleQueuedAjax(ajaxSettings, action, payload);
                }

                /** 
                 * @param {string} message The message to log
                 * @param {object} parameters (Optional) parameters
                 * @returns {Promise<Number>} A promise. The `then` and `catch` blocks should be implemented. The `then` callback will be called with the log id of the newly created log entry.
                 */
                module.log = function(message, parameters) {
                    return module.ajax('<?=ExternalModules::MODULE_AJAX_LOGGING_ACTION?>', {
                        msg: message,
                        params: parameters,
                    })
                }

				module.getUrl = function(path, noAuth){
					var url = <?=json_encode($this->getUrl("$pageUrlPlaceholder.php", false, true))?>;
					url = url.replace(<?=json_encode($pageUrlPlaceholder)?>, path)

					if(noAuth === true){
						url += '&NOAUTH'
					}

					return url;
				}

				module.getUrlParameters = function(){
					var search = location.search
					if(location.search[0] !== '?'){
						// There aren't any URL parameters
						return null
					}

					// Remove the leading question mark
					search = search.substring(1)

					var params = []
					var parts = search.split('&')
					parts.forEach(function(part){
						var innerParts = part.split('=')
						var name = innerParts[0]
						var value = null

						if(innerParts.length === 2){
							value = innerParts[1]
						}

						params[name] = value
					})

					return params
				}

				module.getUrlParameter = function(name){
					var params = this.getUrlParameters()
					return params[name]
				}

				module.isRoute = function(routeName){
					return this.getUrlParameter('route') === routeName
				}

				module.isImportPage = function(){
					return this.isRoute('DataImportController:index')
				}

				module.isImportReviewPage = function(){
					if(!this.isImportPage()){
						return false
					}

					return $('table#comptable').length === 1
				}

				module.isImportSuccessPage = function(){
					if(!this.isImportPage()){
						return false
					}

					let successFound = false
					$('#center > .green > b').each((index, row) => {
						if($(row).text() === <?=json_encode($lang["data_import_tool_133"])?>){ // 'Import Successful!'
							successFound = true
						}
					})

					return successFound
				}

				/**
				 * Constructs the full language key for an EM-scoped key.
				 * @private
				 * @param {string} key The EM-scoped key.
				 * @returns {string} The full key for use in $lang.
				 */
				module._constructLanguageKey = function(key) {
					return <?=json_encode(ExternalModules::EM_LANG_PREFIX . $this->getPrefix())?> + '_' + key
				}
				
				/**
				 * Gets and interpolate a translation.
				 * @param {string} key The key for the string.
				 * Note: Any further arguments after key will be used for interpolation. If the first such argument is an array, it will be used as the interpolation source.
				 * @returns {string} The interpolated string.
				 */
				module.tt = function (key) {
					var argArray = Array.prototype.slice.call(arguments)
					argArray[0] = this._constructLanguageKey(key)
					var lang = window.ExternalModules.$lang
					return lang.tt.apply(lang, argArray)
				}
				/**
				 * Adds a key/value pair to the language store.
				 * @param {string} key The key.
				 * @param {string} value The string value to add.
				 */
				module.tt_add = function(key, value) {
					key = this._constructLanguageKey(key)
					window.ExternalModules.$lang.add(key, value)
				}

				/**
				 * Registers a callback with MLM's onLangChanged event.
				 * In case MLM is not active, the action is called once (after the DOM is ready).
				 * @param {function(string,bool)} action The callback function.
				 */
				module.afterRender = function(action){
					if (documentReady) {
						// Determine whether MLM is in play
						if (typeof window.REDCap == 'undefined' || typeof window.REDCap.MultiLanguage == 'undefined' || typeof window.REDCap.MultiLanguage.isInitialized == 'undefined') {
							// MLM is inactive
							action()
						}
						else {
							// MLM is active
							const MLM = window.REDCap.MultiLanguage
							const alreadyInitialized = MLM.isInitialized()
							// Register the callback. MLM will call it each time it updates the page
							MLM.onLangChanged(action)
							// In case MLM was already initialized, call action now. Otherwise, MLM will do it
							if (alreadyInitialized) {
								action()
							}
						}
					}
					else {
						// Too early yet. Queue request for later
						if (!afterRenderQueue.includes(action)) {
							afterRenderQueue.push(action)
						}
					}
				}

				/**
				 * Indicates whether MLM is active on the current page.
				 * @returns {boolean}
				 */
				module.isMlmActive = function(){
					// Is MLM active?
					if (typeof window.REDCap == 'undefined' || 
					    typeof window.REDCap.MultiLanguage == 'undefined' || 
						typeof window.REDCap.MultiLanguage.isInitialized == 'undefined') {
						return false;
					}
					return true;
				}

				/**
				 * Returns the current MLM language. If MLM is not active, `false` is returned.
				 * @returns {string|null|false} The current language, `null` (if MLM is not initialized yet, or `false` (if MLM is not active).
				 */
				module.getCurrentLanguage = function(){
					// Is MLM active?
					if (!module.isMlmActive()) {
						return false;
					}
					// Has MLM been initialized?
					if (!window.REDCap.MultiLanguage.isInitialized()) {
						return null;
					}
					// Return the current language
					return window.REDCap.MultiLanguage.getCurrentLanguage();
				}
			})()
		</script>
		<?php
	}

	public function getFirstEventId($pid = null) {
		$pid = $this->requireProjectId($pid);
		$results = $this->query("
			select event_id
			from redcap_events_arms a
			join redcap_events_metadata m
				on a.arm_id = m.arm_id
			where a.project_id = ?
			order by arm_num, day_offset, descrip
		", [$pid]);

		$row = $results->fetch_assoc();
		return $row['event_id'];
	}

	public function log($message, $parameters = []) {
		// Is the no-auth logging enabled in config.json? This is required for calls to log in a no-auth context for framework version 11+.
		if ($this->VERSION >= static::LOGGING_IMPROVEMENTS_FRAMEWORK_VERSION && ExternalModules::isNoAuth()) {
			$this->requireNoAuthLoggingEnabled();
		}

		if (empty($message)) {
			throw new Exception("A message is required for log entries");
		} elseif (mb_strlen($message, '8bit') > ExternalModules::LOG_MESSAGE_SIZE_LIMIT) {
			throw new Exception(ExternalModules::tt('em_errors_159', ExternalModules::LOG_MESSAGE_SIZE_LIMIT));
		}

		if (!is_array($parameters)) {
			throw new Exception("The second argument to the log() method must be an array of parameters. A '" . gettype($parameters) . "' was given instead.");
		}

		foreach ($parameters as $name => $value) {
			$type = gettype($value);
			if (!in_array($type, ['boolean', 'integer', 'double', 'string', 'NULL'])) {
				throw new Exception("The type '$type' for the '$name' parameter is not supported.");
			} elseif (isset(AbstractExternalModule::$RESERVED_LOG_PARAMETER_NAMES_FLIPPED[$name])) {
				throw new Exception("The '$name' parameter name is set automatically and cannot be overridden.");
			} elseif ($value === null && $name !== "project_id") {
				// There's no point in storing null values in the database.
				// If a parameter is missing, queries will return null for it anyway.
				// Keep the project_id override, though, as it will be needed further on.
				unset($parameters[$name]);
			} elseif (strpos($name, "'") !== false) {
				throw new Exception("Single quotes are not allowed in parameter names.");
			} elseif (mb_strlen($name, '8bit') > ExternalModules::LOG_PARAM_NAME_SIZE_LIMIT) {
				throw new Exception(ExternalModules::tt('em_errors_160', ExternalModules::LOG_PARAM_NAME_SIZE_LIMIT));
			} elseif (mb_strlen($value ?? '', '8bit') > ExternalModules::LOG_PARAM_VALUE_SIZE_LIMIT) {
				throw new Exception(ExternalModules::tt('em_errors_161', ExternalModules::LOG_PARAM_VALUE_SIZE_LIMIT));
			}
		}

		$projectId = $parameters['project_id'] ?? null;

		// Auto-detect project id ONLY if not explicitly set to null via parameters
		if (array_key_exists("project_id", $parameters) && $parameters["project_id"] === null) {
			// Explicitly set to null, don't auto-detect
		} elseif (empty($projectId)) {
			$projectId = $this->getProjectId();

			if (empty($projectId)) {
				$projectId = null;
			}
		}

		$username = $parameters['username'] ?? null;
		if (empty($username)) {
			$username = ExternalModules::getUsername();
			;
		}

		if (isset($parameters['record'])) {
			$recordId = $parameters['record'];
		} else {
			$recordId = $this->getRecordIdOrTemporaryRecordId();
		}

		if (empty($recordId)) {
			$recordId = null;
		}

		$timestamp = $parameters['timestamp'] ?? null;
		$ip = $this->getIP($parameters['ip'] ?? null);

		// Remove parameter values that will be stored on the main log table,
		// so they are not also stored in the parameter table
		foreach (AbstractExternalModule::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE as $paramName) {
			unset($parameters[$paramName]);
		}

		$query = ExternalModules::createQuery();
		$query->add("
			insert into redcap_external_modules_log
				(
					timestamp,
					ui_id,
					ip,
					external_module_id,
					project_id,
					record,
					message
				)
			values
		");

		$query->add('(');

		if (empty($timestamp)) {
			$query->add('now()');
		} else {
			$query->add('?', $timestamp);
		}


		$query->add("
			,
			(select ui_id from redcap_user_information where username = ?),
			?,
			(select external_module_id from redcap_external_modules where directory_prefix = ?),
			?,
			?,
			?
		", [$username, $ip, $this->getPrefix(), $projectId, $recordId, $message]);

		$query->add(')');

		$query->execute();

		$logId = db_insert_id();
		if (!empty($parameters)) {
			$this->insertLogParameters($logId, $parameters);
		}

		return $logId;
	}

	private function getIP($ip) {
		$username = ExternalModules::getUsername();

		if (
			empty($ip)
			&& !empty($username) // Only log the ip if a user is currently logged in
			&& !$this->isSurveyPage() // Don't log IPs for surveys
		) {
			// The IP could contain multiple comma separated addresses (if proxies are used).
			// To accommodated at least three IPv4 addresses, the DB field is 100 chars long like the redcap_log_event table.
			$ip = \System::clientIpAddress();
		}

		if (empty($ip)) {
			$ip = null;
		}

		return $ip;
	}

	/**
	 * @return void
	 *
	 * @param array $parameters
	 */
	private function insertLogParameters($logId, $parameters) {
		$query = ExternalModules::createQuery();

		$query->add('insert into redcap_external_modules_log_parameters (log_id, name, value) VALUES');

		$addComma = false;
		foreach ($parameters as $name => $value) {
			if (!$addComma) {
				$addComma = true;
			} else {
				$query->add(',');
			}

			if (empty($name)) {
				throw new Exception(ExternalModules::tt('em_errors_116'));
			}

			// Limit allowed characters to prevent SQL injection when logs are queried later.
			ExternalModules::checkForInvalidLogParameterNameCharacters($name);

			$query->add('(?, ?, ?)', [$logId, $name, $value]);
		}

		$query->execute();
	}

	/**
	 * @return bool
	 */
	public function isSurveyPage() {
		return ExternalModules::isSurveyPage();
	}

	public function getPublicSurveyHash($pid = null) {
		$sql = "
			select p.hash 
            from redcap_surveys s
            join redcap_surveys_participants p
            on s.survey_id = p.survey_id
            join redcap_metadata  m
            on m.project_id = s.project_id and m.form_name = s.form_name
            where p.participant_email is null and m.field_order = 1 and s.project_id = ?
		";

		$result = $this->query($sql, [$pid]);
		if ($result->num_rows > 0) {
			$row = $result->fetch_assoc();
			$hash = @$row['hash'];
		} else {
			$hash = null;
		}

		return $hash;
	}

	/**
	 * @return null|string
	 */
	public function getPublicSurveyUrl($pid = null) {

		if (empty($pid)) {
			$pid = $this->getProjectId();
		}

		$hash = $this->getPublicSurveyHash($pid);

		$link = APP_PATH_SURVEY_FULL . "?s=$hash";
		if ($hash == null) {
			$link = null;
		}
		return $link;
	}

	/**
	 * @return void
	 */
	public function exitAfterHook() {
		ExternalModules::exitAfterHook();
	}

	/**
	 * @return void
	 */
	public function redirectAfterHook($url, $forceJS = false) {
		ExternalModules::redirectAfterHook($url, $forceJS);
	}

	/**
	 * Applies trusted context and checks overrides, then calls the log() method
	 * @param mixed $message Log message
	 * @param mixed $parameters Additional parameters (including overrides)
	 * @param mixed $verified_context Context
	 * @return int The id of the created log entry
	 */
	public function logAjax($message, $parameters, $verified_context) {
		// Apply overrides
		foreach ($parameters as $name => $value) {
			if (
				$name === 'record'
				&&
				(
					// Allow record IDs when authenticated.
					$verified_context["user"] != null
					||
					// Allow when it is identical to verified context data
					$value == $verified_context["record"]
				)
			) {
				continue;
			}
			if (in_array($name, AbstractExternalModule::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE)) {
				$subject = "External Module Log Request Failure ({$verified_context["prefix"]})";
				$body = "For security reasons, the '$name' parameter cannot be overridden via AJAX log requests. It can only be overridden by PHP log requests. You might want use the _JavaScript Module Object_'s ajax() method and implement your own logging in the '" . ExternalModules::MODULE_AJAX_HOOK_NAME . "' hook. If you do that, make sure to add security checking so that logs are only allowed in appropriate contexts.";

				$body .= "\n\n POST: " . json_encode([
					"message" => $message,
					"parameters" => $parameters,
				], JSON_PRETTY_PRINT);

				ExternalModules::handleError($subject, $body, $verified_context["prefix"]);

				throw new Exception($body);
			}
		}

		// Add some verified context parameters
		$parameters["username"] = $verified_context["user"];
		$parameters["project_id"] = $verified_context["project_id"];
		if (!isset($parameters["record"]) && !empty($verified_context["record"])) {
			$parameters["record"] = $verified_context["record"];
		}

		return $this->log($message, $parameters);
	}

	/**
	 * @return array|false
	 */
	public function resetSurveyAndGetCodes($projectId, $recordId, $surveyFormName = "", $eventId = "", $instance = 1) {
		list($surveyId, $surveyFormName) = $this->getSurveyId($projectId, $surveyFormName);

		## Invalid instance value, throw exception
		if (!is_numeric($instance)) {
			throw new Exception("Instance ".$instance." is not numeric on record: " . $recordId. ", survey: " . $surveyFormName);
		}

		## Validate surveyId and surveyFormName were found
		if ($surveyId == "" || $surveyFormName == "") {
			return false;
		}

		## Find valid event ID for form if it wasn't passed in
		if ($eventId == "") {
			$eventId = $this->getValidFormEventId($surveyFormName, $projectId);

			if (!$eventId) {
				return false;
			}
		}

		## Search for a participant and response id for the given survey and record
		list($participantId, $responseId) = $this->getParticipantAndResponseId($surveyId, $recordId, $eventId, $instance);

		## Create participant and return code if doesn't exist yet
		if ($participantId == "" || $responseId == "") {
			$hash = self::generateUniqueRandomSurveyHash();

			$participantId = ExternalModules::addSurveyParticipant($surveyId, $eventId, $hash);

			## Insert a response row for this survey and record
			$returnCode = generateRandomHash();
			ExternalModules::addSurveyResponse($participantId, $recordId, $returnCode, $instance);
		}
		## Reset response status if it already exists
		else {
			$sql = "SELECT CAST(p.participant_id as CHAR) as participant_id, p.hash, r.return_code, CAST(r.response_id as CHAR) as response_id, COALESCE(p.participant_email,'NULL') as participant_email
					FROM redcap_surveys_participants p, redcap_surveys_response r
					WHERE p.survey_id = ?
						AND p.participant_id = r.participant_id
						AND r.record = ?
						AND p.event_id = ?
						AND r.instance = ?";

			$query = self::createQuery();
			$query->add($sql, [$surveyId, $recordId, $eventId, $instance]);

			$q = $query->execute();
			$rows = [];
			while ($row = $q->fetch_assoc()) {
				$rows[] = $row;
			}

			## If more than one exists, delete any that are responses to public survey links
			if ($q->num_rows > 1) {
				foreach ($rows as $thisRow) {
					if ($thisRow["participant_email"] == "NULL" && $thisRow["response_id"] != "") {
						self::query("DELETE FROM redcap_surveys_response
								WHERE response_id = ?", $thisRow["response_id"]);
					} else {
						$row = $thisRow;
					}
				}
			} else {
				$row = $rows[0];
			}
			$returnCode = $row['return_code'];
			$hash = $row['hash'];
			$participantId = "";

			if ($returnCode == "") {
				$returnCode = generateRandomHash();
			}

			## If this is only as a public survey link, generate new participant row
			if ($row["participant_email"] == "NULL") {
				$hash = self::generateUniqueRandomSurveyHash();
				$participantId = ExternalModules::addSurveyParticipant($surveyId, $eventId, $hash);
			}

			// Set the response as incomplete in the response table, update participantId if on public survey link
			$q = ExternalModules::createQuery();
			$q->add("UPDATE redcap_surveys_participants p, redcap_surveys_response r
					SET r.completion_time = null,
						r.first_submit_time = '".date('Y-m-d H:i:s')."',
						r.return_code = ?", $returnCode);

			if ($participantId != "") {
				$q->add(", r.participant_id = ?", $participantId);
			}

			$q->add("WHERE p.survey_id = ?
						AND p.event_id = ?
						AND r.participant_id = p.participant_id
						AND r.record = ?
						AND r.instance = ?", [$surveyId, $eventId, $recordId, $instance]);

			$q->execute();
		}

		list($q, $r) = ExternalModules::setRecordCompleteStatus($projectId, $recordId, $eventId, $surveyFormName, 0);

		// Log the event (if value changed)
		if ($r && $q->affected_rows > 0) {
			\Logging::logEvent($sql, "redcap_data", "UPDATE", $recordId, "{$surveyFormName}_complete = '0'", "Update record");
		}

		return ["hash" => $hash, "return_code" => $returnCode];
	}

	/**
	 * @return false
	 */
	public function createPassthruForm($projectId, $recordId, $surveyFormName = "", $eventId = "") {
		$codeDetails = $this->resetSurveyAndGetCodes($projectId, $recordId, $surveyFormName, $eventId);

		$hash = $codeDetails["hash"];
		$returnCode = $codeDetails["return_code"];

		$surveyLink = APP_PATH_SURVEY_FULL . "?s=$hash";

		## Build invisible self-submitting HTML form to get the user to the survey
		echo "<html><body>
				<form name='passthruform' action='$surveyLink' method='post' enctype='multipart/form-data'>
				".($returnCode == "NULL" ? "" : "<input type='hidden' value='".$returnCode."' name='__code'/>")."
				<input type='hidden' value='1' name='__prefill' />
				</form>
				<script type='text/javascript'>
					document.passthruform.submit();
				</script>
				</body>
				</html>";
		return false;
	}

	public function getValidFormEventId($formName, $projectId) {
		if (!is_numeric($projectId) || $projectId == "") {
			return false;
		}

		$projectDetails = $this->getProjectDetails($projectId);

		if ($projectDetails["repeatforms"] == 0) {
			$sql = "SELECT CAST(e.event_id as CHAR) as event_id
					FROM redcap_events_metadata e, redcap_events_arms a
					WHERE a.project_id = ?
						AND a.arm_id = e.arm_id
					ORDER BY e.event_id ASC
					LIMIT 1";

			$q = ExternalModules::query($sql, [$projectId]);

			if ($row = $q->fetch_assoc()) {
				return $row['event_id'];
			}
		} else {
			$sql = "SELECT CAST(f.event_id as CHAR) as event_id
					FROM redcap_events_forms f, redcap_events_metadata m, redcap_events_arms a
					WHERE a.project_id = ?
						AND a.arm_id = m.arm_id
						AND m.event_id = f.event_id
						AND f.form_name = ?
					ORDER BY f.event_id ASC
					LIMIT 1";

			$q = ExternalModules::query($sql, [$projectId, $formName]);

			if ($row = $q->fetch_assoc()) {
				return $row['event_id'];
			}
		}

		return false;
	}

	/**
	 * @return (mixed|null)[]
	 */
	public function getSurveyId($projectId, $surveyFormName = "") {
		// Get survey_id, form status field, and save and return setting
		$query = ExternalModules::createQuery();
		$query->add("
			SELECT CAST(s.survey_id as CHAR) as survey_id, s.form_name, CAST(s.save_and_return as CHAR) as save_and_return
			FROM redcap_projects p, redcap_surveys s, redcap_metadata m
			WHERE p.project_id = ?
				AND p.project_id = s.project_id
				AND m.project_id = p.project_id
				AND s.form_name = m.form_name
		", [$projectId]);

		if ($surveyFormName != "") {
			if (is_numeric($surveyFormName)) {
				$query->add("AND s.survey_id = ?", $surveyFormName);
			} else {
				$query->add("AND s.form_name = ?", $surveyFormName);
			}
		}

		$query->add("
			ORDER BY s.survey_id ASC
			LIMIT 1
		");

		$r = $query->execute();
		$row = $r->fetch_assoc();

		$surveyId = $row['survey_id'] ?? null;
		$surveyFormName = $row['form_name'] ?? null;

		return [$surveyId,$surveyFormName];
	}

	public function getRecordId() {
		return $this->recordId;
	}

	/**
	 * @return void
	 */
	public function setRecordId($recordId) {
		$this->recordId = $recordId;
	}

	/**
	 * @return null|numeric-string
	 */
	public function getProjectId() {
		return ExternalModules::getProjectId();
	}

	/**
	 * @param string $sql
	 * @param array|null $parameters
	 *
	 * @psalm-taint-sink sql $sql
	 * @return \mysqli_result
	 */
	public function query($sql, $parameters = null) {
		if ($parameters === null && $this->VERSION < 4) {
			// Allow queries without parameters.
			$parameters = [];
		}

		return ExternalModules::query($sql, $parameters);
	}

	# function to enforce that a pid is required for a particular function
	/**
	 * @param int|null|string $pid
	 */
	public function requireProjectId($pid = null) {
		return ExternalModules::requireProjectId($pid);
	}

	/**
	 * @param numeric $projectId
	 */
	public function getProjectDetails($projectId) {
		$sql = "SELECT *
				FROM redcap_projects
				WHERE project_id = ?";

		$q = ExternalModules::query($sql, $projectId);

		$row = ExternalModules::convertIntsToStrings($q->fetch_assoc());

		return $row;
	}

	//change this to match what FHIR Services really needs!?!?!?

	/**
	 * This was never a supported/documented method, but a couple of modules are using it on the Vanderbilt test server as of 2/26/21.
	 *
	 * @return (null|string)[]
	 */
	public function getParticipantAndResponseId($surveyId, $recordId, $eventId = null, $instance = 1) {
		$result = $this->query('select project_id, form_name from redcap_surveys where survey_id = ?', $surveyId);
		$survey = $result->fetch_assoc();

		$responses = $this->getSurveyResponses([
			'pid' => $survey['project_id'] ?? null,
			'form' => $survey['form_name'] ?? null,
			'record' => $recordId,
			'event' => $eventId,
			'instance' => $instance
		]);

		$row = $responses->fetch_assoc();
		if ($row === null) {
			return [null, null];
		}

		$participantId = (string) $row['participant_id'];
		$responseId = (string) $row['response_id'];

		return [$participantId,$responseId];
	}

	public function generateUniqueRandomSurveyHash() {
		## Generate a random hash and verify it's unique
		do {
			$hash = generateRandomHash(10);

			$sql = "SELECT p.hash
						FROM redcap_surveys_participants p
						WHERE p.hash = ?";

			$result = self::query($sql, $hash);
			$hashExists = ($result->num_rows > 0);
		} while ($hashExists);

		return $hash;
	}

	/**
	 * @return string
	 */
	public function getModulePath() {
		return ExternalModules::getModuleDirectoryPath($this->getPrefix(), $this->getModuleVersion()) . DS;
	}

	public function getSettingConfig($key) {
		$config = $this->getConfig();
		foreach (['project-settings', 'system-settings'] as $type) {
			foreach ($config[$type] as $setting) {
				if ($key == $setting['key']) {
					$setting['project-setting'] = $type === 'project-settings';
					$setting['system-setting'] = $type === 'system-settings';

					return $setting;
				}
			}
		}

		return null;
	}

	/**
	 * Return a value from the UI state config. Return null if key doesn't exist.
	 * @param int/string $key key
	 * @return mixed - value if exists, else return null
	 */
	public function getUserSetting($key) {
		return UIState::getUIStateValue($this->getProjectId(), AbstractExternalModule::UI_STATE_OBJECT_PREFIX . $this->getPrefix(), $key);
	}

	/**
	 * Save a value in the UI state config
	 *
	 * @param int/string $key key
	 * @param mixed $value value for key
	 *
	 * @return void
	 */
	public function setUserSetting($key, $value) {
		UIState::saveUIStateValue($this->getProjectId(), AbstractExternalModule::UI_STATE_OBJECT_PREFIX . $this->getPrefix(), $key, $value);
	}

	/**
	 * Remove key-value from the UI state config
	 *
	 * @param int/string $key key
	 *
	 * @return void
	 */
	public function removeUserSetting($key) {
		UIState::removeUIStateValue($this->getProjectId(), AbstractExternalModule::UI_STATE_OBJECT_PREFIX . $this->getPrefix(), $key);
	}

	/**
	 * @return int
	 */
	public function addAutoNumberedRecord($pid = null) {
		$pid = $this->requireProjectId($pid);

		// The actual id passed to saveData() doesn't matter, since autonumbering will overwrite it.
		$importRecordId = 1;

		$data = [
			[
				ExternalModules::getRecordIdField($pid) => $importRecordId,
			]
		];

		$results = \REDCap::saveData(
			$pid,
			'json',
			json_encode($data),
			'normal',
			'YMD',
			'flat',
			null,
			true,
			true,
			true,
			false,
			true,
			[],
			false,
			true,
			false,
			true // Use auto numbering
		);

		if (!empty($results['errors'])) {
			throw new Exception("Error calling " . __METHOD__ . "(): " . json_encode($results, JSON_PRETTY_PRINT));
		}

		if (!empty($results['warnings'])) {
			ExternalModules::errorLog("Warnings occurred while calling " . __METHOD__ . "().  These should likely be ignored.  In fact, this error message could potentially be removed:" . json_encode($results, JSON_PRETTY_PRINT));
		}

		return (int) $results['ids'][$importRecordId];
	}

	public function areSettingPermissionsUserBased() {
		return $this->userBasedSettingPermissions;
	}

	/**
	 * @return void
	 */
	public function disableUserBasedSettingPermissions() {
		$this->userBasedSettingPermissions = false;
	}

	/**
	 * @return void
	 */
	public function setDAG($record, $groupId) {
		// We don't use REDCap::saveData() because it has some (perhaps erroneous) limitations for super users around setting DAGs on records that are already in DAGs.
		// It also doesn't seem to be aware of DAGs that were just added in the same hook call (likely because DAGs are cached before the hook is called).
		// Specifying a "redcap_data_access_group" parameter for REDCap::saveData() doesn't work either, since that parameter only accepts the auto generated names (not ids or full names).

		\Records::assignRecordToDag($record, $groupId, $this->getProjectId());
	}

	/**
	 * @return void
	 */
	public function renameDAG($dagId, $dagName) {
		$this->query(
			"update redcap_data_access_groups set group_name = ? where project_id = ? and group_id = ?",
			[$dagName, $this->requireProjectId(), $dagId]
		);
	}

	/**
	 * @return void
	 */
	public function deleteDAG($dagId) {
		$this->deleteAllDAGRecords($dagId);
		$this->deleteAllDAGUsers($dagId);

		$this->query(
			"DELETE FROM redcap_data_access_groups where project_id = ? and group_id = ?",
			[$this->requireProjectId(), $dagId]
		);
	}

	/**
	 * @return void
	 */
	private function deleteAllDAGRecords($dagId) {
		$pid = $this->requireProjectId();

		$records = $this->query(
			"SELECT record FROM ".$this->getDataTable($pid)." where project_id = ? and field_name = '__GROUPID__' and value = ?",
			[$pid, $dagId]
		);

		while ($row = $records->fetch_assoc()) {
			$record = $row['record'];
			$this->query("DELETE FROM ".$this->getDataTable($pid)." where project_id = ? and record = ?", [$pid, $record]);
		}

		$this->query("DELETE FROM ".$this->getDataTable($pid)." where project_id = ? and field_name = '__GROUPID__' and value = ?", [$pid, $dagId]);
	}

	/**
	 * @return void
	 */
	private function deleteAllDAGUsers($dagId) {
		$this->query("DELETE FROM redcap_user_rights where project_id = ? and group_id = ?", [$this->requireProjectId(), $dagId]);
	}

	public function createDAG($dagName) {
		$this->query(
			"insert into redcap_data_access_groups (project_id, group_name) values (?, ?)",
			[$this->requireProjectId(), $dagName]
		);
		return db_insert_id();
	}

	public function getFieldLabel($fieldName) {
		$pid = $this->requireProjectId();
		$dictionary = \REDCap::getDataDictionary($pid, 'array', false, [$fieldName]);
		return $dictionary[$fieldName]['field_label'];
	}

	/**
	 * @deprecated This method was never documented.  It should likely be removed, but is still used by some very old modules.
	 * @return void
	 */
	public function sendAdminEmail($subject, $message) {
		ExternalModules::sendAdminEmail($subject, $message, $this->getPrefix());
	}

	public function saveFile($path, $pid = null) {
		$pid = $this->requireProjectId($pid);

		$file = [];
		$file['name'] = basename($path);
		$file['tmp_name'] = $path;
		$file['size'] = filesize($path);

		return \Files::uploadFile($file, $pid);
	}

	private function getModuleVersion() {
		return $this->getModuleInstance()->VERSION;
	}

	# pushes the execution of the module to the end of the queue
	# helpful to wait for data to be processed by other modules
	# execution of the module will be restarted from the beginning
	# For example:
	# 	if ($data['field'] === "") {
	#		delayModuleExecution();
	#		return;       // the module will be restarted from the beginning
	#	}
	public function delayModuleExecution() {
		return ExternalModules::delayModuleExecution($this->getPrefix(), $this->getModuleVersion());
	}

	# checks whether the current External Module has permission for $permissionName
	/**
	 * @return bool
	 */
	public function hasPermission($permissionName) {
		return ExternalModules::hasPermission($this->getPrefix(), $this->getModuleVersion(), $permissionName);
	}

	# get the config for the current External Module
	# consists of config.json and filled-in values
	public function getConfig() {
		return ExternalModules::getConfig($this->getPrefix(), $this->getModuleVersion(), $this->getProjectId(), true);
	}

	# get the directory name of the current external module
	/**
	 * @return string
	 */
	public function getModuleDirectoryName() {
		return ExternalModules::getModuleDirectoryName($this->getModuleInstance());
	}

	public function prefixSettingKey($key) {
		return $this->getModuleInstance()->prefixSettingKey($key);
	}

	# a SYSTEM setting is a value to be used on all projects. It can be overridden by a particular project
	# a PROJECT setting is a value set by each project. It may be a value that overrides a system setting
	#      or it may be a value set for that project alone with no suggested System-level value.
	#      the project_id corresponds to the value in REDCap
	#      if a project_id (pid) is null, then it becomes a system value

	# Set the setting specified by the key to the specified value
	# systemwide (shared by all projects).
	/**
	 * @return void
	 */
	public function setSystemSetting($key, $value) {
		$key = $this->prefixSettingKey($key);
		ExternalModules::setSystemSetting($this->getPrefix(), $key, $value);
	}

	# Get the value stored systemwide for the specified key.
	public function getSystemSetting($key) {
		$key = $this->prefixSettingKey($key);
		return ExternalModules::getSystemSetting($this->getPrefix(), $key);
	}

	/**
	 * Gets all system settings as an array. Does not include project settings. Each setting
	 * is formatted as: [ 'yourkey' => ['system_value' => 'foo', 'value' => 'bar'] ]
	 *
	 * @return array
	 */
	public function getSystemSettings() {
		return ExternalModules::getSystemSettingsAsArray($this->getPrefix());
	}

	# Remove the value stored systemwide for the specified key.
	/**
	 * @return void
	 */
	public function removeSystemSetting($key) {
		$key = $this->prefixSettingKey($key);
		ExternalModules::removeSystemSetting($this->getPrefix(), $key);
	}

	# Set the setting specified by the key to the specified value for
	# this project (override the system setting).  In most cases
	# the project id can be detected automatically, but it can
	# optionaly be specified as the third parameter instead.
	/**
	 * @return void
	 */
	public function setProjectSetting($key, $value, $pid = null) {
		$pid = $this->requireProjectId($pid);
		$key = $this->prefixSettingKey($key);
		ExternalModules::setProjectSetting($this->getPrefix(), $pid, $key, $value);
	}

	# Returns the value stored for the specified key for the current
	# project if it exists.  If this setting key is not set (overriden)
	# for the current project, the system value for this key is
	# returned.  In most cases the project id can be detected
	# automatically, but it can optionally be specified as the third
	# parameter instead.
	public function getProjectSetting($key, $pid = null) {
		$pid = $this->requireProjectId($pid);
		$key = $this->prefixSettingKey($key);
		return ExternalModules::getProjectSetting($this->getPrefix(), $pid, $key);
	}

	# Remove the value stored for this project and the specified key.
	# In most cases the project id can be detected automatically, but
	# it can optionally be specified as the third parameter instead.
	/**
	 * @return void
	 */
	public function removeProjectSetting($key, $pid = null) {
		$pid = $this->requireProjectId($pid);
		$key = $this->prefixSettingKey($key);
		ExternalModules::removeProjectSetting($this->getPrefix(), $pid, $key);
	}

	/**
	 * @return string
	 *
	 * @param string $path
	 * @param bool $noAuth
	 * @param bool $useApiEndpoint
	 */
	public function getUrl($path, $noAuth = false, $useApiEndpoint = false) {
		$pid = $this->getProjectId();
		return ExternalModules::getUrl($this->getPrefix(), $path, $pid, $noAuth, $useApiEndpoint);
	}

	/**
	 * @return string
	 */
	public function getLinkIconHtml($link) {
		return ExternalModules::getLinkIconHtml($link, $this->VERSION >= 3, $this);
	}

	public function getModuleName() {
		return $this->getConfig()['name'];
	}

	public function getProjectAndRecordFromHashes($surveyHash, $returnCode) {
		$sql = "SELECT
					CAST(s.project_id as CHAR) as projectId,
					r.record as recordId,
					s.form_name as surveyForm,
					CAST(p.event_id as CHAR) as eventId
				FROM redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s
				WHERE p.hash = ?
					AND p.survey_id = s.survey_id
					AND p.participant_id = r.participant_id
					AND r.return_code = ?";

		$q = $this->query($sql, [$surveyHash, $returnCode]);

		$row = $q->fetch_assoc();

		if ($row) {
			return $row;
		}

		return false;
	}

	public function getMetadata($projectId, $forms = null) {
		return ExternalModules::getMetadata($projectId, $forms);
	}

	public function saveData($projectId, $recordId, $eventId, $data) {
		return \REDCap::saveData($projectId, "array", [$recordId => [$eventId => $data]]);
	}

	/**
	 * @param $projectId
	 * @param $recordId
	 * @param $eventId
	 * @param $formName
	 * @param $data array This must be in [instance => [field => value]] format
	 * @return array
	 */
	public function saveInstanceData($projectId, $recordId, $eventId, $formName, $data) {
		return \REDCap::saveData($projectId, "array", [$recordId => [$eventId => [$formName => $data]]]);
	}

	/**
	 * method to load twig and its dependencies.
	 *
	 * @param $templateDirectoryName - name of directory that views are stored in, without leading slash.
	 * @return void
	 * @throws Exception
	 */
	public function initializeTwig($templateDirectoryName = 'views'): void {
		if ($this->VERSION < static::TWIG_FRAMEWORK_VERSION) {
			throw new Exception("Cannot call 'initializeTwig()' on Framework version " . $this->VERSION . '.  Please upgrade to ' . static::TWIG_FRAMEWORK_VERSION);
		}
		if (!$this->twig) {
			require_once __DIR__ . '/FrameworkTwigExtensions.php';
			$loader = new FilesystemLoader($this->getSafePath($templateDirectoryName));
			$twig = new Environment($loader);
			$twig->addExtension(new FrameworkTwigExtensions($this));
			$this->twig = $twig;
		}
	}

	/**
	 * Method to get an instance of a Twig Environment.  Each module has its own instance of an Environment
	 * @return Environment
	 * @throws Exception
	 */
	public function getTwig(): Environment {
		if (!$this->twig) {
			throw new Exception("Twig has not been initialized yet. Call initializeTwig() first.");
		}
		return $this->twig;
	}

	/**
	 * Helper method to get the selected values for checkbox variables when working with data in the 'return_format' => 'json-array'.
	 *
	 * @param $record - REDCap Record array containing rows of "'$variableName___$labelKey'" syntax (from 'return_format' => 'json-array')
	 * @param $variable - which variable you want to return. Should match a variable in the REDCap project.
	 * @return array - array of selected checkboxes keyed by REDCap variable names
	 */
	public function getSelectedCheckboxes($record, $variable) {
		$selectedValues = [];
		foreach ($record as $labelKey => $isChecked) {
			if (str_contains($labelKey, '___')) {
				// get the variable name
				$labelParts = preg_split("/___/", $labelKey);
				$variableName = $labelParts[0];
				$variableValue = $labelParts[1];
				if ($variable == $variableName && $isChecked) {
					$selectedValues[] = $variableValue;
				}
			}
		}

		return $selectedValues;
	}

	/**
	 * It'd be great if we could add the examples in the slack message to the docs (maybe after we stop using markdown):
	 * https://victr.slack.com/archives/C2JM4HCJE/p1605564911257800
	 *
	 * @return bool
	 */
	public function throttle($sql, $parameters, $seconds, $maxOccurrences) {
		if (!is_array($parameters)) {
			$parameters = [$parameters];
		}

		$startTime = date('Y-m-d H:i:s', time() - $seconds);
		array_unshift($parameters, $startTime);

		$recentOccurrences = $this->countLogs('timestamp > ? and ' . $sql, $parameters);
		return $recentOccurrences >= $maxOccurrences;
	}

	/**
	 * @param (mixed|null)[] $args
	 */
	public function getSurveyResponses($args) {
		$args = array_merge([
			'pid' => $this->getProjectId()
		], $args);

		$pid = $args['pid'] ?? null;
		$event = $args['event'] ?? null;
		$form = $args['form'] ?? null;
		$record = $args['record'] ?? null;
		$instance = $args['instance'] ?? null;

		$query = $this->createQuery();
		$query->add("
			select *
            from redcap_surveys s
            join redcap_surveys_participants p
                on p.survey_id = s.survey_id
            join redcap_surveys_response r
                on r.participant_id = p.participant_id 
		");

		$clauses = [];
		$params = [];

		if ($pid !== null) {
			$clauses[] = "project_id = ?";
			$params[] = $pid;
		}

		if ($event !== null) {
			$clauses[] = "p.event_id = ?";
			$params[] = $event;
		}

		if ($form !== null) {
			$clauses[] = "form_name = ?";
			$params[] = $form;
		}

		if ($record !== null) {
			$clauses[] = "r.record = ?";
			$params[] = $record;
		}

		if ($instance !== null) {
			$clauses[] = "r.instance = ?";
			$params[] = $instance;
		}

		$query->add(" where " . implode(' and ', $clauses), $params);

		/**
		 * Ordering by participant_id is important since getParticipantAndResponseId() expects
		 * the first row returned to be the first participant.
		 * Keep in mind that there are sometimes two participants for a given event, record, & instance,
		 * due to a quirk of the way REDCap manages participants.  Here's Rob's explanation:
		 * Public surveys can have 1 or 2 rows. But other surveys in the project should not
		 * (unless they *used* to be the public survey at some point in the past).
		 * Each row in your join would correspond to a particular survey link/hash AND response,
		 * and public surveys occupy one row themselves while a private/unique survey link
		 * (after the record has been created) can occupy another row.
		 * In certain situations, either row may not exist, but in some situations, both exist.
		 * It’s not ideal, and has caused some issues over time because of this complexity.
		 * It is sort of a weird thing due to the evolution of surveys in REDCap over time
		 * (originally REDCap only allowed for one survey per project – i.e., the public survey).
		 * We probably wouldn’t design it that way if we re-built it all today.
		 */
		$query->add('
            order by p.participant_id asc
        ');

		return $query->execute();
	}

	/**
	 * @return string
	 */
	public function getSurveyHash() {
		return ExternalModules::getSurveyHash();
	}

	/**
	 * @return string
	 */
	public function getSurveyQueueHash() {
		return ExternalModules::getSurveyQueueHash();
	}

	/**
	 * @return false|string
	 */
	public function createTempFile() {
		return ExternalModules::createTempFile();
	}

	/**
	 * @return false|string
	 */
	public function createTempDir() {
		return ExternalModules::createTempDir();
	}

	/**
	 * @psalm-taint-escape html
	 * @psalm-taint-escape has_quotes
	 *
	 * @return null|string
	 */
	public function sanitizeAPIToken($token) {
		return preg_replace('/[^\dABCDEF]/', '', $token ?? '');
	}

	public function sanitizeFieldName($fieldName) {
		return ExternalModules::sanitizeFieldName($fieldName);
	}

	public function escape($value) {
		return ExternalModules::escape($value);
	}

	public function isSuperUser() {
		return ExternalModules::isSuperUser();
	}

	public function setProjectId($pid) {
		ExternalModules::setProjectId($pid);
	}

	public function isAuthenticated() {
		return !ExternalModules::isNoAuth() && ExternalModules::getUsername() !== null;
	}

	public function getDataTable($pid = null) {
		$pid = $this->requireProjectId($pid);
		return \REDCap::getDataTable($pid);
	}

    #region API Helpers

    /**
     * Constructs a HTTP status 200 response that can be returned by the redcap_module_api hook
     * @param string $body
     */
    function apiResponse($body = "") {
        return [
            "status" => 200,
            "body" => $body,
        ];
    }

    /**
     * Constructs an error response that can be returned by the redcap_module_api hook
     * @param string $error_message
     * @param int $status
     * @return array
     */
    function apiErrorResponse($error_message = "", $status = 500) {
        if (!is_string($error_message)) {
            throw new \InvalidArgumentException("Error message must be a string!");
        }
        return [
            "status" => $status * 1,
            "body" => $error_message,
        ];
    }

    /**
     * Constructs a file response that can be returned by the redcap_module_api hook
     * @param string $path The path to the file. The file must exist on the file system. Consider using createTempFile() to create the file.
     * @param string $filename The name of the file
     * @param string $type The type, e.g., text/plain or application/json
     * @return array
     */
    function apiFileResponse($path, $filename = "", $type = "text/plain") {
        return [
            "status" => 200,
            "file" => [
                "name" => "".$filename,
                "type" => "".$type,
                "path" => "".$path,
            ]
        ];
    }

    /**
     * Constructs a file response that can be returned by the redcap_module_api hook
     * @param string $contents The contents of the file.
     * @param string $filename The name of the file
     * @param string $type The type, e.g., text/plain or application/json
     * @return array
     */
    function apiFileContentsResponse($contents, $filename = "", $type = "text/plain") {
        return [
            "status" => 200,
            "file" => [
                "name" => "".$filename,
                "type" => "".$type,
                "contents" => $contents,
            ]
        ];
    }

    /**
     * Constructs a JSON response that can be returned by the redcap_module_api hook
     * @param mixed $data The data that is to be converted to JSON.
     * @param bool $force_object (Optional) Whether to add the JSON_FORCE_OBJECT flag.
     * @param int $flags (Optional) Custom flags for json_encode().
     * @return array
     */
    function apiJsonResponse($data, $force_object = false, $flags = 0) {
        if ($force_object) $flags |= JSON_FORCE_OBJECT;
        $flags |= JSON_UNESCAPED_UNICODE;
        $json = json_encode($data, $flags);
        return $this->apiResponse($json);
    }

    /**
     * Constructs a JSON response that can be returned by the redcap_module_api hook as a file
     * @param mixed $data The data that is to be converted to JSON.
     * @param string $filename The file name.
     * @param bool $force_object (Optional) Whether to add the JSON_FORCE_OBJECT flag.
     * @param int $flags (Optional) Custom flags for json_encode().
     * @return array
     * @throws Exception
     */
    function apiJsonFileResponse($data, $filename, $force_object = false, $flags = 0) {
        if ($force_object) $flags |= JSON_FORCE_OBJECT;
        $flags |= JSON_UNESCAPED_UNICODE;
        $json = json_encode($data, $flags);
        return $this->apiFileContentsResponse($json, $filename, "application/json");
    }

    /**
     * Constructs a CSV response that can be returned by the redcap_module_api hook
     * The data structure must be a nested array where the first dimension
     * represents the column names and the second dimension represents the
     * column values. The array can be staggered (i.e., not all columns must be of the same length).
     * Example:
     * [ "ColA" => [ "A1", "A2" ],  "ColB" => [ "B1", "B2", "B3" ] ]
     * will produce this CSV (comma set as delimiter):
     * ColA,ColB
     * A1,B1
     * A2,B2
     * ,B3
     * @param array $data The data that is to be converted to CSV.
     * @param string $delim (Optional) The delimiter (default: comma).
     * @param bool $add_bom (Optional) Whether to add the UTF8 byte order mark (default: false).
     * @return (int|string)[]
     */
    function apiCsvResponse($data, $delim = ",", $add_bom = false) {
        $csv = ExternalModules::createApiCsvString($data, $delim, $add_bom);
        return $this->apiResponse($csv);
    }

    /**
     * Constructs a CSV response that can be returned by the redcap_module_api hook as a file
     * The data structure must be a nested array where the first dimension
     * represents the column names and the second dimension represents the
     * column values. The array can be staggered (i.e., not all columns must be of the same length).
     * Example:
     * [ "ColA" => [ "A1", "A2" ],  "ColB" => [ "B1", "B2", "B3" ] ]
     * will produce this CSV (comma set as delimiter):
     * ColA,ColB
     * A1,B1
     * A2,B2
     * ,B3
     * @param array $data The data structure representing the CSV content.
     * @param string $filename The file name.
     * @param string $delim (Optional) The delimiter (default: comma).
     * @param bool $add_bom (Optional) Whether to add the UTF8 byte order mark (default: true).
     * @param int $status (Optional) The HTTP status code (default: 200).
     * @return array
     * @throws Exception
     */
    function apiCsvFileResponse($data, $filename, $delim = ",", $add_bom = true, $status = 200) {
        $csv = ExternalModules::createApiCsvString($data, $delim, $add_bom);
        return $this->apiFileContentsResponse($csv, $filename, "text/csv", $status);
    }

    #endregion

    function validateS3URL($url){
		$parts = parse_url($url);
		if (ends_with($parts['host'] ?? '', 's3.amazonaws.com')) {
			return $url;
		}

		throw new \Exception(ExternalModules::tt('em_errors_179', $url));
	}

	public function getSurveyLinkNewInstance($this_form, $record, $pid = null) {
		$pid = $this->requireProjectId($pid);
		$Proj = ExternalModules::getREDCapProjectObject($pid);
		$event_id = $Proj->firstEventId;

		if (empty($this_form)) {
			throw new Exception("A form is required to get the survey link");
		}

		if (empty($record)) {
			throw new Exception("A record is required to get the survey link");
		}

		// For new-instance, we need $this_form for context
		if ($Proj->isRepeatingEvent($event_id)) {
			$formInstances = array_keys(\RepeatInstance::getRepeatEventInstanceList($record, $event_id, $Proj));
		} else {
			$formInstances = array_keys(\RepeatInstance::getRepeatFormInstanceList($record, $event_id, $this_form, $Proj));
		}
		$this_instance = count($formInstances) ? (max($formInstances) + 1) : 1;

		if ($record != null) {
			// Get link using record
			$link = \REDCap::getSurveyLink($record, $this_form, $event_id, $this_instance, $Proj->project_id, false);
		}
		$link = $link ?? "";

		//For new-instance, append &new to enforce it to always be a new instance
		$link .= "&new";

		return $link;
	}

	/**
	 * Helper method to match a choice selection to its corresponding choice label by removing the "1,..." from the str.
	 * @param string $choice
	 * @return string
	 */
	private function filterChoiceLabelFromChoice($choice): string {
		return trim(preg_split("/^(.+?),/", $choice)[1]);
	}

	public function loadREDCapJS() {
		// REDCap pages includes two different bundle.js files. These files are not the same and provide different functionality.
		?>
        <script src='<?=APP_PATH_WEBPACK?>js/bundle.js'></script>
        <script src='<?=APP_PATH_JS?>Libraries/bundle.js'></script>
        <?php
	}

	public function loadREDCapCSS() {
		$htmlPage = new \HtmlPage();
		// Add all stylesheets
		?>
        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function()
                let em_css_link;
                <?php
					foreach ($htmlPage->stylesheets as $this_css) {
						// Cache-busting
						$this_css['href'] = $htmlPage->CacheBuster($this_css['href']);
						// Output to HEAD
						?>
                            em_css_link = document.createElement("link");
                            em_css_link.rel = "stylesheet";
                            em_css_link.href = "<?=$this_css['href']?>";
                            em_css_link.media = "<?=$this_css['media']?>";
                            document.head.appendChild(em_css_link);
                        <?php
					}
		?>
            });
        </script>
        <?php
		// Not sure if necessary but may as well clean up the variable after use
		unset($htmlPage);
	}

	public function loadBootstrap() {
		?>
		<link rel="stylesheet" href="<?=APP_PATH_WEBROOT?>Resources/webpack/css/bootstrap.min.css" />
		<script src='<?=APP_PATH_WEBROOT?>Resources/webpack/js/bootstrap.min.js'></script>
		<?php
	}
}
