<?php

use Vanderbilt\REDCap\Classes\Rewards\Settings\Project\ProjectSettingsValueObject;

class UIHelper {

    public static function checkSettings(?ProjectSettingsValueObject $settings) {
        if ($settings instanceof ProjectSettingsValueObject) return true;
        $html = <<<HTML
        <div class="alert alert-danger">
            <p>Error retrieving settings for this project</p>
        </div>
        HTML;
        echo $html;
        exit;
    }
    
}
