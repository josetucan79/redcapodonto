### Documentation for `useEventBus`

The `useEventBus` function provides a centralized and efficient way to manage and dispatch custom events across different parts of your application. It supports creating unique or shared event buses using keys, ensuring flexible and reusable event handling.

---

### `useEventBus` Function

The `useEventBus` function simplifies the process of creating or retrieving `EventBus` instances for event-driven communication within your application.

```javascript
const useEventBus = (key) => {
  if (typeof key !== 'string') {
    return new EventBus();
  }

  if (!eventBusMap.has(key)) {
    eventBusMap.set(key, new EventBus());
  }

  return eventBusMap.get(key);
};
export { useEventBus };
```

#### Parameters
- **`key`** (optional):  
  - A `string` that acts as a unique identifier for a specific `EventBus` instance.
  - If not provided or if the key is not a string, a new independent `EventBus` instance is created.

#### Returns
- An instance of the `EventBus` class:
  - If a key is provided and matches an existing instance, the same `EventBus` instance is returned.
  - If the key does not match an existing instance, a new `EventBus` instance is created and associated with the key.

---

### `EventBus` Class

The `EventBus` class extends the native `EventTarget` interface, providing a robust and flexible mechanism for event-based communication.

---

#### Features

1. **Custom Event Dispatching**
   - Dispatch custom events with detailed data and metadata (e.g., sender, timestamp).

2. **Shared and Unique Event Buses**
   - Use keys to create shared `EventBus` instances or directly create independent instances for localized communication.

3. **Native EventTarget Methods**
   - Supports `addEventListener`, `removeEventListener`, and `dispatchEvent`.

4. **Timestamp and Sender Context**
   - Events include a `timestamp` and optional `sender` to add context to the dispatched event.

---

#### Methods

##### **`notify(eventName, data = null, sender = null)`**

Dispatches a custom event with the given name, data, and optional sender.

- **Parameters**:
  - `eventName` (string): The name of the event to dispatch.
  - `data` (any, optional): Additional data to include in the event. Default is `null`.
  - `sender` (Object, optional): The sender of the event (for context). Default is `null`.

- **Example**:
  ```javascript
  const eventBus = useEventBus();
  eventBus.notify("userLoggedIn", { userId: 123 }, { component: "LoginForm" });
  ```

- **Event Structure**:
  The dispatched event contains a `detail` object with the following properties:
  - `data`: The event data.
  - `sender`: The event sender.
  - `timestamp`: A timestamp indicating when the event was dispatched.

---

### Example Usage

#### Creating and Using a Shared EventBus
```javascript
import { useEventBus } from "./EventBus";

// Create or retrieve a shared EventBus instance
const globalEventBus = useEventBus("global");

// Listen for an event
globalEventBus.addEventListener("appLoaded", (event) => {
  console.log("App loaded at:", event.detail.timestamp);
});

// Dispatch an event
globalEventBus.notify("appLoaded", { message: "Welcome!" });
```

---

#### Creating an Independent EventBus
```javascript
import { useEventBus } from "./EventBus";

// Create a unique EventBus instance
const localEventBus = useEventBus();

// Listen for an event
localEventBus.addEventListener("taskCompleted", (event) => {
  console.log("Task completed with data:", event.detail.data);
});

// Dispatch an event
localEventBus.notify("taskCompleted", { taskId: 42 });
```

---

#### Using the Sender Context
```javascript
const eventBus = useEventBus("shared");

eventBus.addEventListener("actionTriggered", (event) => {
  console.log("Action triggered by:", event.detail.sender);
});

eventBus.notify("actionTriggered", { action: "save" }, { component: "Editor" });
```

---

### Why `useEventBus`?

1. **Centralized Event Management**  
   - Use keys to manage event buses across different components or modules, ensuring consistency and avoiding duplication.

2. **Flexibility**  
   - Create independent or shared `EventBus` instances depending on your use case.

3. **Context-Rich Events**  
   - Dispatch events with detailed data, sender information, and timestamps, making event handling more informative.

4. **Lightweight and Native**  
   - Built on top of the native `EventTarget` API, ensuring lightweight and efficient performance.

---

### Key Advantages

- **Shared Communication**: Easily share a single `EventBus` instance using keys for cross-component or global event handling.
- **Local Communication**: Create independent `EventBus` instances for isolated or localized use cases.
- **Custom Metadata**: Add meaningful context to events with timestamps and sender details.