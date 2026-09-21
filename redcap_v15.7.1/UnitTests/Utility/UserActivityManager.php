<?php
namespace Vanderbilt\REDCap\UnitTests\Utility;

class UserActivityManager {

    /**
     * Log in a user by inserting a log record with the current timestamp.
     *
     * @param string $username The username to log in.
     * @return bool|resource The result of the query, or false on failure.
     */
    public static function logInUser($username) {
        // Current timestamp represents an active login.
        $timestamp = date("Y-m-d H:i:s");
        $query = "INSERT INTO redcap_log_view (username, ts, user)
                  VALUES (?, ?, ?)";
        return db_query($query, [$username, $timestamp, $username]);
    }

    /**
     * Log out a user by removing entries that indicate the user is online,
     * then inserting a log record with a timestamp older than the auto-logout window.
     *
     * @param string $username The username to log out.
     * @return bool|resource The result of the final query, or false on failure.
     */
    public static function logOutUser($username) {
        global $autologout_timer;
        // Calculate the logout window timestamp.
        $logoutWindow = date("Y-m-d H:i:s", mktime(
            date("H"),
            date("i") - $autologout_timer,
            date("s"),
            date("m"),
            date("d"),
            date("Y")
        ));

        // Remove any entries that would mark the user as online.
        db_query("DELETE FROM redcap_log_view WHERE username = ? AND ts >= ?", [$username, $logoutWindow]);

        // Insert a log record with a timestamp older than the logout window,
        // ensuring the user is not considered online.
        $olderTimestamp = date("Y-m-d H:i:s", mktime(
            date("H"),
            date("i") - $autologout_timer - 5,
            date("s"),
            date("m"),
            date("d"),
            date("Y")
        ));
        $query = "INSERT INTO redcap_log_view (username, ts, user)
                  VALUES (?, ?, ?)";
        return db_query($query, [$username, $olderTimestamp, $username]);
    }
}
