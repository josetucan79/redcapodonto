import EventBus from './EventBus.js';

// Map to hold EventBus instances
const eventBusMap = new Map();

/**
 * Returns an EventBus instance associated with a specific key.
 * If the EventBus for the key doesn't exist, it will be lazily instantiated.
 * If the key is not a string (e.g., null), a new EventBus is returned directly.
 *
 * @param {string} [key] - An optional key to retrieve a specific EventBus instance.
 * @returns {EventBus} The EventBus instance for the given key or a new instance if the key is invalid.
 */
const useEventBus = (key) => {
  // If key is not a string, return a new EventBus instance
  if (typeof key !== 'string') {
    return new EventBus();
  }

  // Use the provided key to retrieve or create an EventBus instance
  if (!eventBusMap.has(key)) {
    eventBusMap.set(key, new EventBus());
  }

  return eventBusMap.get(key);
};

export { useEventBus, EventBus };
