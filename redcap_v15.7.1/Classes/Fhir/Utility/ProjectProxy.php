<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Utility;

use Project;

/**
 * Proxy for the Project class with FHIR specific helper methods
 */
class ProjectProxy
{
  /**
   * ID of the project
   *
   * @var integer
   */
  private $project_id;

  /**
   * instance of the project
   *
   * @var Project
   */
  private $project;

  public function __construct($project_id)
  {
    $this->project_id = $project_id;
    $this->project = new Project($project_id);
  }

  public function getLanguage()
  {
    $project_language = $this->project->project['project_language'];
    $lang = \Language::getLanguage($project_language);
    return $lang;
  }

  /**
   * get the name of the form containing a field
   *
   * @param string $field_name
   * @return string
   */
  public function getFormName($field_name)
  {
    $project_metadata = $this->project->metadata;
    $form_name = $project_metadata[$field_name]['form_name'] ?? '';
    return $form_name;
  }

  /**
   * check if a field is contained in a repeating form
   *
   * @param integer $event_id
   * @param string $field_name
   * @return boolean
   */
  public function isFieldInRepeatingForm($event_id, $field_name)
  {
    $form_name = $this->getFormName($field_name);
    $is_repeating = $this->isRepeatingForm($event_id, $form_name);
    return $is_repeating;
  }

  /**
   * shortcut to get metadata
   *
   * @return array
   */
  public function getMetadata()
  {
    return $this->project->metadata ?? [];
  }

  /**
   * get the validation type for a field using the metadata
   * stored in the project
   *
   * @param string $field_name
   * @return string
   */
  public function getFieldValidation($field_name)
  {
    $metadata = $this->getMetadata();
    $raw_validation_type = $metadata[$field_name]['element_validation_type'] ?? '';
    $validation_type = convertLegacyValidationType(convertDateValidtionToYMD($raw_validation_type));
    return $validation_type;
  }

  /**
   * get operator (positive, negative or both) to calculate date range for
   * valid values in fetched Fhir values
   *
   * @return string
   */
  public function getFhirCdpOffsetPlusMinus()
  {
    $realtime_webservice_offset_plusminus = $this->project->project['realtime_webservice_offset_plusminus'] ?? ''; // example "+-"
    return $realtime_webservice_offset_plusminus;
  }

  public function getRealtimeWebserviceType()
  {
    return $this->project->project['realtime_webservice_type'] ?? null;
  }

  /**
   * get offset in secondsto calculate date range for
   * valid values in fetched FHIR values.
   * offset days are stored using a float where 1/100 = 15 minutes
   *
   * @return array
   */
  private function convertFhirCdpOffsetToSeconds()
  {
    $realtime_webservice_offset_days = $this->project->project['realtime_webservice_offset_days'] ?? 0; // example "30"
    $days_with_minutes = floatval($realtime_webservice_offset_days);
    $days = floor($days_with_minutes);
    $fraction_value = 60*24/15; // 0.01 eqauls ~15 minutes (14.4)
    $seconds = ($days_with_minutes - $days)*1000*$fraction_value; // convert decimal part to seconds
    $total = $seconds + ($days*60*60*24);
    return $total;
  }

  /**
   * get a date range based on the provided date
   *
   * @param \DateTime $date
   * @return array [from, to]
   */
  public function getFhirOffsetDaysRange($date)
  {
    if(!($date instanceof \DateTime)) throw new \Exception("you must provide a DateTime object", 400);
    $offset_seconds = $this->convertFhirCdpOffsetToSeconds();
    $plus_minus = $this->getFhirCdpOffsetPlusMinus();
    $from = clone $date;
    $to = clone $date;
    $offset = new \DateInterval("PT{$offset_seconds}S");
    if(preg_match('/-/', $plus_minus)) $from->sub($offset);
    if(preg_match('/\+/', $plus_minus)) $to->add($offset);
    return compact('from', 'to');
  }

  /**
   * get a value(s) for a field
   *
   * @param integer $project_id
   * @param mixed $record_id
   * @param integer $event_id
   * @param string $field_name
   * @param boolean|integer $instance
   * @return array [instance_number=>value]
   */
  public function getFieldValue($record_id, $event_id, $field_name, $instance=false)
  {
      $query_string = sprintf(
          "SELECT `value`, IFNULL(`instance`, 1) as `normalized_instance` FROM ".\Records::getDataTable($this->project_id)." WHERE
          `project_id`=%u AND `record`=%s AND `event_id`=%u AND `field_name`=%s",
          +$this->project_id, checkNull($record_id), +$event_id, checkNull($field_name)
      );
      if($instance) $query_string .= sprintf(" HAVING `normalized_instance`=%u", +$instance);
      $result = db_query($query_string);
      $rows = [];
      while($row = db_fetch_assoc($result)) {
          $instance = $row['normalized_instance'] ?? 1;
          $rows[$instance] = $row['value'] ?? '';
      }
      return $rows;
  }

  /**
   * build the structure of a REDCap record
   *
   * @param array $carry
   * @param mixed $value
   * @param string|int $record_id
   * @param int $event_id
   * @param string $field_name
   * @param int $instance
   * @return array
   */
  public function buildRecord($carry, $value, $record_id, $event_id, $field_name, $instance=1)
  {
    if(!is_array($carry)) throw new \Exception("The structure the carried record must be in array format", 400);
    $project_id = $this->project_id;
    $form_name = $this->getFormName($field_name);
    if(empty($form_name)) throw new \Exception("The provided field name '{$field_name}' does not belong to a form in the project ID {$project_id}, event ID {$event_id}", 400); 
    $is_repeating = $this->isRepeatingForm($event_id, $form_name);

    if($is_repeating) {
      $carry[$record_id]['repeat_instances'][$event_id][$form_name][$instance][$field_name] = $value;
    }else {
      $carry[$record_id][$event_id][$field_name] = $value;
    }
    return $carry;
  }

  /**
   * get setting for time conversion
   *
   * @return boolean
   */
  public function shouldConvertTimestampFromGmt()
  {

    $fhir_convert_timestamp_from_gmt = $this->project->project['fhir_convert_timestamp_from_gmt'] ?? false;
    return $fhir_convert_timestamp_from_gmt;
  }

  public function __set($name, $value)
  {
    $this->project->$name = $value;
  }

  public function __get($name)
  {
    if (property_exists($this->project, $name)) {
        return $this->project->$name;
    }

    $trace = debug_backtrace();
    trigger_error(
        'Undefined property via __get(): ' . $name .
        ' in ' . $trace[0]['file'] .
        ' on line ' . $trace[0]['line'],
        E_USER_NOTICE);
    return null;
  }

  public function __call($name, $arguments)
  {
    if(method_exists($this->project, $name)) {
      return call_user_func_array([$this->project, $name], $arguments);
    }
  }

  public static function __callStatic($name, $arguments)
  {
    if(method_exists(Project::class, $name)) {
      return call_user_func_array([Project::class, $name], $arguments);
    }
  }


}