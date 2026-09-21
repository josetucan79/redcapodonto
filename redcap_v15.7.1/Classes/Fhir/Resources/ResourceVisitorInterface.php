<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

/**
 * Manipulate a resource to fit the needs of the client (DDP, CDP, mapping helper...)
 */
interface ResourceVisitorInterface
{
  /**
   * Apply a behavior based on the class
   * implementing the visitor
   *
   * @param AbstractResource $resource
   * @return mixed
   */
  public function visit($resource);
}