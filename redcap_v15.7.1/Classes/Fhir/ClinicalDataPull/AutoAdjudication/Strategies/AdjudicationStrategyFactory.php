<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\Strategies;

use DateTime;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\CacheEntry;

class AdjudicationStrategyFactory
{

  const STRATEGY_MIN = 'MIN';
  const STRATEGY_MAX = 'MAX';
  const STRATEGY_FIRST = 'FIRST';
  const STRATEGY_LAST = 'LAST';
  const STRATEGY_NEAR = 'NEAR';

  /**
   * get a comparison strategy
   *
   * @param CacheEntry $cacheEntry
   * @return AdjudicationStrategy
   */
  public static function make($cacheEntry)
  {
    $strategyName =  strtoupper($cacheEntry->strategy);
    switch ($strategyName) {
      case self::STRATEGY_MIN:
        return new Lowest($cacheEntry);
        break;
      case self::STRATEGY_MAX:
        return new Highest($cacheEntry);
        break;
      case self::STRATEGY_FIRST:
        return new Earliest($cacheEntry);
        break;
      case self::STRATEGY_LAST:
        return new Latest($cacheEntry);
        break;
      case self::STRATEGY_NEAR:
        return new Nearest($cacheEntry);
        break;
      default:
        return false;
        break;
    }
  }
}