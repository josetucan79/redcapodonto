<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo;


use Session;
use Exception;
use Duo\DuoUniversal\Client;

final class DuoStore {

    const CACHE_NAMESPACE_PREFIX = 'DUO_STATE';
    const CACHE_DATA_KEY = 'data';

    /**
     * state generate by Duo
     *
     * @var string
     */
    private $state;

    /**
     * username in the launch context
     *
     * @var string
     */
    private $username;

    /**
     * page that originated the 2FA process
     *
     * @var string
     */
    private $launchPage;

    /**
     * remember user based on REDCap settings
     *
     * @var boolean
     */
    private $rememberMe;

    public function __construct($state, $username, $launchPage, $rememberMe=false) {
        $this->state = $state;
        $this->username = $username;
        $this->launchPage = $launchPage;
        $this->rememberMe = $rememberMe;
    }

    /**
     * maximum number of tries allowed to get a unique state
     */
    const MAX_UNIQUE_STATE_GENERATION_ATTEMPTS = 100;

    /**
     * generate a unique state making sure that there is not an active
     * session with the same ID
     * 
     * @param Client $duoClient
     * @return string
     * @throws Exception if fails to generate a unique state after MAX_UNIQUE_STATE_GENERATION_ATTEMPTS
     */
    public static function makeUniqueState($client, $attemptNumber=0) {
        if($attemptNumber>self::MAX_UNIQUE_STATE_GENERATION_ATTEMPTS)
            throw new Exception("Could not generate a unique state for the custom session after $attemptNumber attempts.", 1);
        $state = $client->generateState();
        $existingSession = Session::read($state) == true;
        if(!$existingSession) return $state;
        return self::makeUniqueState($client, ++$attemptNumber);
    }

    public function state() { return $this->state; }
    public function username() { return $this->username; }
    public function launchPage() { return $this->launchPage; }
    public function rememberMe() { return $this->rememberMe; }
    public function setRememberMe($value) { $this->rememberMe = boolval($value); }

    public function __serialize(): array {
        return [
            'state' => $this->state,
            'username' => $this->username,
            'launchPage' => $this->launchPage,
            'rememberMe' => $this->rememberMe,
        ];
    }

    public function __unserialize(array $data): void {
        $this->state = $data['state'];
        $this->username = $data['username'];
        $this->launchPage = $data['launchPage'];
        $this->rememberMe = $data['rememberMe'];
    }

    /**
     * persist the store in a cache file
     *
     * @return void
     */
    public function save() {
        $value = encrypt(serialize($this));
        $state = $this->state();
        Session::write($state, $value);
        /* $namespace = self::CACHE_NAMESPACE_PREFIX.$state;
        $cache = new FileCache($namespace);
        $cache->set(self::CACHE_DATA_KEY, $value); */
    }

    /**
     * create a store from a cached file
     *
     * @param string $state
     * @return DuoStore|false
     */
    public static function fromState($state) {
        $data = Session::read($state);
        if(is_null($data)) return false;
        /** @var DuoStore $store */
        $store = @unserialize(decrypt($data), ['allowed_classes'=>[static::class]]);
        return $store;
    }

}