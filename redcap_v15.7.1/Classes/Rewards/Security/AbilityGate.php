<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Security;

class AbilityGate
{
    protected $abilities = [];
    protected $beforeCallbacks = [];
    protected $afterCallbacks = [];

    // Define a new ability
    public function define($ability, callable $callback)
    {
        $this->abilities[$ability] = $callback;
    }

    // Register a before callback
    public function before(callable $callback)
    {
        $this->beforeCallbacks[] = $callback;
    }

    // Register an after callback
    public function after(callable $callback)
    {
        $this->afterCallbacks[] = $callback;
    }

    // Check if a given ability is allowed for the user
    public function allows($ability, ...$arguments)
    {
        // Run before callbacks
        $result = $this->callBeforeCallbacks($ability, ...$arguments);
        if (!is_null($result)) {
            return $result;
        }

        // Check ability
        $result = $this->checkAbility($ability, ...$arguments);

        // Run after callbacks
        $finalResult = $this->callAfterCallbacks($ability, $result, ...$arguments);
        if (!is_null($finalResult)) {
            return $finalResult;
        }

        return $result;
    }

    // Check if a given ability is denied for the user
    public function denies($ability, ...$arguments)
    {
        return !$this->allows($ability, ...$arguments);
    }

    // Determine if the given ability has been defined
    public function has($ability)
    {
        return isset($this->abilities[$ability]);
    }

    // Call before callbacks
    protected function callBeforeCallbacks($ability, ...$arguments)
    {
        foreach ($this->beforeCallbacks as $callback) {
            $result = $callback($ability, ...$arguments);
            if (!is_null($result)) {
                return $result;
            }
        }
        return null;
    }

    // Call after callbacks
    protected function callAfterCallbacks($ability, $result, ...$arguments)
    {
        foreach ($this->afterCallbacks as $callback) {
            $afterResult = $callback($ability, $result, ...$arguments);
            if (!is_null($afterResult)) {
                return $afterResult;
            }
        }
        return null;
    }

    // Check ability
    protected function checkAbility($ability, ...$arguments)
    {
        if (isset($this->abilities[$ability])) {
            return call_user_func($this->abilities[$ability], $ability, ...$arguments);
        }

        return false;
    }
}
