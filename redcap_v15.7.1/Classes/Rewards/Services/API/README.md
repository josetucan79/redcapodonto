# API Services Folder

This folder contains classes responsible for managing **resources** in the participant compensation system. These services handle the CRUD (Create, Read, Update, Delete) operations for various resources and return data in JSON format for use in the UI. 

## Structure

### 1. **Resource Management Services**
These classes focus on managing data entities, such as participants, rewards, and orders. Each resource service implements the `JsonSerializable` interface, which enables data serialization to JSON format for easy frontend display and manipulation.

- **Purpose**:
  These services manage resources in the system by interfacing with the database or external APIs. They provide CRUD functionality, ensuring that the resources can be created, retrieved, updated, and deleted as necessary.

- **Usage**:
  Each resource service provides a set of methods that interact with the backend and return resource objects in JSON format. These methods can be used to populate the UI with data or to modify resources as part of the application flow.

- **Example**:
  ```php
  $rewardService = new RewardService();
  $reward = $rewardService->read($rewardId);
  echo json_encode($reward);  // Outputs the reward in JSON format
  ```

### 2. **JSON Representation**
Each service serializes its data into a JSON object, making it easily consumable by the frontend. This standardized representation ensures consistency across the application when displaying or processing data.

## Key Concepts

- **Separation of Concerns**:
  This folder contains services that **only** handle resource-related CRUD operations. **Action services**, which deal with workflow-specific actions such as approving rewards or placing orders, are located in a different folder.

- **Consistency**:
  All resource data is returned in a consistent JSON format, ensuring uniformity when passing data between the backend and frontend.

## Best Practices

- **Use Resource Services for CRUD Operations**:
  Resource services should be used for all data-related operations such as fetching, updating, or deleting participants, rewards, and orders.

- **Use Action Services for Business Logic**:
  If a service needs to perform an action (e.g., approve a reward or place an order), the corresponding **Action Service** (located in a separate folder) should be used. Action services handle specific workflows and are not included in this folder to maintain separation of concerns.