<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow;

use Exception;

class OrderStateMachine {
    protected $currentState;
    protected $transitions = [];

    public function __construct($initialState, $transitions = []) {
        $this->currentState = $initialState;
        $this->transitions = $transitions;
    }


    /**
     * Register a custom handler for a given state and action.
     *
     * This method overrides the existing transition for the provided state and action.
     *
     * @param string   $state   The current state to which the handler applies.
     * @param string   $action  The action for which the handler should be registered.
     * @param callable $handler A callable that accepts the current state and returns the new state.
     *
     * @throws Exception If there is no existing transition for the provided state and action.
     */
    public function registerHandler(string $state, string $action, callable $handler): void {
        if (!isset($this->transitions[$state][$action])) {
            throw new Exception("No transition defined for state {$state} and action {$action}");
        }
        $this->transitions[$state][$action] = $handler;
    }

    public function can($action) {
        return isset($this->transitions[$this->currentState][$action]);
    }

    public function apply($action, ...$args) {
        if (!$this->can($action)) {
            throw new Exception("Invalid transition from {$this->currentState} using action {$action}");
        }
        
        $transition = $this->transitions[$this->currentState][$action];
        if (is_callable($transition)) {
            $this->currentState = call_user_func_array($transition, [$this->currentState, ...$args]);
        } else {
            $this->currentState = $transition;
        }
    }

    public function getCurrentState() {
        return $this->currentState;
    }
}
