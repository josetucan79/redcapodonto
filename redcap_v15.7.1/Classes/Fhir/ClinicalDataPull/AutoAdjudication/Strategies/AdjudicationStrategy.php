<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\Strategies;

use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\CacheEntry;

abstract class AdjudicationStrategy  implements AdjudicationStrategyInterface
{

    /**
     *
     * @var CacheEntry
     */
    protected $cacheEntry;

    /**
     *
     * @param CacheEntry $cacheEntry
     */
    public function __construct(CacheEntry $cacheEntry)
    {
        $this->cacheEntry = $cacheEntry;
    }

}