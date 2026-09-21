<?php
namespace Vanderbilt\REDCap\Classes\JsonParser\Helpers;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Vanderbilt\REDCap\Classes\JsonParser\ArrayWalker\ArrayWalker;
use Vanderbilt\REDCap\Classes\JsonParser\ArrayWalker\ArrayWalkerDto;

 class Utils
 {

  /**
   * check if an array has any string keys
   */
  public static function arrayHasStringKeys(array $array) {
    return count(array_filter(array_keys($array), 'is_string')) > 0;
  }

  /**
   * checking whether the array is zero-indexed and sequential
   *
   * @param array $arr
   * @return boolean
   */
  public static function arrayIsAssoc(array $arr)
  {
      if ([] === $arr) return false;
      return array_keys($arr) !== range(0, count($arr) - 1);
  }

    /**
   * make sure to get a valid regular expression:
   * - keep whole structure with flags if delimiters are detected
   *
   * @param string $string
   * @return string
   */
  public static function fixRegExp($string, $escapeCharacters='/')
  {
    if(preg_match("/^\/(?<content>.*)\/(?<flags>[\w]*)$/", $string, $matches)) {
      $escaped = addcslashes(@$matches['content'], $escapeCharacters);
      $regexp = sprintf("/%s/%s", $escaped, @$matches['flags']);
    }else {
      $escaped = addcslashes($string, $escapeCharacters);
      $regexp = sprintf("/%s/", $escaped);
    }
    return $regexp;
  }

  public static function getArrayDepth($array) {
      $overallDepth = 0;
      $recursiveArrayIterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($array));
  
      foreach ($recursiveArrayIterator as $iterator) {
          $depth = $recursiveArrayIterator->getDepth();
          $overallDepth = $depth > $overallDepth ? $depth : $overallDepth;
      }
  
      return $overallDepth;
  }

  /**
   * normalize arrays removing 1 level if the first 2 levels
   * are both numeric indexes arrays
   *
   * @param array $data
   * @return array
   */
  public static function normalizeArray($data) {
      if(!is_array($data) || Utils::arrayIsAssoc($data)) return $data;
      $flat = [];
      // if we are here then this is a numeric index array
      foreach ($data as $subData) {
          if(!is_array($subData) || Utils::arrayIsAssoc($subData)) return $data; // stop at any time if not array or associative
          $flat = array_merge($flat, $subData);
      }
      return $flat;
  }

  /**
   * get rid of numeric indexes in full paths
   * example
   * $data = [
   *     0 => [
   *         'n' => [
   *             0 => [
   *                 0 => ['a','b','c'],
   *                 'n' => ['d','e','f'],
   *                 2 => ['g','h','i'],
   *             ]
   *         ],
   *     ]
   * ];
   *  will become
   * $normalized = [
   *  'n' => [
   *    'a','b','c',
   *    'n' => ['d','e','f'],
   *    'g','h','i',
   *    ],
   *  ];
   *
   * @param [type] $data
   * @return void
   */
  public static function removeNumericIndexes($data) {
    if(!is_array($data) || Utils::arrayIsAssoc($data)) return $data;
    $generator = ArrayWalker::fromArray($data)->BF();
    $normalized = [];
    /** @var ArrayWalkerDto $dto */
    while( ($dto = $generator->current())) {
      $generator->next();
      if($dto->isLeaf()) {
        $path = $dto->path();
        $normalizedPath = array_filter($path, function($key) {

          return !is_numeric($key);
        });
        $current = &$normalized;
        while($key = current($normalizedPath)) {
          $current = &$current[$key];
          next($normalizedPath);
        }
        $data = $dto->data();
        // if(is_array($current) && !in_array($data, $current))
        if(is_array($current) && in_array($data, $current)) {
          continue;
        }
        $current[] = $data;
      }


    }
    return $normalized;
  }

  public static function flattenArray1($data) {
    if(!is_array($data) || Utils::arrayIsAssoc($data)) return $data;
    if(Utils::getArrayDepth($data)===0) return $data; // do not flatten single level arrays
    // make sure all children are nested arrays
    foreach($data as $key => $subData) {
      // check if we can return original data as is
      if(!is_array($subData) || Utils::arrayIsAssoc($subData)) return $data;
      // if(Utils::arrayIsAssoc($subData)) return $data; // stop and return unmodified data
    }
    return $subData;
  }


  public static function getArrayElement($data, $path) {
    $current = &$data;
    while($key = current($path)) {
      $current = &$current[$key];
      next($path);
    }
    return $current;
  }

  public static function setArrayElement(&$data, $path, $value) {
    $current = &$data;
    while($key = current($path)) {
      $current = &$current[$key];
      next($path);
    }
    $current = $value;
  }

  /**
   * get all leaf values
   *
   * @return array
   */
  public static function getArrayLeaves($array) {
    $arrayIterator = new RecursiveArrayIterator($array);
    $recursiveIterator = new RecursiveIteratorIterator( $arrayIterator, $mode = RecursiveIteratorIterator::LEAVES_ONLY );
    $leaves =[];
    foreach( $recursiveIterator as $key => $value ){
        $leaves[] = $value;
    }
    return $leaves;
  }

  /**
   * check if an array is multidimensional
   *
   * @param array $array
   * @return boolean
   */
  public static function is_multidimensional_array($array) {
    foreach ($array as $value) {
      if (is_array($value)) return true;
    }
    return false;
  }
 }