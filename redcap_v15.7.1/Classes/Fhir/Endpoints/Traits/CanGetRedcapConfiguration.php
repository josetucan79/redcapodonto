<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\Traits;

use DateTime;
use Project;
use System;

trait CanGetRedcapConfiguration
{
  private $redcap_configs;
  private $project_configs;

  protected function getRedcapConfigs()
  {
    if(!$this->redcap_configs) {
      $this->redcap_configs = System::getConfigVals();
    }
    return $this->redcap_configs;
  }

  protected function getProjectConfigs()
  {
    if(!$this->project_configs) {
      $this->project_configs = Project::getProjectVals();
    }
    return $this->project_configs;
  }
}