<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

/**
 * This class encapsulates mapping information used during the REDCap clinical data adjudication process.
 * 
 * The `MappingInfoDTO` class is a data transfer object that aggregates key details about the mappings 
 * between REDCap fields and external source data, as well as metadata related to these mappings.
 * It serves as a structured container for this information, simplifying data handling across the application.
 * 
 * Purpose:
 * - This DTO consolidates all relevant mapping details, ensuring that the data is passed efficiently 
 *   and consistently between components involved in the adjudication workflow.
 * 
 * Common Use Cases:
 * - Used as a return value for functions that process mappings and need to provide a structured summary.
 * - Simplifies access to mapping-related metadata in downstream processes, such as adjudication workflows or data pulls.
 */
class MappingInfoDTO extends DTO
{
    /**
     * An array of unique REDCap fields involved in the mapping.
     *
     * @var array
     */
    public $mapped_fields;

    /**
     * An array of unique event IDs corresponding to the mapped fields.
     *
     * @var array
     */
    public $mapped_events;
    /**
     * A flat array of all mapping IDs associated with the mappings.
     *
     * @var array
     */
    public $map_id_list;

    /**
     * An associative array where keys are event IDs and values are the REDCap
     * fields designated as record identifiers.
     *
     * @var array
     */
    public $record_identifiers;

    /**
     * An associative array where keys are REDCap fields and values are the
     * corresponding temporal reference fields (if applicable).
     *
     * @var array
     */
    public $temporal_fields;

    public function __construct($params)
    {
        parent::__construct($params);
    }

    public function getMappedFields(): array { return $this->mapped_fields; }
    public function getMappedEvents(): array { return $this->mapped_events; }
    public function getMapIdList(): array { return $this->map_id_list; }
    public function getRecordIdentifiers(): array { return $this->record_identifiers; }
    public function getTemporalFields(): array { return $this->temporal_fields; }
}
