<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Handler\Error;

/**
 * Enumerates error types for the Api Handler Project class
 */
class ProjectHandlerError
{
    const CODE_NOT_FOUND = ".ProjectNotFound";
    const FILE_NOT_FOUND = ".ProjectFileNotFound";
    const IMAGES_NOT_FOUND = ".ProjectImagesNotFound";
    const INVALID_CONFIG = ".ProjectInvalidConfig";
    const INVALID_FILE_CATEGORY = ".ProjectInvalidFileCategory";
    const INVALID_LANGUAGE_CODE = ".ProjectInvalidLanguageCode";
}
