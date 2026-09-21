<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Traits;

trait CanFlattenObjects
{

  /**
   * Undocumented function
   *
   * @param object|array $object
   * @param array $flattened
   * @param array $path
   * @param string $path_separator character that separates the elements of the path
   * @return array
   */
  private function flatten($object, $flattened=array(), $path=array(), $path_separator = ':')
  {
      foreach($object as $key => $value)
      {
          $path[] = $key;
          if(is_array($value) || is_object($value))
          {
              $flattened = $this->flatten($value, $flattened, $path);
              array_pop($path);
              continue;
          }
          $string_path = implode($path_separator, $path);
          $flattened[$string_path] = $value;
          array_pop($path);
      }
      return $flattened;
  }
}