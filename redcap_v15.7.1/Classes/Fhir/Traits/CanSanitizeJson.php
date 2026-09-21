<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Traits;

/**
 * classes using this trait can store temporary data
 * to prevent repetitive tasks and for faster access
 */
trait CanSanitizeJson {
  
  protected function htmlSpecialChars1($json) {
    return json_decode(htmlspecialchars(json_encode( $json ), ENT_NOQUOTES));
  }

  protected function htmlSpecialChars($data) {
    if (is_array($data)) {
        foreach ( $data as $key => $value ) {
            $data[htmlspecialchars($key)] = $this->htmlSpecialChars($value);
        }
    } else if (is_object($data)) {
        $values = get_class_vars(get_class($data));
        foreach ( $values as $key => $value ) {
            $data->{htmlspecialchars($key)} = $this->htmlSpecialChars($value);
        }
    } else {
        $data = htmlspecialchars($data);
    }
    return $data;
  }
  
}
