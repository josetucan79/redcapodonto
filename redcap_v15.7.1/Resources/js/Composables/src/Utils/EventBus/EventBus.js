export default class EventBus extends EventTarget {
    /**
    * Dispatches a custom event with the given event name, data, and sender.
    * The event includes a timestamp and an optional sender to provide context.
    *
    * @param {string} eventName - The name of the event to dispatch.
    * @param {any} [data=null] - Additional data to include in the event.
    * @param {Object} [sender=null] - The sender of the event (optional).
    */
    notify(eventName, data = null, sender = null) {
        const customEvent = new CustomEvent(eventName, {
            detail: {
                data,
                sender,
                timestamp: Date.now(),
            },
        });
        this.dispatchEvent(customEvent);
    }
}