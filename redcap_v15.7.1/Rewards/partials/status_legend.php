<fieldset class="status-legend">
    <legend>Icons Legend</legend>
    <div class="d-flex flex-column gap-2">
        <span class="fs-6 fw-bold">Status Indicators</span>
        <div>
            <i class="fas fa-clipboard text-secondary"></i>
            <strong>Eligible:</strong> Participant meets the criteria, and the reward is awaiting approval.
        </div>
        <div>
            <i class="fas fa-ban text-danger"></i>
            <strong>Ineligible:</strong> Participant does not meet the criteria for this reward.
        </div>
        <div>
            <i class="fas fa-thumbs-up text-success"></i>
            <strong>Approved:</strong> The Reviewer has approved the reward; the Buyer can now place the order.
            <small class="small text-muted d-block">Note: A cart icon <i class="fas fa-shopping-cart"></i> will appear next to the status, allowing you to proceed with placing the order.</small>
        </div>
        <div>
            <i class="fas fa-thumbs-down text-danger"></i>
            <strong>Rejected:</strong> The Reviewer has rejected the reward.
        </div>
        <div>
            <i class="fas fa-circle-check text-success"></i>
            <strong>Completed:</strong> The order has been placed, and the participant has received the redeem code.
            <small class="small text-muted d-block">Note: A gift icon <i class="fas fa-gift"></i> will appear next to the status, allowing you to view details about the completed order.</small>
        </div>
        <div>
            <i class="fas fa-triangle-exclamation text-warning"></i>
            <strong>Invalid:</strong> An issue occurred with the eligibility criteria, preventing further action.
        </div>
        <span class="fs-6 fw-bold">Action Icons</span>
        <div>
            <i class="fas fa-square-root-variable text-primary"></i>
            <strong>Display Eligibility Logic:</strong> Shows the logic associated with the reward, which defines if a participant is eligible for approval.
        </div>
        <div>
            <span class="small text-muted">Note: If an action icon is greyed out, it indicates that the user does not have the necessary permission to perform that specific action.</span>
        </div>
    </div>
</fieldset>

<style>
    fieldset.status-legend  {
        min-width: revert;
        padding: revert;
        margin: revert;
        border: revert;
    }
    .status-legend legend {
        border: revert;
        margin: revert;
        width: revert;
        float: revert;
        width: revert;
        padding: revert;
        margin-bottom: revert;
        font-size: calc(1.275rem + .3vw);
        line-height: revert;
    }
    .status-legend ul {
        list-style-type: none;
        padding: 0;
    }
</style>