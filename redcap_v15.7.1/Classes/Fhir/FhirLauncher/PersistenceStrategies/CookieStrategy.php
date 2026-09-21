<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\PersistenceStrategies;

class CookieStrategy implements PersistenceStrategyInterface {

    private $lifespan;
    private $path;
    private $domain;
    private $secure;
    private $httponly;

    public function __construct($lifespan=0, $path = "/", $domain = '', $secure = false, $httponly = true) {
        $this->lifespan = $lifespan;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httponly = $httponly;
    }

    /**
	 * make a cookie friendly duration based 
	 * on the provided seconds
	 *
	 * @param integer $lifespan in seconds
	 * @return void
	 */
	private static function makeExpiration($lifespan) {
		$lifespan = intval($lifespan);
		$expiration = ($lifespan==0) ? 0 : time()+$lifespan;
		return $expiration;
	}

    private static function set($name, $data, $expiration=0, $path = "/", $domain = '', $secure = false, $httponly = true) {
        $success = setcookie(
			$name,
			$data,
			$expiration,
			$path,
			$domain,
			$secure,
			$httponly
		);
		return $success;
    }

    /**
     * Retrieve an item
     *
     * @param string $identifier
     * @return mixed
     */
    public function get($identifier) {
        $data = $_COOKIE[$identifier] ?? '';
        return $data;
    }

    /**
     * Destroy an item
     *
     * @param string $identifier
     * @return void
     */
    public function destroy($identifier) {
        // set negative expiration so it is deleted on next reload
		self::set($identifier, '', -3600);
		unset($_COOKIE[$identifier]);
    }

    /**
     * Save an item
     *
     * @param string $identifier
     * @param mixed $data
     * @return Boolean
     */
    public function save($identifier, $data) {
        $success = self::set(
            $identifier,
			$data,
			$expiration = self::makeExpiration($this->lifespan),
			$this->path,
			$this->domain,
			$this->secure,
			$this->httponly
        );
        if($success) $_COOKIE[$identifier] = $data;
        return $success;
    }


}