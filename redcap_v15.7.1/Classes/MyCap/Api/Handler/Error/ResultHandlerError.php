<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Handler\Error;

/**
 * Enumerates error types for the Api Handler Result class
 */
class ResultHandlerError {
    const INVALID_REQUEST = ".ResultsInvalidRequest";
    const FILE_NOT_PROVIDED = ".ResultsFileMissing";
    const FILE_NOT_SAVED = ".ResultsFileNotSaved";
}
