<?php
namespace Vanderbilt\REDCap\UnitTests\Utility;

use Vanderbilt\REDCap\Classes\Utility\TransactionHelper;

class UserStatusManager {

    const PRIVILEGE_LOCK_RECORD = 'lock_record';
    const PRIVILEGE_LOCK_RECORD_MULTIFORM = 'lock_record_multiform';
    const PRIVILEGE_LOCK_RECORD_CUSTOMIZE = 'lock_record_customize';
    const PRIVILEGE_DATA_IMPORT_TOOL = 'data_import_tool';
    const PRIVILEGE_DATA_COMPARISON_TOOL = 'data_comparison_tool';
    const PRIVILEGE_DATA_LOGGING = 'data_logging';
    const PRIVILEGE_EMAIL_LOGGING = 'email_logging';
    const PRIVILEGE_FILE_REPOSITORY = 'file_repository';
    const PRIVILEGE_DOUBLE_DATA = 'double_data';
    const PRIVILEGE_USER_RIGHTS = 'user_rights';
    const PRIVILEGE_DATA_ACCESS_GROUPS = 'data_access_groups';
    const PRIVILEGE_GRAPHICAL = 'graphical';
    const PRIVILEGE_REPORTS = 'reports';
    const PRIVILEGE_DESIGN = 'design';
    const PRIVILEGE_ALERTS = 'alerts';
    const PRIVILEGE_CALENDAR = 'calendar';
    const PRIVILEGE_API_EXPORT = 'api_export';
    const PRIVILEGE_API_IMPORT = 'api_import';
    const PRIVILEGE_API_MODULES = 'api_modules';
    const PRIVILEGE_MOBILE_APP = 'mobile_app';
    const PRIVILEGE_MOBILE_APP_DOWNLOAD_DATA = 'mobile_app_download_data';
    const PRIVILEGE_RECORD_CREATE = 'record_create';
    const PRIVILEGE_RECORD_RENAME = 'record_rename';
    const PRIVILEGE_RECORD_DELETE = 'record_delete';
    const PRIVILEGE_DTS = 'dts';
    const PRIVILEGE_PARTICIPANTS = 'participants';
    const PRIVILEGE_DATA_QUALITY_DESIGN = 'data_quality_design';
    const PRIVILEGE_DATA_QUALITY_EXECUTE = 'data_quality_execute';
    const PRIVILEGE_DATA_QUALITY_RESOLUTION = 'data_quality_resolution';
    const PRIVILEGE_RANDOM_SETUP = 'random_setup';
    const PRIVILEGE_RANDOM_DASHBOARD = 'random_dashboard';
    const PRIVILEGE_RANDOM_PERFORM = 'random_perform';
    const PRIVILEGE_REALTIME_WEBSERVICE_MAPPING = 'realtime_webservice_mapping';
    const PRIVILEGE_REALTIME_WEBSERVICE_ADJUDICATE = 'realtime_webservice_adjudicate';
    const PRIVILEGE_MYCAP_PARTICIPANTS = 'mycap_participants';

    const TOOL_DATA_EXPORT_TOOL = 'data_export_tool';
    const TOOL_DATA_EXPORT_INSTRUMENTS = 'data_export_instruments';
    const TOOL_DATA_ENTRY = 'data_entry';
    const TOOL_API_TOKEN = 'api_token';


    /**
     * Make a user a project owner.
     *
     * This method will assign the given user as a project owner for the specified project.
     * It does so by inserting a row into the redcap_user_rights table with user_rights set to 1.
     *
     * @param string $username The username of the user.
     * @param int $project_id The project ID to assign the ownership. Defaults to 1.
     * @return bool|resource The result of the query, or false on failure.
     */
    public static function makeProjectOwner($username, $project_id = 1) {
        $results = [];
        try {
            TransactionHelper::beginTransaction();
            $results[] = static::setPrivilege($username, 'user_rights', 1, $project_id);
            $results[] = static::setPrivilege($username, 'design', 1, $project_id);
            TransactionHelper::commitTransaction();
        } catch (\Throwable $th) {
            TransactionHelper::rollbackTransaction();
            throw $th;
        }
        return $results;
    }

    /**
     * Make a user a CDIS user.
     *
     * This method will insert or update a record in the redcap_user_rights table
     * so that the 'realtime_webservice_adjudicate' flag is set to 1.
     * This flag, when set, qualifies the user as a CDIS user per your check query.
     *
     * @param string $username The username of the user.
     * @param int $project_id The project ID in which to update the user rights. Defaults to 1.
     * @return bool|resource The result of the query, or false on failure.
     */
    public static function makeCdisUser($username, $project_id = 1) {
        return static::setPrivilege($username, 'realtime_webservice_adjudicate', 1, $project_id);
    }

    /**
     * Suspend a user by setting the user_suspended_time field to the current timestamp.
     *
     * @param string $username The username of the user to suspend.
     * @return bool|resource The result of the update query, or false on failure.
     */
    public static function suspendUser($username) {
        $timestamp = date("Y-m-d H:i:s");
        $query = "UPDATE redcap_user_information SET user_suspended_time = ? WHERE username = ?";
        return db_query($query, [$timestamp, $username]);
    }

    /**
     * Unsuspend a user by clearing the user_suspended_time field (setting it to NULL).
     *
     * @param string $username The username of the user to unsuspend.
     * @return bool|resource The result of the update query, or false on failure.
     */
    public static function unsuspendUser($username) {
        $query = "UPDATE redcap_user_information SET user_suspended_time = NULL WHERE username = ?";
        return db_query($query, [$username]);
    }

    public static function setTool($username, $tool, $value, $project_id=1) {
        $allowedTools = [
            static::TOOL_DATA_EXPORT_TOOL => NULL,
            static::TOOL_DATA_EXPORT_INSTRUMENTS => NULL,
            static::TOOL_DATA_ENTRY => NULL,
            static::TOOL_API_TOKEN => NULL,
        ];
        
        // Validate the provided privilege column name
        if (!array_key_exists($tool, $allowedTools)) {
            throw new \InvalidArgumentException("Invalid tool: " . $tool);
        }
        
        $tool = db_escape($tool);
        $value = $value ?? $allowedTools[$tool];

        // Build the query using the provided column name.
        $query = "INSERT INTO redcap_user_rights (username, project_id, $tool)
                  VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE $tool = VALUES($tool)";

        return db_query($query, [$username, $project_id, $value]);
    }

    /**
     * Set the API token for a user.
     *
     * This method assigns an API token for the given user by inserting a new record
     * into the redcap_user_rights table, or updating the record if one already exists.
     *
     * @param string $username The username of the user.
     * @param string $apiToken The API token to assign.
     * @param int $project_id The project ID associated with this record (default is 1).
     * @return bool|resource The result of the query, or false on failure.
     */
    public static function setApiToken($username, $apiToken, $project_id = 1) {
        return static::setTool($username, static::TOOL_API_TOKEN, $apiToken, $project_id);
    }

    /**
     * Set a given privilege to a specific value for a user.
     *
     * This method will update the specified privilege column for the provided user
     * and project. If no record exists, it will create one.
     *
     * @param string $username The username of the user.
     * @param int $project_id The project ID to update. Defaults to 1.
     * @param string $privilege The privilege column name to update.
     * @param mixed $value The value to set for the privilege.
     * @return bool|resource The result of the query, or false on failure.
     * @throws \InvalidArgumentException If the provided privilege is not allowed.
     */
    public static function setPrivilege($username, $privilege, $value, $project_id = 1) {
        // Define an array of allowed privilege columns
        $allowedPrivileges = [
            static::PRIVILEGE_LOCK_RECORD => 0,
            static::PRIVILEGE_LOCK_RECORD_MULTIFORM => 0,
            static::PRIVILEGE_LOCK_RECORD_CUSTOMIZE => 0,
            static::PRIVILEGE_DATA_IMPORT_TOOL => 1,
            static::PRIVILEGE_DATA_COMPARISON_TOOL => 1,
            static::PRIVILEGE_DATA_LOGGING => 1,
            static::PRIVILEGE_EMAIL_LOGGING => 0,
            static::PRIVILEGE_FILE_REPOSITORY => 1,
            static::PRIVILEGE_DOUBLE_DATA => 0,
            static::PRIVILEGE_USER_RIGHTS => 1,
            static::PRIVILEGE_DATA_ACCESS_GROUPS => 1,
            static::PRIVILEGE_GRAPHICAL => 1,
            static::PRIVILEGE_REPORTS => 1,
            static::PRIVILEGE_DESIGN => 0,
            static::PRIVILEGE_ALERTS => 0,
            static::PRIVILEGE_CALENDAR => 1,
            static::PRIVILEGE_API_EXPORT => 0,
            static::PRIVILEGE_API_IMPORT => 0,
            static::PRIVILEGE_API_MODULES => 0,
            static::PRIVILEGE_MOBILE_APP => 0,
            static::PRIVILEGE_MOBILE_APP_DOWNLOAD_DATA => 0,
            static::PRIVILEGE_RECORD_CREATE => 1,
            static::PRIVILEGE_RECORD_RENAME => 0,
            static::PRIVILEGE_RECORD_DELETE => 0,
            static::PRIVILEGE_DTS => 0, // DTS adjudication page
            static::PRIVILEGE_PARTICIPANTS => 1,
            static::PRIVILEGE_DATA_QUALITY_DESIGN => 0,
            static::PRIVILEGE_DATA_QUALITY_EXECUTE => 0,
            static::PRIVILEGE_DATA_QUALITY_RESOLUTION => 0, //'0=No access, 1=View only, 2=Respond, 3=Open, close, respond, 4=Open only, 5=Open and respond'
            static::PRIVILEGE_RANDOM_SETUP => 0,
            static::PRIVILEGE_RANDOM_DASHBOARD => 0,
            static::PRIVILEGE_RANDOM_PERFORM => 0,
            static::PRIVILEGE_REALTIME_WEBSERVICE_MAPPING => 0,  // User can map fields for RTWS
            static::PRIVILEGE_REALTIME_WEBSERVICE_ADJUDICATE => 0,  // User can adjudicate data for RTWS
            static::PRIVILEGE_MYCAP_PARTICIPANTS => 0,
            // add other allowed privilege columns here as needed
        ];

        
        // Validate the provided privilege column name
        if (!array_key_exists($privilege, $allowedPrivileges)) {
            throw new \InvalidArgumentException("Invalid privilege: " . $privilege);
        }
        
        $privilege = db_escape($privilege);
        $value = $value ?? $allowedPrivileges[$privilege];

        // Build the query using the provided column name.
        $query = "INSERT INTO redcap_user_rights (username, project_id, $privilege)
                  VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE {$privilege} = VALUES($privilege)";

        return db_query($query, [$username, $project_id, $value]);
    }
}
