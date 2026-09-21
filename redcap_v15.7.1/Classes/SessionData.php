<?php

namespace Vanderbilt\REDCap\Classes;

/**
 * Class SessionData
 * Handles session storage with support for flash data and old input handling.
 */
class SessionData {

    const NEXT_FLASH_KEY = '_NEXT_FLASH'; // Storage for data that will become flash data on the next request
    const FLASH_KEY = '_FLASH';
    const OLD_KEY = '_OLD';

    /**
     * @var SessionData The singleton instance of the session manager.
     */
    private static $instance = null;

    /**
     * Constructor is private to prevent instantiation and ensure singleton usage.
     */
    private function __construct() {
        $this->initialize();
		$this->prepareFlashData();
    }

    /**
     * Moves data from NEXT_FLASH_KEY to FLASH_KEY, making it available for this request only,
     * and clearing it out immediately so it does not persist to another request.
     */
    private function prepareFlashData() {
        if (isset($_SESSION[self::NEXT_FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = $_SESSION[self::NEXT_FLASH_KEY];
            unset($_SESSION[self::NEXT_FLASH_KEY]);
        } else {
            unset($_SESSION[self::FLASH_KEY]); // Ensure FLASH_KEY is cleared if no new data was flashed
        }
    }
    /**
     * Gets the singleton instance of the SessionData class.
     * 
     * @return SessionData The singleton instance.
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initializes the session by starting it if it has not been started already.
     */
    private function initialize() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Retrieves a session value by key, with an optional default.
     * 
     * @param string $key The session key to retrieve.
     * @param mixed $default The default value to return if the key does not exist.
     * @return mixed The value from the session, or the default value.
     */
    public function get($key, $default = null) {
        return $_SESSION[self::FLASH_KEY][$key] ?? $_SESSION[$key] ?? $default;
    }

    /**
     * Stores a value in the session under a specified key.
     * 
     * @param string $key The key under which to store the value.
     * @param mixed $value The value to store.
     */
    public function put($key, $value) {
        $_SESSION[$key] = $value;
    }

    /**
     * Stores a value in the session that can be used during the next request (flash data).
     * 
     * @param string $key The key under which to store the flash value.
     * @param mixed $value The value to flash.
     */
    public function flash($key, $value) {
		$_SESSION[self::NEXT_FLASH_KEY][$key] = $value;
    }

    /**
     * Clears all stored flash data from the session.
     */
    public function unflash() {
        unset($_SESSION[self::FLASH_KEY]);
    }

    /**
     * Sets data that can be fetched once via old() and is then deleted, typically used for form values.
     * 
     * @param string $key The key under which to store the old value.
     * @param mixed $value The value to store as old.
     */
    public function setOld($key, $value) {
        $_SESSION[self::OLD_KEY][$key] = $value;
    }

    /**
     * Retrieves and deletes an old value from the session, typically used to repopulate form fields.
     * 
     * @param string $key The key of the old value to retrieve.
     * @param mixed $default The default value if the key does not exist.
     * @return mixed The old value, or default if not available.
     */
    public function old($key, $default = null) {
        $value = $_SESSION[self::OLD_KEY][$key] ?? $default;
        unset($_SESSION[self::OLD_KEY][$key]);  // Ensure the data is fetched only once
        return $value;
    }
}