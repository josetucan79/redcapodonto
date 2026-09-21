<?php
namespace Vanderbilt\REDCap\Classes\Cache;

use DateTime;
use Exception;


class Utils {

    /**
     * get the current page
     * if the request is AJAX, the provide the source page
     *
     * @return string
     */
    public static function currentPage() {
        $extractPage = function($url) {
            $parts = explode(APP_PATH_WEBROOT, $url);
            $lastPart = end($parts);
            return $lastPart;
        };

        if($GLOBALS['isAjax']) {
            $url = $_SERVER['HTTP_REFERER'] ?? '';
        } else {
            $url = $_SERVER['REQUEST_URI'] ?? '';
        }
        return $extractPage($url);
    }

    /**
     * get the current user
     *
     * @return string|null
     */
    public static function currentUser() { return  defined('USERID') ? USERID : null; }

    public static function sanitizeFileName($fileName) {
        // Replace forbidden characters with an underscore
        $sanitized = preg_replace('/[\/\*?"<>|:]/', '_', $fileName);
        // remove non-ASCII characters
        $sanitized = preg_replace('/[^(\x20-\x7F)]*/','', $sanitized);
        return $sanitized;
    }

    /**
     * Creates a DateTime object from a provided string.
     *
     * This function attempts to create a DateTime object based on a provided string.
     * It returns the DateTime object if the string is a valid date/time format.
     * If the input is not a string or the DateTime object cannot be created,
     * the function returns false.
     *
     * @param mixed $input The date/time string from which to create the DateTime object.
     * 
     * @return DateTime|false The created DateTime object, or false on failure.
     */
    public static function createDateTime($input) {
        // Check if the input is a string
        if (!is_string($input)) {
            return false;
        }
    
        try {
            // Attempt to create a DateTime object
            $dateTime = new DateTime($input);
            return $dateTime;
        } catch (Exception $e) {
            // Return false if DateTime creation fails
            return false;
        }
    }
    
}