<?php
namespace Vanderbilt\REDCap\Classes\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

class ResultsMetadataDTO extends DTO {
    /**
     *
     * @var int
     */
    public $total;

    /**
     *
     * @var int
     */
    public $page;

    /**
     *
     * @var int
     */
    public $perPage;

    /**
     *
     * @var int
     */
    public $totalPages;
}