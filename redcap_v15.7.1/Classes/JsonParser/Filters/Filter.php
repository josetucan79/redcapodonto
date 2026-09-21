<?php
namespace Vanderbilt\REDCap\Classes\JsonParser\Filters;

use DateTime;
use Vanderbilt\REDCap\Classes\JsonParser\Node\Node;
use Vanderbilt\REDCap\Classes\JsonParser\Helpers\Utils;

/**
 * Filters for Node elements.
 * 
 * Note: since Node::search needs a Node as only argument
 * for the search callback, all this functions can be used
 * applying first the $value with a Functional::partialRight
 */
class Filter
{
  /**
   * Check if node value is equal
   * NOTE: in PHP comparing a string to 0 could result in a false positive
   *
   * @param mixed $expected
   * @param mixed $actual
   * @return boolean
   */
  public function isEqual($actual, $expected)
  {
    // $expectedType = getType($expected);
    // if($expectedType==='string') return strcasecmp($expected, $actual)===0;
    return $expected == $actual;
  }
    
  /**
   * Check if node value is not equal
   *
   * @param mixed $actual
   * @param mixed $expected
   * @return boolean
   */
  public function isNotEqual($actual, $expected)
  {
    return !$this->isEqual($actual, $expected);
  }

  /**
   * Check if node value is identical
   *
   * @param mixed $actual
   * @param mixed $expected
   * @return boolean
   */
  public function isIdentical($actual, $expected)
  {
    // $expectedType = getType($expected);
    // if($expectedType==='string') return strcmp($expected, $actual)===0;
    return $expected === $actual;
  }
    
  /**
   * Check if node value is not identical
   *
   * @param mixed $actual
   * @param mixed $expected
   * @return boolean
   */
  public function isNotIdentical($actual, $expected)
  {
    return !$this->isIdentical($actual, $expected);
  }

  /**
   * check if a value is null
   *
   * @param mixed $actual
   * @return boolean
   */
  public function isNull($actual) {
    return is_null($actual);
  }

  /**
   * check if a value is NOT null
   *
   * @param mixed $actual
   * @return boolean
   */
  public function isNotNull($actual) {
    return !$this->isNull($actual);
  }
    
  /**
   * Check if node value is bigger
   *
   * @param mixed $actual
   * @param mixed $expected
   * @return boolean
   */
  public function isBigger($actual, $expected)
  {
    return $actual > $expected;
  }
    
  /**
   * Check if node value is bigger or equal
   *
   * @param mixed $actual
   * @param mixed $expected
   * @return boolean
   */
  public function isBiggerOrEqual($actual, $expected)
  {
    return $actual >= $expected;
  }
    
  /**
   * Check if node key is smaller
   *
   * @param mixed $actual
   * @param mixed $expected
   * @return boolean
   */
  public function isSmaller($actual, $expected)
  {
    return $actual < $expected;
  }
    
  /**
   * Check if node key is smaller or equal
   *
   * @param mixed $value
   * @return boolean
   */
  public function isSmallerOrEqual($actual, $expected)
  {
    return $actual <= $expected;
  }
    
  /**
   * Check if node key matches a regular expression
   *
   * @param string $string
   * @param string $pattern
   * @param string $escapeCharacters
   * @return boolean
   */
  public function isLike($string, $pattern, $escapeCharacters='/')
  {
    $regexp = Utils::fixRegExp($pattern, $escapeCharacters);
    $match = preg_match($regexp, $string );
    return $match===1;
  }
  
  /**
   * Check if node key DOES NOT matches a regular expression
   *
   * @param string $string
   * @param string $pattern
   * @param string $escapeCharacters
   * @return boolean
   */
  public function isNotLike($string, $pattern, $escapeCharacters='/')
  {
    return !$this->isLike($string, $pattern, $escapeCharacters);
  }
  
  /**
   * Check if key is included in a list of values
   *
   * @param mixed $needle
   * @param array $haystack list of values
   * @return boolean
   */
  public function isIn($needle, $haystack)
  {
    if(!is_array($haystack)) throw new \Exception("please provide an array", 400);
    return in_array($needle, $haystack);

  }
  
  /**
   * Check if node key is NOT included in a list of values
   *
   * @param mixed $needle
   * @param array $haystack list of values
   * @return boolean
   */
  public function isNotIn($needle, $haystack)
  {
    return !$this->isIn($needle, $haystack);
  }
  
  /**
   * Check if node value is beetween 2 values (start and end included)
   *
   * @param float $actual
   * @param float $start
   * @param float $end
   * @return boolean
   */
  public function isBeetween($actual, $start, $end)
  {
    if( !is_numeric($start) || !is_numeric($end) ) throw new \Exception("'must provide numeric values", 400);
    if($start>$end) {
      $temp = $start;
      $start = $end;
      $end = $temp;
    }
    return ( $start <= $actual && $actual <= $end );
  }
  
  /**
   * Check if node value is NOT beetween 2 values (start and end included)
   *
   * @param float $actual
   * @param float $start
   * @param float $end
   * @return boolean
   */
  public function isNotBeetween($actual, $start, $end)
  {
    return !$this->isBeetween($actual, $start, $end);
  }

  /**
   * Check if node value is equal
   * NOTE: in PHP comparing a string to 0 could result in a false positive
   *
   * @param mixed $actual
   * @return boolean
   */
  public function isAny($actual)
  {
    return true;
  }

  /**
   * Check if node value is a String
   *
   * @param mixed $actual
   * @return boolean
   */
  function isString($actual) {
    return is_string($actual);
  }

  /**
   * Check if node value is a Number
   *
   * @param mixed $actual
   * @return boolean
   */
  function isNumber($actual) {
    return is_numeric($actual);
  }
  
  /**
   * Check if node value is a Boolean
   *
   * @param mixed $actual
   * @return boolean
   */
  function isBoolean($actual) {
    return is_bool($actual);
  }

  /**
   * Check if node value is an Array
   *
   * @param mixed $actual
   * @return boolean
   */
  function isArray($actual) {
    return $this->isNonAssociativeArray($actual);
  }

  /**
   * Check if node value is an Object (associative array)
   *
   * @param mixed $actual
   * @return boolean
   */
  function isObject($actual) {
    return $this->isAssociativeArray($actual);
  }

  /**
   * If the keys of the original array are not sequential integers
   * (i.e., the array is associative), the keys will not be the same
   * in the two arrays, and thus the expression will evaluate to true.
   *
   * @param mixed $arr
   * @return boolean
   */
  private function isAssociativeArray($arr) {
      if (!is_array($arr)) {
          return false;
      }

      // if the original array contains only sequential integer keys (i.e., it is a regular indexed array),
      // the keys will be the same in both arrays, and the expression will evaluate to false.
      $keys = array_keys($arr);

      return array_keys($keys) !== $keys;
  }

  /**
   * If the original array contains only sequential integer keys
   * (i.e., it is a regular indexed array), the keys will be the same in both arrays,
   * and the expression will evaluate to true.
   *
   * @param mixed $arr
   * @return boolean
   */
  private function isNonAssociativeArray($arr) {
      if (!is_array($arr)) {
          return false;
      }
      $keys = array_keys($arr);

      return array_keys($keys) === $keys;
  }

  private function  getValueType($value) {
    $type = gettype($value);

  }
    
}