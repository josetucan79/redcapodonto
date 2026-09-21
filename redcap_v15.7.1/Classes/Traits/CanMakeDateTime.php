<?php
namespace Vanderbilt\REDCap\Classes\Traits;

use DateTime;

trait CanMakeDateTime {
    
	/**
	 * convert a string to a DateTime object
	 *
	 * @param string $value
	 * @return DateTime|null
	 */
	protected function makeDateTime($value) {
		if(empty($value)) return;
		if($value instanceof DateTime) return $value;
		$dateTime = new DateTime($value);
		return $dateTime;
	}
}