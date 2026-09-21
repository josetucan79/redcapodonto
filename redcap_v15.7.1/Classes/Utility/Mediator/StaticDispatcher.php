<?php
namespace Vanderbilt\REDCap\Classes\Utility\Mediator;


/**
 * provide global access to the event dispatcher
 */
class StaticDispatcher {
    /**
     * singleton
     *
     * @return EventDispatcher
     */
    public static function getInstance(): EventDispatcher
    {
        static $eventDispatcher;
        if (!$eventDispatcher) {
            $eventDispatcher = new EventDispatcher();
        }

        return $eventDispatcher;
    }

    /**
     *
     * @param Observer $observer
     * @param string $event
     * @return void
     */
    public static function attach($observer, $event = EventDispatcher::ANY)
    {
        self::getInstance()->attach(...func_get_args());
    }

    /**
     *
     * @param Observer $observer
     * @param string $event
     * @return void
     */
    public static function detach($observer, $event = EventDispatcher::ANY)
    {
        self::getInstance()->detach(...func_get_args());
    }

    /**
     *
     * @param string $event
     * @param object $emitter
     * @param mixed $data
     * @return void
     */
    public static function notify($emitter, $event, $data = null)
    {
        self::getInstance()->notify(...func_get_args());
    }


}
