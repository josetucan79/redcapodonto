<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Traits;

/**
 * classes using this trait can store temporary data
 * to prevent repetitive tasks and for faster access
 */
trait CanHandleMemoryStorage {
  private $__STORAGE__ = [];

  /**
   * set a value at a specified path
   *
   * @param mixed $value
   * @param array $path
   * @return void
   */
  private function setMemoryStorage($value, $path)
  {
    $current = &$this->__STORAGE__;
    while($key = current($path)) {
      $current = &$current[$key];
      next($path);
    }
    $current = $value;
  }

  /**
   * get a value form the provided path
   *
   * @param array $path
   * @return mixed
   */
  private function getMemoryStorage($path)
  {
    $current = $this->__STORAGE__;
    while($key = current($path)) {
      $current = $current[$key] ?? null;
      next($path);
    }
    return $current;
  }

  /**
   * unset the value of the specified path
   *
   * @param array $path path to value to unset
   * @return void
   */
  private function unsetMemoryStorage($path)
  {
    $current = &$this->__STORAGE__;
    $last = array_pop($path);
    foreach ($path as $key) {
      $current = &$current[$key];
    }
    unset($current[$last]);

  }
  
}
