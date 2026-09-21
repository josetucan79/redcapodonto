<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Utility;

class DBDataNormalizer
{
  
  /**
  * max bytes allowed in a TEXT field of a MySQL database
  * (as used by redcap_data)
  */
  const MAX_FIELD_SIZE = 65535; // max size of field in REDCap db
  const FIELD_SIZE_DECREMENT = 0.05; // percentage to remove from maxSize
  const TOO_LARGE_NOTICE = 'DATA TOO LARGE, TRUNCATED';

  /**
  *
  * @param string $string
  * @param int $maxSize
  * @return bool
  */
  function isSizeExceeded($string, $maxSize=self::MAX_FIELD_SIZE) {
    // Get the size of the string in bytes
    $stringSize = strlen($string);
    
    // Check if the size of the string exceeds the maximum size of TEXT data type
    if ($stringSize > $maxSize) {
      return true; // Size exceeded
    } else {
      return false; // Size within limit
    }
  }
  
  /**
  *
  * @param string $string
  * @param int $byteCount
  * @return void
  */
  function truncateStringToBytes($string, $byteCount) {
    if (strlen($string) > $byteCount) {
      $string = substr($string, 0, $byteCount);
      $lastChar = substr($string, -1);
      
      // Check if the last character is a multi-byte character
      if (ord($lastChar) > 127) {
        $string = substr($string, 0, -1);
      }
    }
    return $string;
  }
  
  /**
   * Truncates a string to a specified byte count, optionally adding a prefix and suffix.
   *
   * This function checks if the data size exceeds the specified byte limit. If it does,
   * it truncates the data to fit within the limit after accounting for the lengths of the
   * prefix and suffix. If the size does not exceed the limit, it returns the data unchanged.
   *
   * @param string $data The input string to be truncated.
   * @param int $byteCount The maximum allowed byte count for the output string.
   * @param string $prefix A string to prepend to the truncated data. Default is an empty string.
   * @param string $suffix A string to append to the truncated data. Default is an empty string.
   * @return string The truncated string with the prefix and suffix added.
   */
  public function truncate($data, $byteCount, $prefix = '', $suffix='') {
    if(!$this->isSizeExceeded($data, $byteCount)) return $data;
    $prefixLength = strlen($prefix);
    $suffixLength = strlen($suffix);
    $maxDataLength = $byteCount - $prefixLength - $suffixLength;
    
    $data = $prefix . $this->truncateStringToBytes($data, $maxDataLength) . $suffix;
    return $data;
  }

}