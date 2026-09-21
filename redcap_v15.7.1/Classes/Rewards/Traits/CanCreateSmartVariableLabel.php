<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Traits;

use Piping;

trait CanCreateSmartVariableLabel {

    private function applySmartVariables($text, $project_id, $record_id, $event_id) {
        return Piping::replaceVariablesInLabel(
            $text,
            $record = $record_id,
            $event_id = $event_id,
            $instance = 1,
            $record_data = [],
            $replaceWithUnderlineIfMissing = true,
            $project_id = $project_id,
            $wrapValueInSpan = false,
            $repeat_instrument = "",
            $recursiveCount = 1,
            $simulation = false,
            $applyDeIdExportRights = false,
            $form = null,
            $participant_id = null,
            $returnDatesAsYMD = false,
            $ignoreIdentifiers = false,
            $isEmailContent = false,
            $isPDFContent = false,
            $preventUserNumOrDateFormatPref = false,
            $mlm_target_lang = false,
            $decodeLabel = true
        );
    }

}