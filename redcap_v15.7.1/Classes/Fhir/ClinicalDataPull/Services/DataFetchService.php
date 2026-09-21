<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Services;


use Project;
use Records;
use Language;
use Exception;
use DynamicDataPull;
use Vanderbilt\REDCap\Classes\Fhir\FhirData;
use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroup;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\RecordAdapter;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\ValueObjects\TemporalMapping;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataCdpDecorator;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\ValueObjects\FieldInfoCollection;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataEmailDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataVandyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataCustomDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataAdverseEventDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataCapabilitiesDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertiesDecorator;

class DataFetchService
{
    /**
     *
     * @var DynamicDataPull
     */
    private $ddp;
    private $project_id;
    private $user_id;
    private $Proj;
    private $realtime_webservice_type;

    /**
     * cache field mappings for reuse
     *
     * @var array
     */
    private $field_mappings;

    const WEBSERVICE_TYPE_FHIR = 'FHIR';
    const WEBSERVICE_TYPE_CUSTOM = 'CUSTOM';

    /**
     *
     * @param DynamicDataPull $ddp
     * @param integer $project_id
     * @param integer|null $user_id
     */
    public function __construct(DynamicDataPull $ddp, $project_id, $user_id=null)
    {
        $this->ddp = $ddp;
        $this->project_id = $project_id;
        $this->user_id = $user_id;
        $this->Proj = new Project($this->project_id); // Assuming Project is an existing class
        $this->realtime_webservice_type = $this->Proj->project['realtime_webservice_type'];
    }

    public function fetchData(
        $record_identifier_rc,
        $event_id,
        $record_identifier_external,
        $day_offset,
        $day_offset_plusminus,
        $form_data = [],
        $instance = 0,
        $repeat_instrument = ""
    )
    {
        // 1. Validation and Initialization
        $this->ensureOpenSSLExtension();

        // 2. Data Mapping and Preparation
        $mappings = $this->getMappedFields(); // Use existing method
        $temporal_data = $this->getTemporalData($record_identifier_rc, $form_data, $event_id, $instance, $repeat_instrument);

        // Build field info for the web service request (FhirMapping[])
        $fieldinfoCollection = FieldInfoCollection::fromParams(
            $this->Proj,
            $mappings,
            $temporal_data,
            $day_offset,
            $day_offset_plusminus,
            $record_identifier_rc
        );
        $field_info = $fieldinfoCollection->getFieldInfo();


        $response_data_array = $this->fetchDataFromWebService($record_identifier_external, $field_info);
        return $response_data_array;
    }

    private function ensureOpenSSLExtension()
    {
        if (!extension_loaded('openssl')) {
            throw new Exception('OpenSSL extension is not installed.');
        }
    }

    // Set interval based on type
    private function getDataFetchInterval()
    {
        global $realtime_webservice_data_fetch_interval, $fhir_data_fetch_interval;

        $data_fetch_interval = ($this->realtime_webservice_type == self::WEBSERVICE_TYPE_FHIR)
            ? $fhir_data_fetch_interval
            : $realtime_webservice_data_fetch_interval;

        // Ensure a valid interval
        if (!(is_numeric($data_fetch_interval) && $data_fetch_interval >= 1)) {
            $data_fetch_interval = 24;
        }
        return $data_fetch_interval;
    }

    /**
	 * GET LIST OF FIELDS ALREADY MAPPED TO EXTERNAL SOURCE FIELDS
	 * Return array of fields with external source field as 1st level key, REDCap event_id as 2nd level key,
	 * REDCap field name as 3rd level key,and sub-array of attributes (temporal_field, is_record_identifier).
	 */
	public function getMappedFields()
	{		
		// Make sure Project Attribute class has instantiated the $Proj object
		if ($this->project_id === 0) {
			return [];
		} else {
			$Proj = new Project($this->project_id);
		}

		// If class variable is null, then create mapped field array
		if ($this->field_mappings === null) {
			// Put fields in array
			$this->field_mappings = [];
			// Query table
			$sql = "SELECT * FROM redcap_ddp_mapping
                WHERE project_id = ?
				ORDER BY is_record_identifier DESC, external_source_field_name, event_id, field_name, temporal_field";
			$q = db_query($sql, [$this->project_id]);
			while ($row = db_fetch_assoc($q))
			{
				// If event_id is orphaned, then skip it
				if (!isset($Proj->eventInfo[$row['event_id']])) continue;
				// If field is orphaned, then skip it
				if (!isset($Proj->metadata[$row['field_name']])) continue;
				// Initialize sub-array, if not initialized
				if (!isset($this->field_mappings[$row['external_source_field_name']])) {
					$this->field_mappings[$row['external_source_field_name']] = array();
				}
				// Add to array
				$this->field_mappings[$row['external_source_field_name']][$row['event_id']][$row['field_name']] = array(
					'map_id' => $row['map_id'],
					'is_record_identifier' => $row['is_record_identifier'],
					'temporal_field' => $row['temporal_field'],
					'preselect' => $row['preselect']
				);
			}
		}
		// Return the array of field mappings
		return $this->field_mappings;
	}

    public function getTemporalData($record_identifier_rc, $form_data, $event_id, $instance, $repeat_instrument)
    {
        $temporalMapping = TemporalMapping::fromMappedFields($this->getMappedFields());
        $temporal_fields = $temporalMapping->getFields();
        $temporal_event_ids = $temporalMapping->getEventIds();
        $temporal_data = [];
        if (!empty($temporal_fields)) {
            // Get data from backend
            $temporal_data = Records::getData($this->project_id, 'array', $record_identifier_rc, $temporal_fields, $temporal_event_ids);
            // If form values were sent, add them on top
            if (!empty($form_data) && is_numeric($event_id)) {
                foreach ($form_data as $key => $val) {
                    if (in_array($key, $temporal_fields)) {
                        if ($instance < 1) {
                            $temporal_data[$record_identifier_rc][$event_id][$key] = $val;
                        } else {
                            // Add in repeating instrument format
                            $temporal_data[$record_identifier_rc]['repeat_instances'][$event_id][$repeat_instrument][$instance][$key] = $val;
                        }
                    }
                }
            }
        }
        return $temporal_data;
    }

    public function shouldForceDataFetch($record_identifier_rc, $forceDataFetch, $record_exists)
    {
        if (!$forceDataFetch && $record_exists) {
            $data_fetch_interval = $this->getDataFetchInterval();
            $sql = "SELECT 1 FROM redcap_ddp_mapping m
                    JOIN redcap_ddp_records_data d ON m.map_id = d.map_id
                    JOIN redcap_ddp_records r ON d.mr_id = r.mr_id
                    WHERE m.project_id = ?
                    AND r.project_id = m.project_id
                    AND r.record = ? LIMIT 1";
            $q = db_query($sql, [$this->project_id, $record_identifier_rc]);
            if (db_num_rows($q) == 0) {
                $lastFetchTimeText = $this->getLastFetchTime($record_identifier_rc); // Use existing method
                if ($lastFetchTimeText == '' || ((strtotime(NOW) - strtotime($lastFetchTimeText)) > (3600 * $data_fetch_interval))) {
                    $forceDataFetch = true;
                }
            }
        }
        return $forceDataFetch;
    }

    /**
     *
     * @param string $record_identifier_rc
     * @return string
     */
    private function getLastFetchTime($record_identifier_rc, $returnInAgoFormat=false)
    {
		$sql = "SELECT updated_at FROM redcap_ddp_records WHERE project_id = ? AND record = ? LIMIT 1";
		$q = db_query($sql, [$this->project_id, $record_identifier_rc]);
		if (db_num_rows($q)) {
			$ts = db_result($q, 0);
			if(is_null($ts)) return null;
			// If we're returning the time in "X hours ago" format, then convert it, else return as is
			if ($returnInAgoFormat) {
				// If timestamp is NOW, then return "just now" text
				if ($ts == NOW) return Language::tt('ws_176');
				// First convert to minutes
				$ts = (strtotime(NOW) - strtotime($ts))/60;
				// Return if less than 60 minutes
				if ($ts < 60) return ($ts < 1 ? Language::tt('ws_177') : Language::tt(floor($ts) == 1 ? 'ws_178' :'ws_179', ['replacements' => [floor($ts)]]));
				// Convert to hours
				$ts = $ts/60;
				// Return if less than 24 hours
				if ($ts < 24) return Language::tt(floor($ts) == 1 ? 'ws_180' : 'ws_181', ['replacements' => [floor($ts)]]);
				// Convert to days and return
				$ts = $ts/24;
				return Language::tt(floor($ts) == 1 ? 'ws_182' : 'ws_183', ['replacements' => [floor($ts)]]);
			}
			// Return value
			return $ts;
		} else {
			return null;
		}
    }

    private function fetchDataFromWebService($record_identifier_external, $field_info)
    {
        global $realtime_webservice_url_data, $lang;
        if ($this->realtime_webservice_type == self::WEBSERVICE_TYPE_FHIR) {
            // Use existing getFhirData method
            try {
                $fhir_data = $this->getFhirData($record_identifier_external, $field_info); // Use existing method
                $response_data_array = $fhir_data->getData(); // Assuming getData() returns an array
            } catch (\Exception $e) {
                $code = $e->getCode();
                $message = $e->getMessage();
                throw new Exception("Error $code: $message");
            }
        } else {
            // Custom web service
            $params = array(
                'user'       => (defined('USERID') ? USERID : ''),
                'project_id' => $this->project_id,
                'redcap_url' => APP_PATH_WEBROOT_FULL,
                'id'         => $record_identifier_external,
                'fields'     => $field_info
            );
            $response_json = http_post($realtime_webservice_url_data, $params, 30, 'application/json');
            $response_data_array = json_decode($response_json, true);
            if (!$response_json || !is_array($response_data_array)) {
                $error_msg = $lang['ws_137'] . "<br><br>";
                if ($response_json !== false && !is_array($response_data_array)) {
                    $error_msg .= $lang['ws_138'] . "<div style='color:#C00000;margin-top:10px;'>$response_json</div>";
                } elseif ($response_json === false) {
                    $error_msg .= $lang['ws_139'] . " $realtime_webservice_url_data.";
                }
                throw new Exception($error_msg);
            }
        }
        return $response_data_array;
    }

    public function shouldConvertTimestampFromGMT()
    {
        global $realtime_webservice_convert_timestamp_from_gmt, $fhir_convert_timestamp_from_gmt;
        return (
            ($this->realtime_webservice_type == self::WEBSERVICE_TYPE_CUSTOM && $realtime_webservice_convert_timestamp_from_gmt == '1')
            || ($this->realtime_webservice_type == self::WEBSERVICE_TYPE_FHIR && $fhir_convert_timestamp_from_gmt == '1')
        );
    }

    /**
     * Undocumented function
     *
     * @param string $record_identifier_external
     * @param array $field_info
     * @return FhirData
     */
	public function getFhirData($mrn, $mapping_list=[])
	{
		try {
			$fhirClient = $this->ddp->getFhirClient();
			$recordAdapter = new RecordAdapter($fhirClient);
			$metadataSource = self::getFhirMetadataSource($this->project_id);
	
			// listen for notifications from the FhirClient

			$fhirClient->attach($recordAdapter, FhirClient::NOTIFICATION_ENTRIES_RECEIVED);
			$fhirClient->attach($recordAdapter, FhirClient::NOTIFICATION_ERROR);
			// start the fetching process
            $mappingGroups = FhirMappingGroup::makeGroups($metadataSource, $mapping_list);
            foreach ($mappingGroups as $mappingGroup) {
                $fhirClient->fetchData($mrn, $mappingGroup);
            }
		} catch (\Exception $e) {
			$recordAdapter->addError($e);
		}finally {
			// $data1 = $recordAdapter->getData();
			return $recordAdapter;
		}
	}

    /**
	 * @return FhirMetadataSource
	 */
	public static function getFhirMetadataSource($project_id)
	{
		$fhirSystem = FhirSystem::fromProjectId($project_id);
		$fhirVersionManager = FhirVersionManager::getInstance($fhirSystem);
		$metadataSource = $fhirVersionManager->getFhirMetadataSource();
		$metadataSource = new FhirMetadataVandyDecorator($metadataSource);
		$metadataSource = new FhirMetadataCapabilitiesDecorator($metadataSource, $fhirVersionManager);
		$metadataSource = new FhirMetadataEmailDecorator($metadataSource);
		$metadataSource = new FhirMetadataAdverseEventDecorator($metadataSource);
		$metadataSource = new FhirMetadataCdpDecorator($metadataSource); // do not use encounters
		$metadataSource = new FhirMetadataCustomDecorator($metadataSource); // apply custom metadata
		$metadataSource = new PropertiesDecorator($fhirVersionManager, $metadataSource); // apply custom metadata

		return $metadataSource;
	}
}
