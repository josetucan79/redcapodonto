<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo;

use Session;
use Renderer;
use Exception;
use ErrorException;
use eftec\bladeone\BladeOne;
use Duo\DuoUniversal\DuoException;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers\Email;
use Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers\Domain;
use Vanderbilt\REDCap\Classes\TwoFA\AbstractTwoFactorAuthentication;
use Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers\IgnoreDots;
use Vanderbilt\REDCap\Classes\TwoFA\TwoFactorAuthenticationInterface;
use Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers\CaseInsensitive;
use Vanderbilt\REDCap\Classes\TwoFA\Duo\UsernameMatchers\ReplaceDotsWithSpaces;

/**
 * Facade for the Duo 2FA
 * 
 * steps:
 * - launchUniversalPrompt
 * - healthCheck
 * - redirectToDuo
 * - authenticate
 */
class Duo extends AbstractTwoFactorAuthentication implements TwoFactorAuthenticationInterface {

    const FAIL_MODE_OPEN = "OPEN";
    const TWO_FACTOR_METHOD_NAME = 'DUO';

    /**
     *
     * @var Client
     */
    private $client;

    private $fail_mode;

    /**
     *
     * @var BladeOne
     */
    private $renderer;

    public function __construct($client_id, $client_secret, $api_hostname)
    {
        Session::init(); // session is needed to keep track of user and state
        $this->setClient(
            $client_id,
            $client_secret,
            $api_hostname,
            self::getCallbackUri(),
            self::FAIL_MODE_OPEN
        );
        $this->setRenderer();
    }

    public static function getType(): string {
        return self::TWO_FACTOR_METHOD_NAME;
    }

    public function getUsernameFromSession() {
        return $username = @$_SESSION['username'];
    }

    private function setRenderer() {
        $this->renderer = Renderer::getBlade(__DIR__.'/templates');
        $this->renderer->share('APP_PATH_WEBROOT_FULL', APP_PATH_WEBROOT_FULL);
        $this->renderer->share('APP_PATH_IMAGES', APP_PATH_IMAGES);
        $this->renderer->share('APP_PATH_CSS', APP_PATH_CSS);
        $this->renderer->share('APP_PATH_WEBROOT_PARENT', APP_PATH_WEBROOT_PARENT);
        $this->renderer->share('APP_PATH_WEBPACK', APP_PATH_WEBPACK);
    }

    public function getClient() { return $this->client; }

    private function setClient($client_id, $client_secret, $api_hostname, $redirect_uri, $fail_mode)  {
        try {
            $this->client = new Client(
                $client_id,
                $client_secret,
                $api_hostname,
                $redirect_uri
            );
            $this->fail_mode = $fail_mode;
        } catch (DuoException $e) {
            throw new ErrorException("*** Duo config error. Verify the values in duo.conf are correct ***\n" . $e->getMessage());
        }
    }

    /**
     * redirect the user to the Duo authentication page
     *
     * @param string $username
     * @param string $state
     * @return void
     */
    public function redirectToDuo($username, $state) {
        # Redirect to prompt URI which will redirect to the client's redirect URI after 2FA
        $prompt_uri = $this->client->createAuthUrl($username, $state);
        header("Location: {$prompt_uri}", TRUE, 302);
    }

    /**
     * check if Duo is available and if the username is valid
     *
     * @param string $username
     * @param string $string
     * @return void
     */
    function healthCheck($username, $state) {
        try {
            # Check user's first factor
            if (empty($username)) {
                $args["message"] = "Incorrect username or password";
                $render_template = "login";
            }else {
                $this->client->healthCheck();
            }
        } catch (DuoException $e) {
            \Logging::logEvent('',"","ERROR",'',$display="",$description=$e->getMessage());
            if ($this->fail_mode == self::FAIL_MODE_OPEN) {
                # If we're failing open, errors in 2FA still allow for success
                $args["message"] = "Login 'Successful', but 2FA Not Performed. Confirm Duo client/secret/host values are correct";
                $render_template = "success";
            } else {
                # Otherwise the login fails and redirect user to the login page
                $store = $this->restoreState($state);
                $args["message"] = "2FA Unavailable. Confirm Duo client/secret/host values are correct";
                $args["launchPage"] = $store->launchPage();
                $render_template = "login";
            }
            $html = $this->renderer->run($render_template, compact('args'));
            return print($html);
        }
    }

    /**
     * make a custom session for DUO
     *
     * @param string $username
     * @param string $redirectUrl
     * @return string the session ID
     */
    public static function makeSession($username, $redirectUrl) {
        $config = REDCapConfigDTO::fromDB();
        $two_factor_auth_duo_ikey = $config->two_factor_auth_duo_ikey;
        $two_factor_auth_duo_skey = $config->two_factor_auth_duo_skey;
        $two_factor_auth_duo_hostname = $config->two_factor_auth_duo_hostname;
        $instance = new self(
            $two_factor_auth_duo_ikey,
            $two_factor_auth_duo_skey,
            $two_factor_auth_duo_hostname
        );
        $validRedirectUrl = $instance->getValidRedirectUrl($redirectUrl);
        $client = $instance->getClient();
        $state = DuoStore::makeUniqueState($client);
        $store = new DuoStore($state, $username, $validRedirectUrl);
        $store->save();
        return $state;
    }

    /**
     * restore the state from the cache
     *
     * @return DuoStore
     * @throws ErrorException
     */
    function restoreState($state) {
        /** @var DuoStore $store */
        $store = DuoStore::fromState($state);
        if($store instanceof DuoStore) {
            return $store;
        }
        throw new ErrorException("Cannot restore state", 1);
    }

    /**
     * get a valid redirect URL to use after a successful 2FA process.
     * make sure we do not redirect one of the pages used for the suthentication
     * process
     *
     * @param string $redirectUrl
     * @return string
     */
    function getValidRedirectUrl($redirectUrl) {
        $authProcessEndpoints = [ self::TWO_FA_CALLBACK_ENDPOINT, self::TWO_FA_LAUNCH_ENDPOINT ];
        $currentPage = $_SERVER['REQUEST_URI']; // only path, does not include the domain name or protocol

        foreach ($authProcessEndpoints as $endpoint) {
            $normalized = addcslashes($endpoint, '/\\');
            $match = preg_match("/$normalized$/i", $currentPage);
            if($match===1) return APP_PATH_WEBROOT;
        }
        return $redirectUrl;
    }

    /**
     * on some devices (e.g.: iOS) the session is not kept during the
     * 2FA process.
     * The twoFA index page receives the encrypted and URL encoded session_id
     * The session is retrieved from the database using the session ID
     * 
     * @param string $session_id the encrypted session_id
     * @return string
     */
    public function retrieveSession($session_id) {
        $id = decrypt( urldecode($session_id) ) ?? ''; // restore the encrypted session_id
        if($id===false) throw new Exception("Error: could not decrypt the session ID", 1);
        $session = \Session::read($id);
        // remove the first part of the stored session before unserializing:
        $sessionData = preg_replace('/^_authsession\|/', '', $session);
        $session = @unserialize($sessionData, ['allowed_classes'=>false]);
        if($session===false) throw new Exception("Error: could not rebuild the session", 1);
        return $session;
    }

    /**
     * start the Duo authentication process
     *
     * @param string $state
     * @return void
     */
    public function launchUniversalPrompt($state, $rememberMe=false)
    {
        $store = DuoStore::fromState($state);
        $store->setRememberMe($rememberMe);
        $store->save(); // save after setting the rememberMe value
        $redirectUrl = $store->launchPage();
        $username = $store->username();
        $redirectUrl = $this->getValidRedirectUrl($redirectUrl);
        $this->renderer->share('redirectUrl', $redirectUrl); // add the redirect URL to the blade variables
        $this->healthCheck($username, $state);
        $this->redirectToDuo($username, $state);
    }

    public function authenticate($state, $code) {
        # Retrieve the previously stored state and username
        $store = $this->restoreState($state);
        $saved_state = $store->state();
        $username = $store->username();
        $launchPage = $store->launchPage();
        if (empty($store) || empty($username)) {
            # If the URL used to get to login.php is not localhost, (e.g. 127.0.0.1), then the sessions will be different
            # and the localhost session will not have the state.
            $args["message"] = "No saved state please login again";
            $args["launchPage"] = $store->launchPage();
            $html = $this->renderer->run('login', compact('args'));
            return print($html);
        }

        # Ensure nonce matches from initial request
        if ($state != $saved_state) {
            $args["message"] = "Duo state does not match saved state";
            $args["launchPage"] = $store->launchPage();
            $html = $this->renderer->run('login', compact('args'));
            return print($html);
        }

        try {
            $decoded_token = $this->client->exchangeAuthorizationCodeFor2FAResult($code, $username);
            $matchingUsername = $duoUsername = $decoded_token['auth_context']['user']['name'] ?? '';
            $duoAlias = $decoded_token['auth_context']['alias'] ?? '';

            if(trim($duoAlias)!='') $matchingUsername = $duoAlias;

            $validUser = $this->checkUsername($username, $matchingUsername);
            
            // throw new DuoException("Error Processing Request", 1);
            
            if($validUser===true) {
                // Perform auto-login
                require_once APP_PATH_DOCROOT . 'Libraries/PEAR/Auth.php';
                \Authentication::autoLogin($username);
                defined("USERID") or define("USERID", strtolower($username)); // set the userid constant (used in twoFactorLoginSuccess for logging)
                \Authentication::twoFactorLoginSuccess(self::TWO_FACTOR_METHOD_NAME);
                $two_factor_auth_trust = $store->rememberMe() ? '1' : '0';
                \Authentication::twoFactorSetTrustCookie($two_factor_auth_trust);
                header("Location: {$launchPage}", TRUE, 302); // go back to the page where the 2FA process was started
            }else {
                $errorMessage = "Error: the current username does not match the one sent to Duo. Duo username: '$duoUsername' - REDCap username: '$username'";
                \Logging::logEvent('',"","ERROR",'',$display="",$description=$errorMessage);
                $args["message"] = $errorMessage;
                $args["launchPage"] = $store->launchPage();
                $html = $this->renderer->run('login', compact('args'));
                return print($html);
            }
        } catch (DuoException $e) {
            \Logging::logEvent('',"","ERROR",'',$display="",$description=$e->getMessage());
            $args["message"] = "Error decoding Duo result. ($description)";
            $args["launchPage"] = $store->launchPage();
            $args["callbackUri"] = self::getCallbackUri();
            $args["state"] = $state = $store->state();
            $args["username"] = $username = $store->username();
            $args["prompt_uri"] = $this->client->createAuthUrl($username, $state);
            $html = $this->renderer->run('login', compact('args'));
            return print($html);
        }

        # Exchange happened successfully so render success page
        $args["message"] = json_encode($decoded_token, JSON_PRETTY_PRINT);
        $html = $this->renderer->run('success', compact('args'));
        return print($html);
    }

    /**
     * sequantially apply matchers to check if the
     * REDCap username matches the one registered in DUO
     *
     * @param string $redcapUsername
     * @param string $duoUsername
     * @return bool
     */
    public function checkUsername($redcapUsername, $duoUsername) {
        $caseInsensitive = new CaseInsensitive();
        $ignoreDots = new IgnoreDots();
        $replaceDotsWithSpaces = new ReplaceDotsWithSpaces();
        $Domain = new Domain();
        $email = new Email();

        // set chain of responsability for checking username
        $caseInsensitive
            // ->setNext($ignoreDots)
            // ->setNext($replaceDotsWithSpaces)
            ->setNext($Domain)
            ->setNext($email)
            ;

        $matcher = $caseInsensitive; // set the first matcher
        
        return $matcher->match($redcapUsername, $duoUsername);
    }

}