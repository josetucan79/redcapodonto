<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Traits;

use System;

trait CanNormalizeTimestamp
{
  
  /**
  * return a date in a REDCap compatible format.
  * Note that no server timezone is applied 
  * since gmdate is used instead of date
  *
  * @param string $timestamp
  * @param string $format
  * @return string
  */
  protected function getGmtTimestamp($timestamp, $format='Y-m-d H:i')
  {
    if(!$timestamp) return '';
    $time = strtotime($timestamp);
    $dateAsUtc = gmdate($format, $time);
    return $dateAsUtc;
  }
  
  /**
  * return a date in a REDCap compatible format.
  * The local timezone of the server is applied
  *
  * @param string $timestamp
  * @param string $format
  * @return void
  */
  protected function getLocalTimestamp($timestamp, $format='Y-m-d H:i')
  {
    if(!$timestamp) return '';
    $time = strtotime($timestamp);
    $dateAsLocal = date($format, $time);
    return $dateAsLocal;
  }
  
  /**
  * curried function.
  * returns a callable that will transform a timestamp
  * to local or GMT time
  *
  * @param callable $timestampCallback
  * @param string $format
  * @return callable
  */
  protected function getTimestampCallable($timestamp, $format='Y-m-d H:i')
  {
    $callback = function($localTimestamp=false) use($timestamp, $format) {
      if($localTimestamp) return $this->getLocalTimestamp($timestamp, $format);
      return $this->getGmtTimestamp($timestamp, $format);
    };
    return $callback;
  }
  
  /**
  * curried function.
  * returns a callable that will transform a timestamp
  * to local or GMT time
  *
  * @param callable $timestampCallback
  * @param string $format
  * @return callable
  */
  protected function formatTimestamp($timestamp, $options = []) {
    $defaultOptions = [
      'format' =>'Y-m-d H:i',
      'local' => false,
    ];
    // Merge provided options with the defaults
    $options = array_merge($defaultOptions, $options);
    $format = $options['format'];
    if($options['local']) return $this->getLocalTimestamp($timestamp, $format);
    return $this->getGmtTimestamp($timestamp, $format);
  }
}