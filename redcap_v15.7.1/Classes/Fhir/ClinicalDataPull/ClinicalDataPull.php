<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull;

use DateTime;
use DateInterval;
use DateTimeZone;

/**
 * TODO: new ClynicalDataPull logic
 */
class ClynicalDataPull {

    public function getRecords($project_id, $event_id) {
		$queryString = "SELECT DISTINCT record FROM ".\Records::getDataTable($project_id)." WHERE project_id=? AND event_id=?";
		$result = db_query($queryString, [$project_id, $event_id]);
		$records = [];
		while($row = db_fetch_assoc($result)) $records[] = $row['record'] ?? '';
		return $records;
	}

	public function getFhirParams($project_id, $record, $fhirField, $mapping, $day_offset=null, $day_offset_plusminus="+-") {
		$params = [
			'field' => $fhirField,
			'timestamp_min' => null,
			'timestamp_max' => null,
		];

		$eventID= key($mapping);
		$redcapField = key($mapping[$eventID] ?? '');
		$info = $mapping[$eventID][$redcapField] ?? [];
		$temporal_field = $info['temporal_field'] ?? '';
		if(!$temporal_field) return $params;
		
		$queryString = "SELECT DISTINCT value FROM ".\Records::getDataTable($project_id)." WHERE project_id=? AND event_id=? AND record=? AND field_name=?";
		$result = db_query($queryString, [$project_id, $eventID, $record, $temporal_field]);
		if( !($row = db_fetch_assoc($result))) return $params;
		$referenceDate = $row['value'];
		if ($day_offset_plusminus == '+') {
			$this_timestamp_min = new DateTime($referenceDate);
		} else {
			$this_timestamp_min = $this->setOffsetDays($referenceDate, $day_offset, $add=false);
		}
		// Determine max timestamp
		if ($day_offset_plusminus == '-') {
			$this_timestamp_max = new DateTime($referenceDate);
		} else {
			$this_timestamp_max = $this->setOffsetDays($referenceDate, $day_offset, $add=true);
		}


		/* id
				47:
		array(1)
		mrn:
		array(4)
		map_id:
		is_record_identifier:
		temporal_field:
		preselect: */

	}

    	/**
	 * add/subtract an offset of days to a date provided as a string
	 * 0.01 ~= 15 minutes
	 * @param string $date
	 * @param string $offset (example -340.01) 
	 * @param boolean $add subtract if false
	 * @return DateTime
	 */
	public function setOffsetDays($date, $offset, $add=true)
	{
		$time = strtotime($date);
		$date_time = new DateTime();
		$date_time->setTimestamp($time);
		$offset_regexp = "/(?<days>[\d]+)(\.(?<minutes>\d{1,2}))?/";
		preg_match($offset_regexp, $offset, $matches);
		if($matches['days']) {
			$days_string = $matches['days'] ?: '';
			$minutes_string =  $matches['minutes'] ?: '';
			$date_interval = DateInterval::createFromDateString("$days_string days");
			if(!empty($minutes_string)) {
				$minutes_decimal = (float)sprintf("0.%s", str_pad( $minutes_string, 2, "0")); // add padding zeroes
				$minutes = 1440*$minutes_decimal;
				$date_interval->i = $minutes;
			}
			if($add) $date_time->add($date_interval);
			else $date_time->sub($date_interval);
		}
		return $date_time;
	}

	public function convertTimeFromGMT($timestamp) {
		$userTimezone = new DateTimeZone(getTimeZone());
		$gmtTimezone = new DateTimeZone('GMT');
		$myDateTime = new DateTime($timestamp, $gmtTimezone);
		$offset = $userTimezone->getOffset($myDateTime);
		$myInterval = DateInterval::createFromDateString((string)$offset . 'seconds');
		$myDateTime->add($myInterval);
		$timestamp = $myDateTime->format('Y-m-d H:i:s');
		return $timestamp;
	}

}