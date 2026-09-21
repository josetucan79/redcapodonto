<?php
namespace Vanderbilt\REDCap\Classes\Utility\Mediator;



/**
 * The Observer interface defines how components receive the event
 * notifications.
 */
interface Observer
{
    public function update(object $emitter, string $event, $data = null);
}