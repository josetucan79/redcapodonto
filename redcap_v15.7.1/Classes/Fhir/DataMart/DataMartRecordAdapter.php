<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart;

use Exception;
use InvalidArgumentException;
use SplObserver;
use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\Form;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\Labs;
use Vanderbilt\REDCap\Classes\Fhir\Utility\InstanceSeeker;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\Coverage;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\Allergies;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\Diagnosis;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\ResourceVisitor;
use Vanderbilt\REDCap\Classes\Fhir\Utility\DBDataNormalizer;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\Demography;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\Encounters;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\Procedures;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\VitalSigns;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\Medications;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\ProblemList;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\AdverseEvents;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\ClinicalNotes;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\Immunizations;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\SocialHistory;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\DeviceImplants;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\ConditionGenomics;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\ConditionInfection;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\OperationOutcome;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\CoreCharacteristics;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\ObservationSmartData;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ConditionDentalFinding;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\AppointmentAppointments;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\ConditionMedicalHistory;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\ConditionReasonForVisit;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms\AppointmentScheduledSurgeries;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\GroupDecorators\BaseGroupDecorator;

/**
 * Adapter to save data coming from FHIR endpoints into a Data Mart record.
 * this object will listen for notification from the FHIR client
 * to populate it's data array (grouped by category)
 */
class DataMartRecordAdapter implements SplObserver
{

	/**
	 * collect stats of fetched data
	 *
	 * @var array
	 */
	private $stats = [];

	/**
	 *
	 * @var string
	 */
	private $mrn;

	/**
	 *
	 * @var DataMartRevision
	 */
	private $revision;

	/**
	 *
	 * @var int
	 */
	private $project_id;

	/**
	 *
	 * @var Project
	 */
	private $project;

	/**
	*
	* @var DBDataNormalizer
	*/
	private $dataNormalizer;

	/**
	 * contains all data for a specific record
	 * grouped by category
	 *
	 * @var array
	 */
	private $data = [];
	/**
	 * list of errors
	 *
	 * @var array
	 */
	private $errors = [];

	/**
	 * Create an instance of the adapter
	 *
	 * @param string $mrn
	 * @param \DataMartRevision $revision
	 * @param DBDataNormalizer $dataNormalizer
	 */
	public function __construct($mrn, $revision, $dataNormalizer=null)
	{
		$this->mrn = $mrn;
		$this->revision = $revision;
		$this->project_id = $this->revision->project_id;
		$this->project = new \Project($this->project_id);
		// cache a list of fields in the current project
		$this->dataNormalizer = $dataNormalizer;
	}
	
	public static function fromMRNAndRevision($mrn, $revision) {
		return new self($mrn, $revision, new DBDataNormalizer());
	}

	/**
	 * react to notifications (from the FHIR client)
	 *
	 * @param SplSubject $subject
	 * @param string $event
	 * @param mixed $data
	 * @return void
	 */
	public function update($subject, ?string $event = null, mixed $data = null): void
	{
		if(!($subject instanceof FhirClient)) return;
		switch ($event) {
			case FhirClient::NOTIFICATION_ENTRIES_RECEIVED:
				$category = $data['category'] ?? '';
				$entries = $data['entries'] ?? [];
				$mappingGroup = $data['mappingGroup'] ?? null;
				$this->addData($category, $entries, $mappingGroup);
				break;
			case FhirClient::NOTIFICATION_ERROR:
				$this->addError($data);
				break;
			default:
				# code...
				break;
		}
	}

	/**
	 * add a warning if break the glass is detected
	 * @param BaseGroupDecorator $mappingGroup
	 * @param OperationOutcome $entry
	 * @return void
	 */
	function processOperationOutcome ($mappingGroup, $entry) {
		$data = $entry->getData();
		$fhirBgtToken = $data['fhir-bgt-token'] ?? null;
		if(!$fhirBgtToken) return; 
		$severity = $data['issue_severity_1'] ?? 'Error';
		$diagnostics = $data['diagnostics'] ?? '';
		$issue = $data['issue_text_1'] ?? 'There was a problem fetching data.';
		$category = $mappingGroup->getCategory();
		$errorMessage = sprintf("Category: %s -- %s -- %s \n%s\n\n", [$category, $severity, $diagnostics, $issue]);
		$this->addError(new Exception($errorMessage, 403));
	}

	/**
	 * apply the resource visitor to the received data
	 * and store it in its group
	 *
	 * @param string $category
	 * @param array $entries
	 * @param array $mapping [[field, timestamp_min, timestamp_max]]
	 * @return void
	 */
	public function addData($category, $entries, $mappingGroup)
	{
		/**
		 * extract data from each resource
		 * and make necessary transformations if needed
		 */
		$mapEntries = function($entries, $mappingGroup) {
			/** @var BaseGroupDecorator $mappingGroup */
			$resourceVisitor = new ResourceVisitor($mappingGroup);
			$data = [];
			foreach ($entries as $entry) {
				if($entry instanceof OperationOutcome) {
					$this->processOperationOutcome($mappingGroup, $entry);
					continue;
				}
				$entryData = $entry->accept($resourceVisitor);
				$data = array_merge($data, $entryData);
			}
			return $data;
		};
		if(!$mappingGroup) throw new InvalidArgumentException('No mapping group is present; cannot add data.');

		$mappedEntries = $mapEntries($entries, $mappingGroup);
		$this->data[$category] = $mappedEntries;
	}

	/**
	 *
	 * @param Exception $data
	 * @return void
	 */
	public function addError($data)
	{
		$this->errors[] = $data;
	}

	/**
	 * Undocumented function
	 *
	 * @return Exception[]
	 */
	public function getErrors()
	{
		return $this->errors;
	}

	public function hasErrors()
	{
		return count($this->errors)>0;
	}


	private function getFormforCategory($fhirCategory)
	{
		$project = $this->project;

		// Map FHIR categories to their corresponding classes
		$categoryMap = [
			FhirCategory::LABORATORY => Labs::class,
			FhirCategory::VITAL_SIGNS => VitalSigns::class,
			FhirCategory::SOCIAL_HISTORY => SocialHistory::class,
			FhirCategory::ALLERGY_INTOLERANCE => Allergies::class,
			FhirCategory::MEDICATIONS => Medications::class,
			FhirCategory::CONDITION_PROBLEMS => ProblemList::class,
			FhirCategory::DIAGNOSIS => Diagnosis::class,
			FhirCategory::DEMOGRAPHICS => Demography::class,
			FhirCategory::ENCOUNTER => Encounters::class,
			FhirCategory::IMMUNIZATION => Immunizations::class,
			FhirCategory::CORE_CHARACTERISTICS => CoreCharacteristics::class,
			FhirCategory::ADVERSE_EVENT => AdverseEvents::class,
			FhirCategory::PROCEDURE => Procedures::class,
			FhirCategory::SMART_DATA => ObservationSmartData::class,
			FhirCategory::CONDITION_DENTAL_FINDING => ConditionDentalFinding::class,
			FhirCategory::CONDITION_GENOMICS => ConditionGenomics::class,
			FhirCategory::CONDITION_INFECTION => ConditionInfection::class,
			FhirCategory::CONDITION_MEDICAL_HISTORY => ConditionMedicalHistory::class,
			FhirCategory::CONDITION_REASON_FOR_VISIT => ConditionReasonForVisit::class,
			FhirCategory::COVERAGE => Coverage::class,
			FhirCategory::DEVICE_IMPLANTS => DeviceImplants::class,
			FhirCategory::APPOINTMENT_APPOINTMENTS => AppointmentAppointments::class,
			FhirCategory::APPOINTMENT_SCHEDULED_SURGERIES => AppointmentScheduledSurgeries::class,
			FhirCategory::DOCUMENT_REFERENCE_CLINICAL_NOTES => ClinicalNotes::class,
		];

		// Check if the category exists in the map and instantiate the corresponding class
		if (isset($categoryMap[$fhirCategory])) {
			$className = $categoryMap[$fhirCategory];
			return new $className($project);
		}

		// Return null if the category is not mapped
		return null;
	}


	/**
	 * update the stats with the amount of
	 * data fetched and saved per category
	 *
	 * @param string $category
	 * @param array $data
	 * @param Boolean $repeating
	 * @return void
	 */
	function updateStats($category, $data, $repeating)
	{
		if(empty($data)) return;
		if(!array_key_exists($category, $this->stats)) $this->stats[$category] = 0;
		if($repeating) {
			$this->stats[$category] = intval($this->stats[$category])+1;
		}else {
			$this->stats[$category] = intval($this->stats[$category])+count($data);
		}
	}

	/**
	 * return collected stats
	 *
	 * @return array
	 */
	function getStats() {
		return $this->stats;
	}

	public function getRecord()
	{
		$cache = [];
		/**
		 * check if data has already been processed (dulpicate entries)
		 */
		$isDuplicateEntry = function(...$params) use(&$cache){
			// hash the parameters and cache them to skip duplicates
			$hasedData = md5(json_encode($params));
			if(in_array($hasedData, $cache)) return true;
			$cache[] =$hasedData;
			return false;
		};

		$getNextInstanceInRecord = function($record, $formName) {
			if(empty($record)) return 1;
			$recordData = reset($record) ?: []; //extract what is inside recordId
			$repeatInstancesList = $recordData['repeat_instances'] ?? [];
			$repeatInstancesData = reset($repeatInstancesList) ?: []; // extract what is inside event_id
			$repeatInstanceData = array_key_exists($formName, $repeatInstancesData) ? $repeatInstancesData[$formName] : [];
			$lastInstance = end(array_keys($repeatInstanceData));
			return intval($lastInstance)+1;
		};
		
		$mrn = $this->mrn;
		$groupedData = $this->data;
		// Instantiate project
		$project = $this->project;
		$project_id = $project->project_id;
		$event_id = $project->firstEventId;
		$recordId = InstanceSeeker::getRecordID($project_id, $event_id, 'mrn', $mrn);
		if(!$recordId) {
			throw new \Exception("Error: the specified MRN is not in the project", 1);
		}
		
		$recordSeed = [];
		// get the event ID. Will be used to save data in the record structure
		foreach ($groupedData as $category => $entries) {
			$form = $this->getFormforCategory($category);
			if(!$form instanceof Form) continue;
			$formName = $form->getFormName();
			$instanceSeeker = new InstanceSeeker($project, $formName);
			
			foreach ($entries as $entry) {
				$data = $form->mapFhirData($entry);
				if($isDuplicateEntry($recordId, $data)) continue;
				if($repeating=$form->isRepeating()) {
					$fullMatch = $instanceSeeker->findMatches($recordId, $data, array_keys($data));
					if($fullMatch) continue;
					$uniquenessFields = $form->getUniquenessFields();
					$matchingInstance = $instance_number = $instanceSeeker->findMatches($recordId, $data, $uniquenessFields);
					if(!$matchingInstance) {
						// choose the next instance number between the database and the recordSeed 
						$db_instance_number = $instanceSeeker->getAutoInstanceNumber($recordId);
						$recordSeedInstance = $getNextInstanceInRecord($recordSeed, $formName);
						$instance_number = max($db_instance_number, $recordSeedInstance);
					}
				}else {
					$differentFields = $instanceSeeker->getNonMatchingFields($recordId, $data);
					// only consider different and non empty values for insertion
					$data = array_filter($data, function($value, $key) use($differentFields) {
						if($value==='') return false; // skip empty values
						return in_array($key, $differentFields); // only keep different fields
					}, ARRAY_FILTER_USE_BOTH);
					if(count($data)<1) continue;
					$instance_number = 1;
				}
				
				$completeData = [];
				// add the information to mark the form as "completed" if there is data to save
				if(!empty($data)) $completeData = $form->addCompleteFormData($data);
				// add data to the record seed
				foreach($completeData as $field_name => $value) {
					if($this->dataNormalizer) $value = $this->dataNormalizer->truncate($value, DBDataNormalizer::MAX_FIELD_SIZE);
					$recordSeed = $form->reduceRecord($recordId, $event_id, $field_name, $value, $instance_number, $recordSeed);
				}
				// update stats using the data (do not count the {form_name}_complete field)
				$this->updateStats($category, $data, $repeating);
			}

		}
		return $recordSeed;
	}

}