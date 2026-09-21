<?php
namespace Vanderbilt\REDCap\Classes\Utility\Mediator;

class EventDispatcher {
    /**
     * @var Observer[]
     */
    private $observers = [];
    
    /**
     * @var string
     */
    const ANY = '*';

    public function __construct()
    {
        // The special event group for observers that want to listen to all
        // events.
        $this->observers[self::ANY] = [];
    }

    /**
     *
     * @param string $event
     * @return void
     */
    private function initEventGroup(&$event = self::ANY)
    {
        if (!isset($this->observers[$event])) {
            $this->observers[$event] = [];
        }
    }

    /**
     *
     * @param string $event
     * @return Observer[]
     */
    private function getEventObservers($event = self::ANY)
    {
        $this->initEventGroup($event);
        $group = $this->observers[$event];
        $all = $this->observers[self::ANY];

        return array_merge($group, $all);
    }

    /**
     *
     * @param Observer $observer
     * @param string $event
     * @return void
     */
    public function attach($observer, $event = self::ANY)
    {
        $this->initEventGroup($event);

        $this->observers[$event][] = $observer;
    }

    /**
     *
     * @param Observer $observer
     * @param string $event
     * @return void
     */
    public function detach($observer, $event = self::ANY)
    {
        foreach ($this->getEventObservers($event) as $key => $s) {
            if ($s === $observer) {
                unset($this->observers[$event][$key]);
            }
        }
    }

    /**
     *
     * @param string $event
     * @param object $emitter
     * @param mixed $data
     * @return void
     */
    public function notify($emitter, $event, $data = null)
    {
        foreach ($this->getEventObservers($event) as $observer) {
            $observer->update($emitter, $event, $data);
        }
    }
}
