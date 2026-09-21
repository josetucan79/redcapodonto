<?php

namespace Vanderbilt\REDCap\Classes\MyCap\Api\Handler;

use Google\Exception;
use Vanderbilt\REDCap\Classes\MyCap\Annotation;
use Vanderbilt\REDCap\Classes\MyCap\Api\Handler\Error\ParticipantHandlerError;
use Vanderbilt\REDCap\Classes\MyCap\Api\DB\ParticipantDB;
use Vanderbilt\REDCap\Classes\MyCap\Api\DB\Project;
use Vanderbilt\REDCap\Classes\MyCap\Message;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\MyCapApi;
use Vanderbilt\REDCap\Classes\MyCap\Api\Response;

/**
 * MyCap API User actions.
 */
class ParticipantHandler
{
    /** @var array $actions Array of actions this handler implements */
    public static $actions = [
        "AUTHENTICATE_PARTICIPANT" => "authenticateParticipant",
        "GET_USER_ZERODATE" => "getUserZeroDate",
        "GET_USER_INSTALLDATE" => "getUserInstallDate",
        "SAVE_PARTICIPANT_PUSH_IDENTIFIER" => "saveParticipantPushIdentifier",
        "SAVE_USER_PROPERTIES" => "saveUserProperties",
        "GET_PARTICIPANT_MESSAGES" => "getParticipantMessages",
        "SAVE_PARTICIPANT_MESSAGE" => "saveParticipantMessage"
    ];

    /**
     * Authenticate a user given a study code and user code
     *
     * @param array $data
     */
    public function authenticate($data)
    {
        $this->sharedValidation($data);

        // Load Participant
        $participant = new ParticipantDB();
        $participants = $participant->loadByCode($data['par_code']);

        $response = ['recordId' => $participants['record']];

        // The project and user were found
        Response::sendSuccess($response);
    }

    /**
     * All requests need to verify that study code & participant code
     * were sent, and that the study and participant could be found.
     * This method ensures that the basic request is valid. If
     * yes, then return nothing. If no, send the appropriate
     * error response.
     *
     * @param array $data
     * @param array $params Any additional required parameters
     */
    private function sharedValidation($data, $params = [])
    {
        $requiredParams = array_merge(
            ["stu_code", "par_code"],
            $params
        );

        // All requests require a study code and a participant code
        MyCapApi::validateParameters(
            $data,
            $requiredParams
        );

        try {
            $stu_code = $data['stu_code'];
            $par_code = $data['par_code'];

            $proj = new Project();
            $projects = $proj->loadByCode($stu_code);

            $participant = new ParticipantDB();
            $participants = $participant->loadByCode($par_code);
        } catch (\Exception $e) {
            Response::sendError(
                400,
                ParticipantHandlerError::VALIDATION_ERROR,
                $e->getMessage()
            );
        }

    }

    /**
     * TL;DR... A user may have multiple push notification identifiers
     *
     * Push notification identifiers are created for users that allow notifications.
     * Push identifiers are device-specific. If user JOHNDOE allows push notifications
     * on his iPhone then an identifier will be saved using this action. If user JOHNDOE
     * installs app on his iPad and allows push notifications then another
     * identifier will be saved using this action. If JOHNDOE sells his iPhone and installs
     * app on his new iPhone then yet another push notification identifier will
     * be saved using this action.
     *
     * @param array $data
     */
    public function saveParticipantPushIdentifier($data)
    {
        $this->sharedValidation(
            $data,
            ["par_pushids"]
        );

        $newPushId = $data['par_pushids'];

        $participant = new ParticipantDB();
        $currentPushIds = $participant->getCurrentPushIds($data['par_code']);

        if ($currentPushIds !== null && strlen($currentPushIds)) {
            $allIds = json_decode($currentPushIds);
            if (!in_array(
                $newPushId,
                $allIds
            )) {
                $allIds[] = $newPushId;
            }
        } else {
            $allIds = [$newPushId];
        }

        $allIdsJson = json_encode($allIds);
        try {
            $flag = $participant->savePushIdentifier($allIdsJson, $data);
            if ($flag == false) {
                Response::sendError(
                    400,
                    ParticipantHandlerError::PUSH_IDENTIFIER_NOT_SAVED,
                    "Identifier not saved."
                );
            }
        } catch (\Exception $e) {
            Response::sendError(
                400,
                ParticipantHandlerError::PUSH_IDENTIFIER_NOT_SAVED,
                "Identifier not saved: " . $e->getMessage()
            );
        }

        Response::sendSuccess();
    }

    /**
     * Save user properties/attributes. Does NOT create a new user
     *
     * IMPORTANT: Install date and baseline date values will not be overwritten once set
     *
     * @param $data
     */
    public function saveUserProperties($data)
    {
        $this->sharedValidation($data);

        $errors = [];
        $warnings = [];

        // An install date and baseline date should only be set once by the mobile app. Do not allow overwrites
        if (array_key_exists(
            'joindate',
            $data
        )) {
            if (!ParticipantHandler::isDateFormatValid(
                $data['joindate'],
                'Y-m-d H:i:s'
            )) {
                $errors[] = 'joindate is not formatted correctly';
            } else {
                $participant = new ParticipantDB();
                $joinDate = $participant->getInstallDate($data['par_code'], $data['stu_code']);

                if ($joinDate == null || strlen($joinDate) == 0) {
                    $flag = $participant->saveInstallDate($data);
                    if ($flag == false) {
                        Response::sendError(
                            400,
                            ParticipantHandlerError::USER_PROPERTIES_NOT_SAVED,
                            "User property not saved."
                        );
                    } else {
                        $participants = $participant->loadByCode($data['par_code']);
                        $record = $participants['record'];

                        $projectId = MyCap::getProjectIdByCode($data['stu_code']);
                        $Proj = new \Project($projectId);

                        // Update Install Date (if applicable) - Update field value where action tag is @MC-PARTICIPANT-JOINDATE
                        $fields = \Form::getMyCapParticipantInstallDateFields($projectId ?? null);
                        $fields[] = $Proj->table_pk; // Add record id to return event, instance, etc.

                        if (!empty($fields)) {
                            \Form::saveMyCapInstallDateInfo($projectId, $fields, $record, $data['joindate']);
                        }

                        // Update Install Date UTC (if applicable) - Update field value where action tag is @MC-PARTICIPANT-JOINDATE-UTC
                        $fields = \Form::getMyCapParticipantInstallDateUTCFields($projectId ?? null);
                        $fields[] = $Proj->table_pk; // Add record id to return event, instance, etc.

                        if (!empty($fields)) {
                            \Form::saveMyCapInstallDateInfo($projectId, $fields, $record, $data['utcTime']);
                        }

                        // Update Timezone (if applicable) - Update field value where action tag is @MC-PARTICIPANT-TIMEZONE
                        $fields = \Form::getMyCapParticipantTimezoneFields($projectId ?? null);
                        $fields[] = $Proj->table_pk; // Add record id to return event, instance, etc.
                        if (!empty($fields)) {
                            \Form::saveMyCapInstallDateInfo($projectId, $fields, $record, $data['timezone']);
                        }
                        // Add new message "#JOINED PROJECT" to db
                        $time = NOW;
                        $uuid = MyCap::guid();
                        $sql = "INSERT INTO redcap_mycap_messages (uuid, project_id, `type`, from_server, `from`, `to`, body, sent_date, processed) VALUES
                                ('".$uuid."', '".$projectId."', '".Message::STANDARD."', '0', '".$data['par_code']."', '".Message::TOFROM_SERVER."', '".Message::AUTO_MSG_JOINED."', '".$time."', 1)";
                        db_query($sql);
                    }
                } else {
                    $warnings[] = 'joindate already exists and cannot be overwritten once set. No changes were made.';
                }
            }
        }

        if (array_key_exists(
            'zerodate',
            $data
        )) {
            if (!ParticipantHandler::isDateFormatValid(
                $data['zerodate'],
                'Y-m-d'
            )) {
                $errors[] = 'zerodate is not formatted correctly';
            } else {
                $participant = new ParticipantDB();
                $zeroDate = $participant->getBaselineDate($data['par_code'], $data['stu_code']);

                if ($zeroDate == null || strlen($zeroDate) == 0) {
                    $flag = $participant->saveBaselineDate($data);
                    if ($flag == false) {
                        Response::sendError(
                            400,
                            ParticipantHandlerError::USER_PROPERTIES_NOT_SAVED,
                            "User property not saved."
                        );
                    }
                } else {
                    $warnings[] = 'zerodate already exists and cannot be overwritten once set. No changes were made.';
                }
            }
        }

        if ($errors) {
            Response::sendError(
                400,
                ParticipantHandlerError::USER_PROPERTIES_NOT_SAVED,
                implode(
                    ', ',
                    $errors
                )
            );
        }

        $message = '';
        if ($warnings) {
            $message = ['note' => implode(
                ', ',
                $warnings
            )];
        }
        Response::sendSuccess($message);
    }

    /**
     * Is provided date string formatted correctly
     *
     * @param string $date
     * @param string $format
     * @return bool
     */
    private static function isDateFormatValid($date, $format)
    {
        $d = \DateTime::createFromFormat(
            $format,
            $date
        );
        return $d && $d->format($format) == $date;
    }

    /**
     * Get the baseline date for a user. Returns null if the project does not use the baseline date feature or a
     * baseline date is not set for the user.
     *
     * @param array $data
     */
    public function getUserZeroDate($data)
    {
        $this->sharedValidation($data);
        $participant = new ParticipantDB();
        $zeroDate = $participant->getBaselineDate($data['par_code'], $data['stu_code'], true);
        if (!strlen($zeroDate)) {
            $zeroDate = null;
        } else {
            // Return ISO 8601 date format: YYYY-MM-DD
            // Chop off hours and seconds if they are provided
            $zeroDate = substr(
                $zeroDate,
                0,
                10
            );
        }

        Response::sendSuccess(["zeroDate" => $zeroDate]);
    }

    /**
     * Save Messaged for participant
     *
     * @param array $data
     */
    public function saveParticipantMessage($data)
    {
        $this->sharedValidation(
            $data,
            ['stu_code', 'msg_id','msg_body','msg_sentdate']
        );

        $message = new Message();
        $message->loadByUuid($data['msg_id']);
        $message->id = $data['msg_id'];
        $message->studyId = MyCap::getProjectIdByCode($data['stu_code']);
        $message->type = (isset($data['msg_type'])) ? $data['msg_type'] : Message::STANDARD;
        $message->to = (isset($data['msg_to'])) ? $data['msg_to'] : Message::TOFROM_SERVER;
        // Do not overwrite value if it already exists
        if (!isset($message->from) || !strlen($message->from)) {
            $message->from = (isset($data['msg_from'])) ? $data['msg_from'] : $data['par_code'];
        }
        $message->body = $data['msg_body'];
        $message->sentDate = $data['msg_sentdate'];
        $message->receivedDate = (isset($data['msg_recieveddate'])) ? $data['msg_recieveddate'] : time();
        if (isset($data['msg_readdate'])) {
            $message->readDate = $data['msg_readdate'];
        }

        try {
            $message->validate();
        } catch (\Exception $e) {
            Response::sendError(
                400,
                ParticipantHandlerError::VALIDATION_ERROR,
                $e->getMessage()
            );
        }

        $flag = $message->save($data['doNotSave']);
        if ($flag == true) {
            if ($data['doNotSave'] == false && !$message->isAutoGeneratedMessage($data['msg_body'])) {
                $message->notifyUser($message->toArray(), $data['par_code']);
            }

            Response::sendSuccess(["message" => $this->makeMessage($message->toArray(), $data['par_code'])]);
        } else {
            Response::sendError(
                400,
                ParticipantHandlerError::MESSAGE_NOT_SAVED,
                "Message not saved."
            );
        }
    }

    /**
     * Format message in a way compatible with the MyCap app Message
     *
     * @param array $message
     * @param string $code
     * @return array
     */
    private function makeMessage($message, $code)
    {
        // Announcements are stored in the database as => from 'swaffoja' to '' (blank)
        // Announcements sent to participant as        => from '.Server'  to 'U-EXAMPLE00000'
        //
        // Standard messages are stored in database as => from 'swaffoja' to 'U-EXAMPLE00000'
        // Standard messages sent to participants as   => from '.Server'  to 'U-EXAMPLE00000'
        //
        // Standard messages from participants are     => from 'U-EXAMPLE00000' to '.Server'
        if ($message['msg_type'] == Message::ANNOUNCEMENT) {
            $to = $code;
            $from = Message::TOFROM_SERVER;
        } elseif ($message['msg_to'] == $code) {
            $to = $message['msg_to'];
            $from = Message::TOFROM_SERVER;
        } else {
            $to = Message::TOFROM_SERVER;
            $from = $message['msg_from'];
        }

        $data = [
            "identifier" => $message['msg_id'],
            "from" => $from,
            "to" => $to,
            "type" => $message['msg_type'],
            "sentDate" => (int)$message['msg_sentdate'],
            "body" => $message['msg_body']
        ];

        if (strlen($message['msg_receiveddate']) && (int)$message['msg_receiveddate'] != 0) {
            $data["receivedDate"] = (int)$message['msg_receiveddate'];
        }

        if ($message['msg_readdate'] !== null && strlen($message['msg_readdate']) && (int)$message['msg_readdate'] != 0) {
            $data["readDate"] = (int)$message['msg_readdate'];
        }

        return $data;
    }

    /**
     * Get list of messages of participant
     *
     * @param array $data
     * @return string
     */
    public function getParticipantMessages($data)
    {
        // Will send an error response if basic validation fails
        $this->sharedValidation($data);

        // Sort by sent date ascending or descending?
        $sortDirection = SORT_ASC;
        if (isset($data['sort']) && $data['sort'] == '.Desc') {
            $sortDirection = SORT_DESC;
        }

        $timestamp = 0;
        if (isset($data['after']) && is_numeric($data['after'])) {
            $timestamp = (int)$data['after'];
        }

        $retVal = [];

        $message = new Message();
        $messages = $message->listAllForParticipant($data['par_code'], $data['stu_code']);
        if ($sortDirection == SORT_ASC) {
            usort($messages, function ($a, $b) {
                $at = (int)$a['msg_sentdate'];
                $bt = (int)$b['msg_sentdate'];
                if ($at == $bt) {
                    return 0;
                }
                return ($at < $bt) ? -1 : 1;
            });
        } else {
            usort($messages, function ($a, $b) {
                $at = (int)$a['msg_sentdate'];
                $bt = (int)$b['msg_sentdate'];
                if ($at == $bt) {
                    return 0;
                }
                return ($at > $bt) ? -1 : 1;
            });
        }
        foreach ($messages as $m) {
            if ($timestamp !== 0 && (int)$m['msg_sentdate'] <= $timestamp) {
                continue;
            }

            if (str_starts_with($m['msg_body'], Message::AUTO_MSG_DELETED)) {
                $m['msg_body'] = Message::AUTO_MSG_DELETED;
            }

            $m['msg_body'] = str_replace(array(Message::AUTO_MSG_JOINED, Message::AUTO_MSG_REJOINED, Message::AUTO_MSG_DELETED),
                                        array('Joined Project', 'Rejoined Project', 'Deleted Project'),
                                        $m['msg_body']);
            $retVal[] = $this->makeMessage($m, $data['par_code']);
        }
        Response::sendSuccess(["messages" => $retVal]);
    }

    /**
     * Get the install date for a participant. Returns null if the participant not yet joined
     *
     * @param array $data
     */
    public function getUserInstallDate($data)
    {
        $this->sharedValidation($data);
        $participant = new ParticipantDB();
        $joinDate = $participant->getInstallDate($data['par_code'], $data['stu_code']);
        if (!strlen($joinDate)) {
            $joinDate = null;
        } else {
            // Return ISO 8601 date format: YYYY-MM-DD
            // Chop off hours and seconds if they are provided
            $joinDate = substr(
                $joinDate,
                0,
                10
            );
        }

        Response::sendSuccess(["installDate" => $joinDate]);
    }
}
