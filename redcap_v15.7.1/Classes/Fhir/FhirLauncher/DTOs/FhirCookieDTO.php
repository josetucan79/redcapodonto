<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

/**
 * HTTP only cookie used to collect information
 * about the FHIR authenication process
 * 
 * It is created during before the authentication phase (both EHR and standalone launch).
 * It is deleted in NoState and ErrorState
 */
class FhirCookieDTO extends DTO {

	/**
	 * name
	 *
	 * @var string
	 */
	public $name;

	/**
	 * data stored in the cookie
	 * must be used as a string->string dictionary
	 *
	 * @var string
	 */
	public $launchType = '';

	/**
	 * state (identifier) of the EHR session
	 *
	 * @var string
	 */
	public $state = '';

	/**
	 * static creator
	 *
	 * @param string $name
	 * @return FhirCookie
	 */
	public static function make($name)
	{
		$instance = new self();
		$instance->name = $name;
		return $instance;
	}

	/**
	 * save a cookie
	 *
	 * @param integer $lifespan
	 * @param string $path
	 * @param string $domain
	 * @param boolean $secure
	 * @param boolean $httponly
	 * @return FhirCookie
	 */
	public function save($lifespan=0, $path = "/", $domain = '', $secure = false, $httponly = true) {
		$success = self::set(
			$name = $this->name,
			$data = serialize($this),
			...func_get_args()
		);
		if($success) {
			// make it immediately available
			$_COOKIE[$name] = $data;
			// also add to session if available
			if (session_status() != PHP_SESSION_ACTIVE) session_start();
			$_SESSION[$name] = $this;
		}
		return $this;
	}

	/**
	 * make a FhirCookie using the data available
	 * in the COOKIE superglobal
	 *
	 * @param string $name
	 * @return FhirCookie
	 */
	public static function fromName($name) {
		$serialized = $_COOKIE[$name] ?? ''; // try cookie first
		if($serialized=='') $data = $_SESSION[$name] ?? [];
        else $data = @unserialize($serialized, ['allowed_classes'=>[static::class]]) ?? [];
		if($data instanceof self) return $data;
		if(!is_array($data)) $data = [];
		$instance = new self($data);
		return $instance;
	}

	/**
	 * make a cookie friendly duration based 
	 * on the provided seconds
	 *
	 * @param integer $lifespan in seconds
	 * @return void
	 */
	public static function makeExpiration($lifespan) {
		$lifespan = intval($lifespan);
		$expiration = ($lifespan==0) ? 0 : time()+$lifespan;
		return $expiration;
	}

	
	public static function destroy($name) {
		self::set($name, '', -3600);
		unset($_COOKIE[$name]);
	}

	/**
	 * set a cookie
	 *
	 * @param string $name
	 * @param mixed $data
	 * @param integer $lifespan 0 = destroy when browser session is closed
	 * @param string $path
	 * @param string $domain
	 * @param boolean $secure
	 * @param boolean $httponly
	 * @return boolean
	 */
	public static function set($name, $data, $lifespan=0, $path = "/", $domain = '', $secure = false, $httponly = true): bool
	{
		$success = setcookie(
			$name,
			$data,
			$expiration = self::makeExpiration($lifespan),
			$path,
			$domain,
			$secure,
			$httponly
		);
		return $success;
	}
}