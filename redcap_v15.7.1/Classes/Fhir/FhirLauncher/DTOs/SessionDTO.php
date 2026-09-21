<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States\State;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\PatientDataDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\AccessTokenResponseDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\PersistenceStrategies\PersistenceStrategyInterface;

/**
 * Parameters sent using HTTP POST to the token endpoint
 */
final class SessionDTO extends DTO {

    const PHP_SESSION_KEY = 'fhir_session_data';

    /**
     *
     * @var string
     */
    public $state;

	/**
     *
     * @var string
     */
    public $user;

	/**
     *
     * @var string
     */
    public $fhirUser;

	/**
     *
     * @var array
     */
    public $fhirUsers = null;

    /**
     *
     * @var AccessTokenResponseDTO
     */
	public $accessToken;

	/**
     *
     * @var string
     */
    public $launchPage;

	/**
     * @var string
     */
    public $launchType;

    /**
     *
     * @var PatientDataDTO
     */
    public $patientData;

    /**
     * ID of the EHR system being used
     * @var int
     */
    public $ehrID;

    /**
     *
     * @var array
     */
    public $logs = [];

    /**
     *
     * @var array
     */
    public $previousStates = [];

    /**
     * @var array
     */
    public $warnings = [];

    /**
     * persist when destroyed
     */
    public function __destruct() { }
    
    /**
     * @param PersistenceStrategyInterface $persistenceStrategy
     * @return void
     */
    public function save(PersistenceStrategyInterface $persistenceStrategy) {
        $serialized = serialize($this);
        $data = encrypt($serialized);
		$persistenceStrategy->save($this->state, $data);
    }

    /**
     * remove the session from the database and from the
     * PHP session
     *
     * @param PersistenceStrategyInterface $persistenceStrategy
     * @return void
     */
    public function destroy(PersistenceStrategyInterface $persistenceStrategy) {
        $persistenceStrategy->destroy($this->state);
    }

    /**
     * register a previous state
     *
     * @param State $state
     * @return void
     */
    public function addPreviousState($state) {
        if(!$state) return;
        $previousState = end($this->previousStates);
        // do not add state if matching the previous one (reloads)
        if($previousState===$state) return;
        $this->previousStates[] = get_class($state);
    }

    /**
     * @param string $state
     * @param PersistenceStrategyInterface $persistenceStrategy
     * 
     * @return SessionDTO|false
     * @return void
     */
    public static function fromState($state, $persistenceStrategy) {
        $data = $persistenceStrategy->get($state);
        if(empty($data) || is_null($data)) return false;
        /** @var SessionDTO $dto */
        $decrypted = decrypt($data);
        $dto = @unserialize($decrypted, ['allowed_classes'=>[
                SessionDTO::class,
                AccessTokenResponseDTO::class,
                PatientDataDTO::class
            ]
        ]);
		return $dto;
    }

    /**
     * create a session ID for the custom session.
     * close the current session before a new session ID can be created
     *
     * @return string
     */
    public static function makeState() {
        if (session_status() != PHP_SESSION_ACTIVE) session_start();
        // close the current session
        session_write_close();
        // generate a new session ID
        $ID = session_create_id();
        return $ID;
    }

    public function log($message) { $this->logs[] = $message; }
    public function getLogs() { return $this->logs; }

    /**
     * Adds a warning message to the session.
     *
     * @param string $message
     * @return void
     */
    public function addWarning($message) { $this->warnings[] = $message; }

    /**
     * Retrieves all warning messages.
     *
     * @return array
     */
    public function getWarnings() { return $this->warnings; }
}