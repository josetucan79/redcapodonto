# Business Logic Services Folder

This folder contains services responsible for executing business logic related to participant compensation workflows. These services are separated from the API services, which handle CRUD operations.

## Structure

1. **Notification Services**: Handle tasks related to email communications, such as sending order or reward status updates.
2. **Approval Services**: Manage the approval and rejection of rewards, as well as placing orders based on reward decisions.
3. **Eligibility Services**: Validate reward criteria to ensure participants meet the necessary conditions before approving or processing rewards.

## Key Concepts

- **Separation of Concerns**: These services focus on specific workflows and decision-making, while CRUD operations are handled by API services.
- **Workflow Management**: Each service performs a distinct action, such as sending emails, approving rewards, or validating eligibility.

## Usage

These services should be used for workflow-related tasks like sending notifications, making approval decisions, and checking eligibility criteria, ensuring that business logic is cleanly separated from data management.