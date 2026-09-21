<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\Strategies;

use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\CacheEntry;

interface AdjudicationStrategyInterface
{
  /**
   * compare two values
   * The comparison function must return
   * an integer less than, equal to, or greater
   * than zero if the first argument is considered
   * to be respectively less than, equal to, or
   * greater than the second.
   * 
   * A greater value is more relevant for the adjudication
   *
   * @param CacheEntry $item_a a record to be adjudicated
   * @param CacheEntry $item_b a record to be adjudicated
   * @return int 
   */
  public function compare($item_a, $item_b);

  // public function select(CacheEntry $item_a, CacheEntry $item_b):CacheEntry;

  /**
   *
   * @param CacheEntry $cacheEntry
   * @return CacheEntry cacheEntry to apply
   */
  // public function process(CacheEntry $cacheEntry):CacheEntry;
}