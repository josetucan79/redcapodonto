### Documentation for `useModal`

The `useModal` function provides a simple and consistent way to create and manage modal instances in your application. It wraps the `Modal` class, ensuring a uniform interface for modal creation and usage. This approach promotes a cleaner and more maintainable codebase by abstracting the initialization process.

---

### `useModal` Function

The `useModal` function encapsulates the `Modal` class instantiation, providing a consistent and reusable interface.

```javascript
const useModal = (template = null) => {
  return new Modal(template);
};
export { useModal };
```

#### Parameters
- **`template`** (optional):  
  - A string selector or an `HTMLTemplateElement` that defines the modal's structure.  
  - If not provided, the default modal template is used.

#### Returns
- An instance of the `Modal` class.

---

### Features

1. **Default Modal Template**  
   - Includes a header with a title and close button, a body section, and a footer with OK and Cancel buttons.  
   - Fully customizable by providing your own template.

2. **Dynamic Modal Content**  
   - Easily update the modal's title, body content, and button labels using the `show`, `alert`, or `confirm` methods.

3. **Flexible Modal Sizes**  
   - Supports predefined sizes (`sm`, `md`, `lg`, `xl`, `auto`) that can be set dynamically.

4. **Promise-Based Interaction**  
   - `show`, `alert`, and `confirm` methods return a promise that resolves based on user actions (e.g., clicking OK or Cancel).

5. **Clean Abstraction**  
   - By using `useModal`, you ensure that modal creation and management follow a consistent pattern across your application.

6. **Built-In Button Functionality**  
   - The OK and Cancel buttons trigger promise resolution, while the close button allows users to dismiss the modal easily.

---

### Example Usage

The `useModal` function simplifies the process of creating and interacting with modals.

```javascript
import { useModal } from "./Modal";

// Create a new modal instance
const modal = useModal();

// Show a confirmation dialog
modal
  .confirm({
    title: "Delete Item",
    body: "Are you sure you want to delete this item?",
    okText: "Yes, Delete",
    cancelText: "No, Cancel",
    size: "sm",
  })
  .then((result) => {
    if (result) {
      console.log("Item deleted.");
    } else {
      console.log("Action canceled.");
    }
  });

// Show an alert dialog
modal
  .alert({
    title: "Error",
    body: "An error occurred while processing your request.",
    okText: "Understood",
    size: "md",
  })
  .then(() => {
    console.log("Alert acknowledged.");
  });

// Show a modal with custom options
modal
  .show({
    title: "Custom Modal",
    body: "<p>This is a custom modal body.</p>",
    okText: "Save",
    cancelText: "Discard",
    size: "lg",
  })
  .then((result) => {
    if (result) {
      console.log("Changes saved.");
    } else {
      console.log("Changes discarded.");
    }
  });

// Destroy the modal instance when it's no longer needed
modal.destroy();
```

---

### Why `useModal`?

1. **Consistent Interface**  
   - Using `useModal` ensures a uniform way of creating and managing modals throughout your application, reducing potential inconsistencies.

2. **Encapsulation**  
   - The `useModal` function abstracts the initialization process, allowing you to modify the underlying implementation without changing the usage pattern.

3. **Simplified Syntax**  
   - Instead of manually instantiating the `Modal` class, `useModal` provides a clean and intuitive interface for creating modals.

4. **Reusability**  
   - Encourages reusable modal logic, making your codebase easier to maintain and extend.

---

### Modal Class API

#### Core Methods

- **`show(options)`**  
  Displays the modal with customizable content and size.
  - **Options**:
    - `title` (string): Title text of the modal.
    - `body` (string): Body content of the modal (HTML allowed).
    - `okText` (string): Text for the OK button.
    - `cancelText` (string): Text for the Cancel button.
    - `size` (string): Modal size (`sm`, `md`, `lg`, `xl`, `auto`).
  - **Returns**: A promise resolving to `true` (OK clicked) or `false` (Cancel clicked).

- **`confirm(options)`**  
  Shortcut for displaying a confirmation dialog with predefined content and behavior.
  - Same parameters as `show`.

- **`alert(options)`**  
  Shortcut for displaying an alert dialog with only an OK button.
  - Same parameters as `show`, excluding `cancelText`.

- **`destroy()`**  
  Removes the modal instance from the DOM.