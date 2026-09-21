<?php
namespace Vanderbilt\REDCap\Classes\Traits;

use User;
use Exception;
use Authentication;

trait CanCheckCredentials{

    /**
     * check if credentials are valid
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function checkCredentials($username, $password)
    {
        // temporarily adjust the POST superglobal and load the DSN
        $setDSN = function($username, $password) {
            $temp = [
                'username' => @$_POST['username'],
                'password' => @$_POST['password'],
            ]; // save reference for restoring the original values
            $_POST['username'] = $username;
            $_POST['password'] = $password;
            Authentication::setDSNs();
            // cleanup
            $_POST['username'] = @$temp['username'];
            $_POST['password'] = @$temp['password'];
            // if null, just delete from the array
            if(is_null($_POST['username'])) unset($_POST['username']);
            if(is_null($_POST['password'])) unset($_POST['password']);
        };

        $setDSN($username, $password);

        $valid = checkUserPassword($username, $password, $authSessionName = "credentials_test");
        return $valid===true;
    }
    
}