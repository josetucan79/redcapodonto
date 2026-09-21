<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Middlewares;

use Exception;
use Vanderbilt\REDCap\Classes\Fhir\EhrRedirectManager;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\State;

/**
 * Middleware for user authentication.
 */
class AuthenticationMiddleware implements MiddlewareInterface {
    /**
     *
     * @var FhirLauncher
     */
    private $launcher;

    /**
     *
     * @param FhirLauncher $launcher
     */
    public function __construct(FhirLauncher $launcher)
    {
        $this->launcher = $launcher;
    }

    /**
     * Handles user authentication middleware logic.
     *
     * This method checks if the user is authenticated and redirects to the login page if not.
     *
     * @param State $state The state to process.
     * @return State Returns the processed state.
     */
    public function handle($state) {
         // Retrieve the current session from the launcher
        $session = $this->launcher->getSession();
        
        // Get the username from the session, if available
        $username = $_SESSION['username'] ?? false;

        // No username is set, call the login function to use the standard REDCap login form
        if ($username === false) {
            loginFunction();
            exit;
        }
        
        $storedUser = $session->user;

        if(is_null($storedUser)) {
            // session user was never set; setting it now
            $session->user = $username;
        }else if($storedUser !== $username) {
            // Username is set but does not match the session user, send a 401 error
            throw new Exception('Unauthorized access: You do not have permission to view the requested page', 401);
        }

        // once authenticated, remove the redirect logic
        EhrRedirectManager::disableEhrRedirect();

        return $state;
    }

}