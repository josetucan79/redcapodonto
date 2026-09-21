<?php
namespace Vanderbilt\REDCap\Classes\Utility\Mediator;



/**
 * The Observer interface defines how components receive the event
 * notifications.
 */
interface ObserverInterface
{
    public function update(object $emitter, string $event, $data = null);
}