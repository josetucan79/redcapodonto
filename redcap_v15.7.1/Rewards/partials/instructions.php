<span>The <strong>Participant Manager</strong> is your central hub for managing participant compensation within REDCap. This page allows you to view, approve, reject, and place orders for participant rewards based on their current status. Below is a detailed overview of the features and how they work.</span>

<div class="my-2 d-flex flex-column gap-2">
    <details>
      <summary><strong>Participant Details and Rewards Overview</strong></summary>
      <div class="field-detail">
          <span class="d-block"><strong>Participant Information:</strong> The first column displays participant details based on how the <code>Custom Reward Label</code> is configured in the settings. This may include information such as the participant's name, age, email address, or other identifiers. Proper configuration of the <code>Custom Reward Label</code> is crucial for accurately identifying participants and managing their compensation effectively.</span>
          <span class="d-block"><strong>Rewards Display:</strong> Each subsequent column represents a different reward available to participants. The current status of each reward is displayed within the respective column, giving you an at-a-glance overview of where each participant stands in the compensation process.</span>
        </div>
    </details>

    <details>
      <summary><strong>Compensation Options</strong></summary>
      <div class="field-detail">
          <span class="d-block">The Participant Manager allows you to configure multiple reward options, each with its own set of logic, value, and associated product. This flexibility enables you to tailor the compensation process to meet the specific needs of your study, ensuring that each reward is managed appropriately at different stages of the process.</span>
      </div>
    </details>
    
    <details>
      <summary><strong>Actions</strong></summary>
      <div class="field-detail">
        <span class="d-block">The Participant Manager provides a variety of actions that can be taken based on the status of each reward. These actions are designed to facilitate the management of participant compensation efficiently:</span>
        <ul>
            <li><strong>Display Eligibility Logic:</strong> View the criteria that determine whether a participant is eligible for a reward.</li>
            <li><strong>Approve or Reject Rewards:</strong> For rewards in a pending status, you can approve them if the participant meets the eligibility criteria, or reject them if they do not.</li>
            <li><strong>Place Order:</strong> Once a reward is approved, you can place an order to issue the redeem code to the participant, ensuring they receive their compensation promptly.</li>
            <li><strong>View Completed Orders:</strong> For rewards that have been fulfilled, you can view details about the order by clicking the gift icon next to the completed status.</li>
        </ul>
        <span class="d-block"><strong>Role-Based Actions:</strong> The actions available to you are determined by your role within the system. Roles are defined by the project administrator and typically include:</span>
        <ul>
            <li><strong>Reviewer:</strong> Responsible for reviewing eligibility and approving or rejecting rewards.</li>
            <li><strong>Buyer:</strong> Responsible for placing orders for approved rewards and ensuring participants receive their redeem codes.</li>
            <li><strong>Rewards Options Manager:</strong> Responsible for configuring, modifying, and managing the reward options available for participants.</li>
        </ul>
        <span class="d-block">Each role is assigned specific permissions by the project administrator, ensuring that only authorized individuals can perform certain actions within the Participant Manager.</span>
      </div>
    </details>
    
    <details>
      <summary><strong>Status Indicators</strong></summary>
      <div class="field-detail">
          <span><strong>Eligibility Review:</strong> Rewards are tagged with status indicators that reflect their current state:</span>
          <div class="d-flex flex-column gap-1 ms-2">
            <div>
                <span class="me-1"><i class="fas fa-clipboard text-secondary"></i></span>
                <strong>Eligible:</strong> Awaiting approval or rejection by the <code>Reviewer</code>.
            </div>
            <div>
                <span class="me-1"><i class="fas fa-ban text-danger"></i></span>
                <strong>Ineligible:</strong> Participant does not meet the criteria for this reward.
            </div>
            <div>
                <span class="me-1"><i class="fas fa-thumbs-up text-success"></i></span>
                <strong>Approved:</strong> Ready for the <code>Buyer</code> to place an order.
            </div>
            <div>
                <span class="me-1"><i class="fas fa-thumbs-down text-danger"></i></span>
                <strong>Rejected:</strong> Not eligible for further action, as the reward has been denied.
            </div>
            <div>
                <span class="me-1"><i class="fas fa-circle-check text-success"></i></span>
                <strong>Completed:</strong> The reward has been fulfilled, and the redeem code has been issued to the participant.
            </div>
            <div>
                <span class="me-1"><i class="fas fa-triangle-exclamation text-warning"></i></span>
                <strong>Invalid:</strong> An error occurred with the eligibility criteria, preventing the reward from being processed.
            </div>
          </div>
      </div>
    </details>

    <details>
        <summary><strong>Bulk Actions</strong></summary>
        <div class="field-detail">
        <span class="d-block"><strong>Streamlined Management:</strong> You can select actionable rewards —those with a <code>pending</code> or <code>approved</code> status— using the switches next to each item. Each reward can be individually selected with the switch in its cell, or you can select multiple rewards at once using the checkbox at the top of each column. The Bulk Actions menu then allows you to:</span>
        <ul>
            <li><strong>Approve:</strong> Approve multiple pending rewards in one step.</li>
            <li><strong>Reject:</strong> Reject multiple pending rewards.</li>
            <li><strong>Place Orders:</strong> For approved rewards, place orders for several items at once.</li>
        </ul>
        </div>
    </details>

</div>

<style>
    [class="field-detail"] {
        margin-top: 10px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    details:has([class="field-detail"]) {
        border: solid 1px #cacaca;
        border-radius: 5px;
        padding: 10px;
    }
</style>