<?php

namespace Vanderbilt\REDCap\Classes\Utility\REDCapData;

class MetadataDTO {
    public $filter_count;
    public $result_count;
    public $total_count;
    public $page;
    public $perPage;
    public $totalPages;

    public function __construct($filter_count, $total_count, $page, $perPage) {
        $this->filter_count = $filter_count;
        $this->perPage = $perPage;
        $this->page = $page;
        $this->total_count = $total_count;
        $this->totalPages = ceil($filter_count / $perPage);

        // Calculate result_count
        if ($page < $this->totalPages) {
            $this->result_count = $perPage;
        } else {
            // Last page might have fewer records
            $this->result_count = $filter_count - ($perPage * ($this->totalPages - 1));
        }
    }
}
