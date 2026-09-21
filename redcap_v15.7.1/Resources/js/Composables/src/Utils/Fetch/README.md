### Documentation for `useFetch`

The `useFetch` function, implemented using the `FetchClient` class, provides a simple, flexible, and consistent way to interact with APIs. It supports dynamic URL building, customizable request configurations, and includes a built-in method for seamlessly adding the REDCap CSRF token when interacting with internal APIs.

---

### `useFetch` Function

The `useFetch` function simplifies creating instances of the `FetchClient` class.

```javascript
const useFetch = (baseURL) => {
  return new FetchClient(baseURL);
};
export { useFetch };
```

#### Parameters
- **`baseURL`** (string): The base URL to use for all API requests.

#### Returns
- An instance of the `FetchClient` class, pre-configured with the specified `baseURL`.

---

### `FetchClient` Class

The `FetchClient` class serves as the core utility for making HTTP requests. It supports common HTTP methods, dynamic URL building, and advanced configurations like custom headers, query parameters, and request cancellation.

---

#### Constructor

**`new FetchClient(baseURL)`**  
Creates a new instance of the `FetchClient` class.

- **Parameters**:
  - `baseURL` (string): The base URL for the API.

---

#### Features

1. **Customizable Default Headers**
   - Default headers include:
     - `'Content-Type': 'application/json'`
     - `'X-Requested-With': 'XMLHttpRequest'`
   - Easily modify headers using the `setHeader` method.

2. **CSRF Token Support**
   - Includes the `addCsrfToken` method to automatically add the REDCap CSRF token (`X-Csrf-Token`) when making calls to internal APIs.
   - Logs a warning if the token is unavailable.

3. **Dynamic URL Building**
   - The `buildURL` method appends query parameters (`params`) to the base URL dynamically, making it easy to customize API calls.

4. **Supports All HTTP Verbs**
   - Provides built-in methods for `GET`, `POST`, `PUT`, and `DELETE` requests.

5. **Request Cancellation**
   - Accepts an `AbortController` in the configuration for request cancellation.

6. **Error Handling**
   - Automatically throws errors for non-OK responses, ensuring you can handle errors in your application logic.

---

#### Methods

##### General Request Method
**`request(route, method, data, config)`**  
Executes an HTTP request.

- **Parameters**:
  - `route` (string): The API route (e.g., `/users`).
  - `method` (string): HTTP method (e.g., `GET`, `POST`).
  - `data` (object, optional): Request payload for methods like `POST` and `PUT`.
  - `config` (object, optional): Additional configuration, including:
    - `headers` (object): Custom headers.
    - `params` (object): Query parameters to override defaults.
    - `controller` (AbortController): For request cancellation.

- **Returns**:  
  A `Promise` resolving to the `Response` object.

---

##### Helper Methods for HTTP Verbs
- **`get(route, config)`**  
  Executes a `GET` request.
  ```javascript
  fetchClient.get("/users");
  ```

- **`post(route, data, config)`**  
  Executes a `POST` request.
  ```javascript
  fetchClient.post("/users", { name: "John Doe" });
  ```

- **`put(route, data, config)`**  
  Executes a `PUT` request.
  ```javascript
  fetchClient.put("/users/1", { name: "Jane Doe" });
  ```

- **`delete(route, data, config)`**  
  Executes a `DELETE` request.
  ```javascript
  fetchClient.delete("/users/1");
  ```

---

##### CSRF Token Management
**`addCsrfToken()`**  
Adds the REDCap CSRF token (`X-Csrf-Token`) to the default headers if available.

- **Usage**:
  ```javascript
  fetchClient.addCsrfToken();
  ```

---

#### Example Usage

```javascript
import { useFetch } from "./FetchClient";

// Create a new FetchClient instance
const fetchClient = useFetch("https://api.example.com");

// Add CSRF token for internal API calls
fetchClient.addCsrfToken();

// Perform a GET request
fetchClient
  .get("/users", { params: { role: "admin" } })
  .then((response) => response.json())
  .then((data) => console.log(data))
  .catch((error) => console.error(error));

// Perform a POST request
fetchClient
  .post("/users", { name: "John Doe", email: "john@example.com" })
  .then((response) => response.json())
  .then((data) => console.log("User created:", data))
  .catch((error) => console.error("Error creating user:", error));

// Perform a PUT request
fetchClient
  .put("/users/1", { name: "Jane Doe" })
  .then((response) => console.log("User updated"))
  .catch((error) => console.error(error));

// Perform a DELETE request
fetchClient
  .delete("/users/1")
  .then((response) => console.log("User deleted"))
  .catch((error) => console.error(error));
```