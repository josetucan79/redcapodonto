<?php
namespace Vanderbilt\REDCap\Classes\Traits;

use DateTime;

trait CanPrintDate {
    
	protected static $DATE_FORMAT = 'Y-m-d H:i:s';
	
	protected function printDate($date, $format=null) {
		$format = $format ?? self::$DATE_FORMAT;
		if(!($date instanceof DateTime)) return '';
		return $date->format($format);
	}
}