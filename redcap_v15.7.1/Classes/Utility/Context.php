<?php
namespace Vanderbilt\REDCap\Classes\Utility;

use User;

class Context
{
    const CURRENT_USER = 'current_user';
    const PROJECT_ID = 'project_id';
    const EVENT_ID = 'event_id';
    const RECORD_ID = 'record_id';
    const ARM_NUMBER = 'arm_number';

    protected static $is_ready = false;
    protected static $current_user;
    protected static $ui_id;
    protected static $username;
    protected static $project_id;
    protected static $event_id;
    protected static $arm_number;
    protected static $record_id;

    /** @param array $properties */
    public static function initialize(array $properties) {
        foreach ($properties as $key => $value) {
            self::setProperty($key, $value);
        }
        static::$is_ready = true;
    }

    public static function isReady(): bool { return static::$is_ready; }

    /** @return array */
    public static function getCurrentUser() { return self::$current_user; }

    /** @return int */
    public static function getUIID() { return self::$ui_id; }

    public static function getUsername() { return self::$username; }

    /** @return int */
    public static function getProjectId() { return self::$project_id; }
    
    /** @return int */
    public static function getEventId() { return self::$event_id; }

    /** @return int */
    public static function getArmNumber() { return self::$arm_number; }

    /** @return int|string */
    public static function getRecordId() { return self::$record_id; }
    
    /** @param int $current_user */
    public static function setCurrentUser($ui_id) {
        self::$ui_id = $ui_id;
        self::$current_user = $userInfo = User::getUserInfoByUiid($ui_id);
        self::$username = $userInfo['username'] ?? null;
    }
    
    /** @param int $project_id */
    public static function setProjectId($project_id) { self::$project_id = $project_id; }
    
    /** @param int $event_id */
    public static function setEventId($event_id) { self::$event_id = $event_id; }
    
    /** @param int $event_id */
    public static function setArmNumber($arm_number) { self::$arm_number = $arm_number; }

    /** @param int|string $record_id */
    public static function setRecordId($record_id) { self::$record_id = $record_id; }

    /** Initializes the context from environment variables. */
    public static function fromEnvironment() {
        $properties = [
            self::PROJECT_ID => $_GET['pid'] ?? null,
            self::EVENT_ID => $_GET['event_id'] ?? null,
            self::RECORD_ID => $_GET['id'] ?? null,
            self::CURRENT_USER => defined('UI_ID') ? constant('UI_ID') : null,
            self::ARM_NUMBER => getArm(),
        ];
        self::initialize($properties);
    }

    /** @param string $key @param mixed $value */
    protected static function setProperty($key, $value) {
        $method = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        if (method_exists(__CLASS__, $method)) {
            self::$method($value);
        }
    }
}
