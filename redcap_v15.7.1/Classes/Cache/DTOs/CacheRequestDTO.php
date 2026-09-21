<?php
namespace Vanderbilt\REDCap\Classes\Cache\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

class CacheRequestDTO extends DTO {
    
    public $ts;
    public $key;
    public $value;
    public $cacheMiss;
    public $page;

    public function __toString()
    {
        $string = '';
        $string .= $this->cacheMiss ? 'fresh data' : 'cached data';
        $string .= " - " . $this->ts ?? '';
        return $string;
    }
}