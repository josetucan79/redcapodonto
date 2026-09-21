<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Error;

/**
 * Enumerates error types for the API class
 */
class ApiError
{
    const MISSING_SIGNATURE = ".MissingSignature";
    const INVALID_SIGNATURE = ".InvalidSignature";
    const MISSING_EXPIRATION = ".MissingExpiration";
    const EXPIRED = ".Expired";
    const MISSING_ACTION = ".MissingAction";
    const INVALID_ACTION = ".InvalidAction";
    const MISSING_PARAMETER = ".MissingParameter";
}
