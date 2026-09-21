<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\PersistenceStrategies;

use Session;

class SessionStrategy implements PersistenceStrategyInterface {
    public function __construct() {
    }


    private function init() {
        if (session_status() != PHP_SESSION_ACTIVE) session_start();
    }
    /**
     * Retrieve an item
     *
     * @param string $identifier
     * @return mixed
     */
    public function get($identifier) {
        $this->init();
        return Session::read($identifier);
    }

    /**
     * Destroy an item
     *
     * @param string $identifier
     * @return void
     */
    public function destroy($identifier) {
        $this->init();
        Session::destroy($identifier);
    }

    /**
     * Save an item
     *
     * @param string $identifier
     * @param mixed $data
     * @return Boolean
     */
    public function save($identifier, $data) {
        $this->init();
        Session::write($identifier, $data);
    }

}