### Documentation for `useToaster`

The `useToaster` function provides an intuitive and consistent way to create and manage toasts (notifications) within your application. It leverages the `ToastManager` class to handle toast creation, display, and removal, while supporting various configurations like types, positions, and auto-close behavior.

---

### `useToaster` Function

The `useToaster` function simplifies creating an instance of the `ToastManager` class for displaying notifications.

```javascript
const useToaster = () => {
  return new ToastManager();
};
export { useToaster };
```

#### Returns
- An instance of the `ToastManager` class.

---

### `ToastManager` Class

The `ToastManager` class provides methods to create and manage toasts with customizable behavior, styling, and position.

---

#### Features

1. **Toast Types**
   - Supports predefined types:  
     `"primary"`, `"secondary"`, `"success"`, `"danger"`, `"warning"`, `"info"`, `"light"`, `"dark"`.  
   - Each type can be used directly as a method on the `ToastManager` instance for convenience (e.g., `toaster.success`).

2. **Flexible Positioning**
   - Toasts can be displayed in any of the following positions:
     - `top-left`, `top-center`, `top-right`
     - `middle-left`, `middle-center`, `middle-right`
     - `bottom-left`, `bottom-center`, `bottom-right`
   - Default position is `top-right`.

3. **Auto-Close Behavior**
   - Toasts automatically disappear after a specified duration (`autoClose` in milliseconds).
   - Default auto-close duration: `5000`ms (5 seconds).
   - Auto-close can be paused when the user hovers over the toast.

4. **Customizable Headers**
   - Optionally include a `title` in the toast.

5. **Manual Dismissal**
   - Users can close toasts manually using a close button.

---

#### Methods

##### **`toast(message, options)`**
Displays a toast with a customizable message and configuration.

- **Parameters**:
  - `message` (string): The content of the toast.
  - `options` (object, optional): Configuration options:
    - `title` (string): Optional title for the toast.
    - `type` (string): Toast type (`primary`, `success`, etc.).
    - `position` (string): Toast position (e.g., `top-right`).
    - `autoClose` (number): Duration (in ms) before auto-close. Set to `0` to disable.

- **Example**:
  ```javascript
  toaster.toast("Hello, World!", {
    title: "Welcome",
    type: "success",
    position: "top-center",
    autoClose: 3000,
  });
  ```

---

##### **`<typeName>(message, options)`**
Convenience methods for each toast type (e.g., `success`, `danger`).

- **Parameters**:
  - `message` (string): The content of the toast.
  - `options` (object, optional): Same as `toast`.

- **Example**:
  ```javascript
  toaster.success("Data saved successfully!", {
    title: "Success",
    position: "bottom-right",
  });
  ```

---

#### Example Usage

```javascript
import { useToaster } from "./ToastManager";

// Create a toaster instance
const toaster = useToaster();

// Display a generic toast
toaster.toast("This is a basic toast!", {
  title: "Info",
  position: "top-center",
  autoClose: 4000,
});

// Display a success toast
toaster.success("Operation completed successfully!", {
  title: "Success",
});

// Display an error toast
toaster.danger("An error occurred. Please try again.", {
  title: "Error",
  position: "top-left",
});

// Disable auto-close
toaster.warning("This toast requires manual dismissal.", {
  title: "Warning",
  autoClose: 0, // Toast will not auto-close
});
```

---

### Why `useToaster`?

1. **Consistent Interface**  
   - `useToaster` ensures a unified way to manage toast notifications across your application.

2. **Encapsulation**  
   - Abstracts the initialization process, allowing modifications to the `ToastManager` implementation without affecting usage.

3. **Convenient and Flexible**  
   - Offers convenience methods for toast types and allows full customization of behavior and appearance.

4. **Lightweight and Modular**  
   - Designed for easy integration without bloating your application.

---

### Key Advantages

- **Predefined Styles**: Use built-in toast types for consistent styling.
- **Customizable Behavior**: Control toast position, auto-close duration, and dismiss behavior.
- **Efficient DOM Management**: Containers are lazily created and reused for optimal performance.