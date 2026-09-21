<?php
namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\ValueObjects;

use DateTime;
use DateInterval;
use Project;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMapping;

/**
 * Collection of field info (FhirMapping[]) for the web service request
 * 
 */
class FieldInfoCollection
{
    /**
     * @var array
     */
    private $fieldInfo;        // Array of FhirMapping objects
    /**
     * @var array
     */
    private $fieldEventInfo;   // Array of field event information

    private function __construct(array $fieldInfo, array $fieldEventInfo)
    {
        $this->fieldInfo = $fieldInfo;
        $this->fieldEventInfo = $fieldEventInfo;
    }

    /**
     * Static factory method to create FieldInfoResult from parameters.
     */
    public static function fromParams(
        Project $proj,
        array $mappings,
        array $temporalData,
        float $dayOffset,
        string $dayOffsetPlusMinus,
        string $recordIdentifierRc
    ): self {
        $fieldInfo = [];
        $fieldEventInfo = [];


        foreach ($mappings as $srcField => $evtArray) {
            foreach ($evtArray as $thisEventId => $rcFields) {
                $isRepeatingEvent = $proj->isRepeatingEvent($thisEventId);
                foreach ($rcFields as $rcField => $rcFieldAttr) {
                    // Skip the record ID field
                    if ($rcFieldAttr['is_record_identifier']) {
                        continue;
                    }

                    if (!empty($rcFieldAttr['temporal_field'])) {
                        // Handle temporal fields
                        $temporalFieldData = static::getTemporalFieldData(
                            $proj,
                            $temporalData,
                            $rcFieldAttr,
                            $recordIdentifierRc,
                            $thisEventId,
                            $isRepeatingEvent
                        );

                        foreach ($temporalFieldData as $thisInstance => $instanceTemporalData) {
                            $timestamp = $instanceTemporalData[$rcFieldAttr['temporal_field']] ?? null;
                            if (empty($timestamp)) {
                                continue;
                            }

                            // Calculate timestamp ranges
                            $timestampMin = ($dayOffsetPlusMinus === '+')
                                ? new DateTime($timestamp)
                                : static::setOffsetDays($timestamp, $dayOffset, false);

                            $timestampMax = ($dayOffsetPlusMinus === '-')
                                ? new DateTime($timestamp)
                                : static::setOffsetDays($timestamp, $dayOffset, true);

                            // Add to fieldInfo
                            $fieldInfo[] = new FhirMapping($srcField, $timestampMin, $timestampMax);

                            // Add to fieldEventInfo
                            $fieldEventInfo[] = [
                                'src_field' => $srcField,
                                'rc_field'  => $rcField,
                                'event_id'  => $thisEventId,
                                'timestamp' => $timestamp,
                                'preselect' => $rcFieldAttr['preselect'] ?? null,
                            ];
                        }
                    } else {
                        // Non-temporal fields
                        $fieldInfo[] = new FhirMapping($srcField);

                        $fieldEventInfo[] = [
                            'src_field' => $srcField,
                            'rc_field'  => $rcField,
                            'event_id'  => $thisEventId,
                        ];
                    }
                }
            }
        }

        return new self($fieldInfo, $fieldEventInfo);
    }

    public function getFieldInfo(): array
    {
        return $this->fieldInfo;
    }

    public function getFieldEventInfo(): array
    {
        return $this->fieldEventInfo;
    }

    /**
	 * add/subtract an offset of days to a date provided as a string
	 * 0.01 ~= 15 minutes
	 * @param string $date
	 * @param string $offset (example -340.01) 
	 * @param boolean $add subtract if false
	 * @return DateTime
	 */
	private static function setOffsetDays($date, $offset, $add=true)
	{
		$time = strtotime($date);
		$date_time = new DateTime();
		$date_time->setTimestamp($time);
		$offset_regexp = "/(?<days>[\d]+)(\.(?<minutes>\d{1,2}))?/";
		preg_match($offset_regexp, $offset, $matches);
		if($matches['days']) {
			$days_string = $matches['days'] ?? '';
			$minutes_string =  $matches['minutes'] ?? '';
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

    /**
     *
     * @param Project $Proj
     * @param array $temporal_data
     * @param array $rc_field_attr
     * @param string $record_identifier_rc
     * @param int $this_event_id
     * @param bool $isRepeatingEvent
     * @return array
     */
    private static function getTemporalFieldData($Proj, $temporal_data, $rc_field_attr, $record_identifier_rc, $this_event_id, $isRepeatingEvent)
    {
        $temporal_field_data = [];
        $this_temporal_field_form = $Proj->metadata[$rc_field_attr['temporal_field']]['form_name'];
        if ($isRepeatingEvent || $Proj->isRepeatingForm($this_event_id, $this_temporal_field_form)) {
            $this_repeat_form = $isRepeatingEvent ? "" : $this_temporal_field_form;
            $temporal_field_instances = $temporal_data[$record_identifier_rc]['repeat_instances'][$this_event_id][$this_repeat_form] ?? [];
            foreach ($temporal_field_instances as $this_instance => $idata) {
                $temporal_field_data[$this_instance][$rc_field_attr['temporal_field']] = $idata[$rc_field_attr['temporal_field']];
            }
        } else {
            $temporal_field_data = array(
                1 => array(
                    $rc_field_attr['temporal_field'] => $temporal_data[$record_identifier_rc][$this_event_id][$rc_field_attr['temporal_field']] ?? ''
                )
            );
        }
        return $temporal_field_data;
    }
}
