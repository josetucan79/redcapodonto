<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Handler\Error;

/**
 * Enumerates error types for the Api Handler User class
 */
class ParticipantHandlerError
{
    const VALIDATION_ERROR = '.ReceivedInvalidData';
    const PUSH_IDENTIFIER_NOT_SAVED = ".UserPushIdentifierNotSaved";
    const MESSAGE_NOT_SAVED = ".UserMessageNotSaved";
    const USER_PROPERTIES_NOT_SAVED = '.UserPropertiesNotSaved';
}
