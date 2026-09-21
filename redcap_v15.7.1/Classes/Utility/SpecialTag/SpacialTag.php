<?php
namespace Vanderbilt\REDCap\Classes\Utility\SpecialTag;
/**
 * Class SpecialTag
 *
 * Represents a single parsed special tag.
 */
class SpecialTag {
    /** @var string|null */
    private $event;
    /** @var string|null */
    private $command;
    /** @var array */
    private $params;
    /** @var string|null */
    private $instance;

    /**
     * SpecialTag constructor.
     *
     * @param string|null $event
     * @param string|null $command
     * @param array       $params  An array of parameters (param1, param2, param3)
     * @param string|null $instance
     */
    public function __construct($event, $command, array $params, $instance) {
        $this->event = $event;
        $this->command = $command;
        $this->params = $params;
        $this->instance = $instance;
    }

    /**
     * Get the event part.
     *
     * @return string|null
     */
    public function getEvent() {
        return $this->event;
    }

    /**
     * Get the command part.
     *
     * @return string|null
     */
    public function getCommand() {
        return $this->command;
    }

    /**
     * Get all parameters as an array.
     *
     * @return array
     */
    public function getParams() {
        return $this->params;
    }

    /**
     * Get a specific parameter by index (0-based).
     *
     * @param int $index
     * @return string|null
     */
    public function getParam($index) {
        return isset($this->params[$index]) ? $this->params[$index] : null;
    }

    /**
     * Get the instance specifier.
     *
     * @return string|null
     */
    public function getInstance() {
        return $this->instance;
    }
}