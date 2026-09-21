<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\Traits;


trait CanRemoveExtraSlashesFromUrl
{
  protected function removeExtraSlashesFromUrl($url)
  {
    return preg_replace("/(?<!https:)(?<!http:)\/{2,}/", "/", $url);
  }
  
}