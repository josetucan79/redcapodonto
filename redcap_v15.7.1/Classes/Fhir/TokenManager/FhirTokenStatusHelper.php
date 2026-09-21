<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager;

class FhirTokenStatusHelper
{
    public static function getIcon($status)
    {
        switch ($status) {
            case FhirTokenDTO::STATUS_VALID:
                return '<i class="fas fa-check-circle text-success"></i>';
            case FhirTokenDTO::STATUS_FORBIDDEN:
                return '<i class="fas fa-ban text-danger"></i>';
            case FhirTokenDTO::STATUS_PENDING:
                return '<i class="fas fa-clock text-secondary"></i>';
            case FhirTokenDTO::STATUS_UNKNOWN:
                return '<i class="fas fa-circle-question text-secondary"></i>';
            case FhirTokenDTO::STATUS_INVALID:
            case FhirTokenDTO::STATUS_EXPIRED:
            default:
                return '<i class="fas fa-times-circle text-danger"></i>';
        }
    }
}