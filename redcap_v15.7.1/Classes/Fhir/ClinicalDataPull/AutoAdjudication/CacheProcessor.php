<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication;

use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\Strategies\AdjudicationStrategy;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\Strategies\AdjudicationStrategyFactory;

/**
 * process a field in a specific record and event ID of a CDP project
 */
class CacheProcessor
{

    /**
     *
     * @var int
     */
    private $project_id;
    
    /**
     *
     * @var int
     */
    private $event_id;
    
    /**
     *
     * @var string
     */
    private $record_id;
    
    /**
     *
     * @var string
     */
    private $field_name;

    private $selected; // store the selected entry
    private $excluded = []; // track ids of entries to exclude
    
    /**
    *
    * @param int $project_id
    */
    public function __construct($project_id, $record_id, $event_id, $field_name)
    {
        $this->project_id = $project_id;
        $this->event_id = $event_id;
        $this->record_id = $record_id;
        $this->field_name = $field_name;
    }


    /**
     * get any NEW cached data (not adjudicated or excluded)
     * data is restricted to a specific:
     *  - project
     *  - event
     *  - record
     *  - field
     *
     * @return Generator
     */
    public function dataGenerator()
    {
        $subQuery = CacheEntry::getCacheEntriesQuery($this->project_id);
        // here we check just for non adjudicated; the record IDs provided should 
        $query_string =
            "SELECT *
            FROM ({$subQuery}) AS entries
            WHERE project_id=?
            AND event_id=?
            AND record=?
            AND field_name=?";
        $params = [
            $this->project_id, // for the subquery
            $this->project_id,
            $this->event_id,
            $this->record_id, // checkNull?
            $this->field_name, // checkNull?
        ];
        $result = db_query($query_string, $params);
        if(!$result) return false;
        while($row=db_fetch_assoc($result)) {
            yield new CacheEntry($row);
        }
    }


    /**
     * process all available cache entries for the current field
     *
     * @return void
     */
    public function process() {
        // reset
        $this->selected = null;
        $this->excluded = []; // keep track of excluded entries to update the database

        $generator = $this->dataGenerator();
        /** @var CacheEntry $selected */
        $this->selected = $generator->current();
        if(!($this->selected instanceof CacheEntry)) return; //no cached data available for this field
       

        if(!$this->selected->isTemporal()) return; // stop here; nothing to compare using an adjudication strategy

        //temporal context; parse all entries and selected based on the best strategy
        $strategy = AdjudicationStrategyFactory::make($this->selected); // set the strategy based on the first selected entry
        if( !($strategy instanceof AdjudicationStrategy)) throw new \Exception("Error: an adjudication strategy is necessary to auto-adjudicate temporal data", 1);
        $generator->next(); // move to next entry
        
        /** @var CacheEntry $cacheEntry */
        while($cacheEntry = $generator->current()) {
            $generator->next();
            
            if($cacheEntry->isEmpty()) {
                // exclude empty values
                $this->excluded[] = $cacheEntry->md_id;
                continue;
            }

            $comparison = $strategy->compare($this->selected, $cacheEntry);
            if($comparison<=0) $this->excluded[] = $cacheEntry->md_id;
            else {
                $this->excluded[] = $this->selected->md_id;
                $this->selected = $cacheEntry;
            }
        }
        return;
    }

    public function adjudicate() {
        $cacheEntry = $this->selected;
        if(!($cacheEntry instanceof CacheEntry)) return 0;
        $queryString = sprintf( "UPDATE redcap_ddp_records_data SET adjudicated=1, exclude=0 WHERE md_id=%u", $cacheEntry->md_id );
        $result = db_query($queryString);
        if(!$result) throw new \Exception("Error adjudicating entry ID {$cacheEntry->md_id}", 1);
        return db_affected_rows();
    }

    /**
     *
     * @return int
     */
    public function exclude() {
        $entries = $this->getExcluded();
        if(empty($entries)) return 0;
        $entriesList = implode(', ', $entries);
        $queryString = sprintf( "UPDATE redcap_ddp_records_data SET adjudicated=0, exclude=1 WHERE md_id IN (%s)", $entriesList);
        $result = db_query($queryString);
        if(!$result) throw new \Exception("Error excluding entries with the following IDs: {$entriesList}", 1);
        return db_affected_rows();
    }

    // getters
    public function getSelected() { return $this->selected; }
    public function getExcluded() { return $this->excluded; }
    public function getProjectID() { return $this->project_id; }
    public function getEventID() { return $this->event_id; }
    public function getRecordID() { return $this->record_id; }
    public function getFieldName() { return $this->field_name; }
}
