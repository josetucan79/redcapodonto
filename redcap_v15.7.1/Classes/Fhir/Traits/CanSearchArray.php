<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Traits;

/**
 * classes using this trait can store temporary data
 * to prevent repetitive tasks and for faster access
 */
trait CanSearchArray {
    /**
   * apply a function to an array
   * and return true as soon as
   * the first element is true
   *
   * @param array $array
   * @param callable $fn
   * @return Boolean
   */
  private function array_any(array $array, callable $fn) {
    foreach ($array as $value) {
        if($fn($value)) {
            return true;
        }
    }
    return false;
  }

  /**
   * search for something in an array
   * using a user specified function.
   * Exit as soon as the first match is found
   *
   * @param array $items
   * @param callable $function
   * @return mixed
   */
  protected function array_find($items, $function) {
    foreach ($items as $item) {
      if (call_user_func_array($function, [$item]) === true) return $item;
    }
    return null;
  }

  /**
   * search for something in an array
   * using a user specified function.
   * Exit as soon as the first match is found
   *
   * @param array $items
   * @param callable $function
   * @return mixed the key of the found object or false
   */
  protected function array_indexOf($items, $function) {
    foreach ($items as $key => $item) {
      if (call_user_func_array($function, [$item, $key]) === true) return $key;
    }
    return false;
  }
  
}
