<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

interface ResourceInterface
{
  
  /**
   * return the relevant data for the resource
   *
   * @return array
   */
  public function getData();

  public static function getPropertyExtractors(): array;
  
  /**
   * return metadata (i.e. payload)
   *
   * @return array
   */
  public function getMetaData();

  /**
   * accept a visitor interface
   *
   * @param ResourceVisitorInterface $visitor
   * @return mixed
   */
  public function accept($visitor);
}