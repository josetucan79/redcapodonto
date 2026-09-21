<?php
namespace Vanderbilt\REDCap\Classes\Rewards\DTOs;


class ResultsMetadataDTO extends BaseDTO {
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

    /**
     * flag indicating if results are served from cache
     * 
     * @var string|false
     */
    public $cached;
}