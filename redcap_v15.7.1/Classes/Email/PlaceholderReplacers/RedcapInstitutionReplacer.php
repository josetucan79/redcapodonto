<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;

class RedcapInstitutionReplacer extends BaseReplacer {

    public function __construct() {
        $config = REDCapConfigDTO::fromDB();
        $this->value = $config->institution;
    }

    public static function token() { return 'redcap_institution'; }
}