<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication;

class ErrorHandlingService
{
    public function checkForErrors($data_array_src)
    {
        // Check if the source data is empty or contains errors
        if ($data_array_src === false || empty($data_array_src)) {
            return false;
        }
        return true;
    }

    public function renderErrorMessage($message)
    {
        // Generate HTML for displaying error messages to the user
        return '<div class="error">' . htmlspecialchars($message) . '</div>';
    }
}
