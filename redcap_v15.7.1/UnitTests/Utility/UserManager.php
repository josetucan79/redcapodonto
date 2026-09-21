<?php
namespace Vanderbilt\REDCap\UnitTests\Utility;

use User;
use Logging;
use DateTime;
use Authentication;

class UserManager {

    const AUTH_TYPE_TABLE = 'table';
    const AUTH_TYPE_LDAP = 'ldap';
    const AUTH_TYPE_NONE = 'none';

    /**
     * Get a builder to create a new user
     * 
     * @param string $username Username for the new user
     * @return UserBuilder
     */
    public static function builder($username) {
        return new UserBuilder($username);
    }

    /**
     * create a user with default properties
     *
     * @param string $username
     * @param array $options
     * @return object|false
     */
    public static function createUser($username=null, $options=[]) {
        $username = $username ?? 'test-user';
        
        $defaultOptions = [
            'username' => $username,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'test@example.com',
        ];
        $options = array_merge($defaultOptions, $options);

        $userInfo = static::builder($username)
            ->withFirstName($options['first_name'])
            ->withLastName($options['last_name'])
            ->withEmail($options['email'])
            ->asTableUser()
            ->create();
        
            return $userInfo;
	}

    /**
     * create a user with default properties
     *
     * @param string $username
     * @param array $options
     * @return object|false
     */
    public static function createLdapUser($username=null, $options=[]) {
        $username = $username ?? 'test-user';
        
        $defaultOptions = [
            'username' => $username,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'test@example.com',
        ];
        $options = array_merge($defaultOptions, $options);

        $userInfo = static::builder($username)
            ->withFirstName($options['first_name'])
            ->withLastName($options['last_name'])
            ->withEmail($options['email'])
            ->asLdapUser()
            ->create();
        
            return $userInfo;
	}


    /**
     * Deletes a user from REDCap.
     *
     * This method removes the specified user from several REDCap tables. It will only
     * proceed if the current user is a SUPER_USER or ACCOUNT_MANAGER, and if an
     * ACCOUNT_MANAGER is attempting to delete a non-administrator account.
     *
     * The method deletes the user from:
     * - redcap_user_information
     * - redcap_user_rights
     * - redcap_auth (Table-based authentication)
     * - redcap_user_allowlist
     * - redcap_auth_history
     * - redcap_external_links_users
     * Additionally, it removes the user from the data access groups table and clears
     * them as a sponsor for any accounts.
     *
     * @param string $username The username of the user to delete.
     * @return bool Returns true if the user is successfully deleted; false otherwise.
     */
    public static function deleteUser($username) {
        if(!defined('SUPER_USER')) define('SUPER_USER', 1);
        if(!defined('ACCOUNT_MANAGER')) define('ACCOUNT_MANAGER', 1);

        // Ensure that account managers cannot delete administrator accounts.
        $thisUserInfo = User::getUserInfo($username);
        if ($thisUserInfo === false || (ACCOUNT_MANAGER && $thisUserInfo['super_user'])) {
            return false;
        }

        // Remove user from redcap_user_information
        $q1 = db_query("DELETE FROM redcap_user_information WHERE username = ?", [$username]);
        $q1_rows = db_affected_rows();

        // Remove user from redcap_user_rights
        $q2 = db_query("DELETE FROM redcap_user_rights WHERE username = ?", [$username]);

        // Remove user from redcap_auth (in case if using Table-based authentication)
        $q3 = db_query("DELETE FROM redcap_auth WHERE username = ?", [$username]);

        // Remove user from redcap_user_allowlist
        $q4 = db_query("DELETE FROM redcap_user_allowlist WHERE username = ?", [$username]);

        // Remove user from redcap_auth_history
        $q5 = db_query("DELETE FROM redcap_auth_history WHERE username = ?", [$username]);

        // Remove user from redcap_external_links_users
        $q6 = db_query("DELETE FROM redcap_external_links_users WHERE username = ?", [$username]);

        // Remove user from redcap_data_access_groups_users (no return check)
        db_query("DELETE FROM redcap_data_access_groups_users WHERE username = ?", [$username]);

        // If user is set as a sponsor in any accounts, remove them as a sponsor.
        db_query("UPDATE redcap_user_information SET user_sponsor = NULL WHERE user_sponsor = ?", [$username]);

        // If the critical queries ran as expected, log the event and return success.
        if ($q1_rows == 1 && $q1 && $q2 && $q3 && $q4 && $q5 && $q6) {
            Logging::logEvent(
                "",
                "redcap_user_information\nredcap_user_rights\nredcap_auth\nredcap_auth_history\nredcap_external_links_users",
                "MANAGE",
                $username,
                "username = '" . db_escape($username) . "'",
                "Delete user from REDCap"
            );
            return true;
        }

        return false;
    }
}
