<?php
namespace Vanderbilt\REDCap\UnitTests\Utility;

use Authentication;
use DateTime;
use User;

/**
 * Builder class for creating REDCap users
 */
class UserBuilder {
    private $username;
    private $first_name = 'John';
    private $last_name = 'Doe';
    private $email = 'test@example.com';
    private $auth_type = UserManager::AUTH_TYPE_TABLE;
    private $password = null;
    private $additional_options = [];
    
    /**
     * Create a new UserBuilder instance
     * 
     * @param string $username The username for the new user
     */
    public function __construct($username) {
        $this->username = $username;
        $this->password = generateRandomHash(8);
    }
    
    /**
     * Check if the username already exists
     * 
     * @return bool True if username exists, false otherwise
     */
    private function usernameExists() {
        $query = "SELECT ui_id FROM redcap_user_information WHERE username = ?";
        $result = db_query($query, [$this->username]);
        if($row = db_fetch_assoc($result)) return true;
        return false;
    }
    
    /**
     * Set the first name for the user
     * 
     * @param string $first_name
     * @return UserBuilder
     */
    public function withFirstName($first_name) {
        $this->first_name = $first_name;
        return $this;
    }
    
    /**
     * Set the last name for the user
     * 
     * @param string $last_name
     * @return UserBuilder
     */
    public function withLastName($last_name) {
        $this->last_name = $last_name;
        return $this;
    }
    
    /**
     * Set the email for the user
     * 
     * @param string $email
     * @return UserBuilder
     */
    public function withEmail($email) {
        $this->email = $email;
        return $this;
    }
    
    /**
     * Set the password for table-based authentication
     * 
     * @param string $password
     * @return UserBuilder
     */
    public function withPassword($password) {
        $this->password = $password;
        return $this;
    }
    
    /**
     * Set as a table-based authentication user
     * 
     * @return UserBuilder
     */
    public function asTableUser() {
        $this->auth_type = UserManager::AUTH_TYPE_TABLE;
        return $this;
    }
    
    /**
     * Set as an LDAP authentication user (no auth entry)
     * 
     * @return UserBuilder
     */
    public function asLdapUser() {
        $this->auth_type = UserManager::AUTH_TYPE_LDAP;
        return $this;
    }
    
    /**
     * Set as a user with no authentication entries
     * 
     * @return UserBuilder
     */
    public function withNoAuth() {
        $this->auth_type = UserManager::AUTH_TYPE_NONE;
        return $this;
    }
    
    /**
     * Add any additional options
     * 
     * @param array $options Additional options to add
     * @return UserBuilder
     */
    public function withOptions(array $options) {
        $this->additional_options = array_merge($this->additional_options, $options);
        return $this;
    }
    
    /**
     * Create the user in the database
     * 
     * @return object|false User info or false if creation failed
     */
    public function create() {
        // Check if username already exists
        if ($this->usernameExists()) {
            return false;
        }
        
        // Create user information entry
        $this->createUserInformationEntry();
        
        // Handle authentication based on type
        if ($this->auth_type === UserManager::AUTH_TYPE_TABLE) {
            $password_salt = Authentication::generatePasswordSalt();
            $hashed_password = Authentication::hashPassword($this->password, $password_salt);
            
            $this->createAuthEntry($hashed_password, $password_salt);
            $this->createAuthHistoryEntry($hashed_password);
        }
        
        // Return user info
        return User::getUserInfo($this->username);
    }
    
    /**
     * Create an entry in the user_information table
     * 
     * @return bool Success or failure
     */
    private function createUserInformationEntry() {
        $params = [
            $this->username,
            $this->first_name,
            $this->last_name,
            $this->email,
        ];
        $placeholders = dbQueryGeneratePlaceholdersForArray($params);

        $query = "INSERT INTO redcap_user_information
            (`username`, `user_firstname`, `user_lastname`, `user_email`)
            VALUES ($placeholders)";
        return db_query($query, $params);
    }
    
    /**
     * Create an entry in the auth table
     * 
     * @param string $hashed_password
     * @param string $password_salt
     * @return bool Success or failure
     */
    private function createAuthEntry($hashed_password, $password_salt) {
        $params = [ $this->username, $hashed_password, $password_salt ];
        $placeholders = dbQueryGeneratePlaceholdersForArray($params);
        $query = "INSERT INTO redcap_auth (`username`, `password`, `password_salt`) VALUES($placeholders)";
        return db_query($query, $params);
    }
    
    /**
     * Create an entry in the auth_history table
     * 
     * @param string $hashed_password
     * @return bool Success or failure
     */
    private function createAuthHistoryEntry($hashed_password) {
        $timestamp = (new DateTime())->format('Y-m-d H:i:s');
        $params = [$this->username, $hashed_password, $timestamp];
        $placeholders = dbQueryGeneratePlaceholdersForArray($params);
        $query = "INSERT INTO redcap_auth_history (`username`, `password`, `timestamp`) VALUES($placeholders)";
        return db_query($query, $params);
    }
}