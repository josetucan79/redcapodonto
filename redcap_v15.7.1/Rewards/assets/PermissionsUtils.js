import {useModal} from '../../Resources/js/Composables/index.es.js.php'

const templateFn = () => `
<template id="permissions-changed-dialog-template">
    <header>{{message}}</header>
    <div>
        <span class="d-block fw-bold my-2">Please provide a reason for this change:</span>
        <input class="form-control form-control-sm" type="text" />
    </div>
    <div class="alert alert-warning mt-2 mb-0">
        <spanp><strong>Note:</strong> This change will be logged for auditing purposes.</span>
    </div>
</template>
`

const stringToTemplate = (templateString) => {
    const container = document.createElement('div');
    container.innerHTML = templateString.trim(); // Ensure the string is clean and free of leading/trailing whitespace
    const template = container.querySelector('template');
    if (!template) {
        throw new Error('No <template> element found in the provided string.');
    }
    return template;
};

const showDialogWithTemplate = async (selector, options = {}) => {
    const modal = useModal();
    const defaultOptions ={
        title: 'Warning',
    }
    options = {...defaultOptions, ...options}
    // Get the template content
    const template = document.querySelector(selector);
    const content = template.content.cloneNode(true); // Clone the content

    // Convert the template content to an HTML string
    const container = document.createElement('div');
    container.appendChild(content);
    const bodyHTML = container.innerHTML;

    // Show the dialog
    const promise = modal.show({
        title: options.title,
        body: bodyHTML,
        cancelText: 'Cancel',
        okText: 'Continue',
    });

    const acknowledgmentCheckbox = modal.dialog.querySelector('input[type="checkbox"]')
    const reasonInput = modal.dialog.querySelector('input[type="text"]')
    const okButton = modal.dialog.querySelector('button[data-btn-ok]')
    okButton.setAttribute('disabled', true)

    // Function to toggle the OK button state
    const toggleOkButtonState = () => {
        if (reasonInput.value.trim() !== '' && acknowledgmentCheckbox.checked) {
            okButton.removeAttribute('disabled');
        } else {
            okButton.setAttribute('disabled', true);
        }
    };

    // Add event listeners to input and checkbox
    reasonInput.addEventListener('input', toggleOkButtonState);
    acknowledgmentCheckbox.addEventListener('change', toggleOkButtonState);

    const response = await promise

    modal.destroy()

    // return false if canceled
    if(!response) return false

    return reasonInput.value
};

// Function to get all form values as an object
function getFormValues(form, options = {}) {
    const { skip = [] } = options;

    // Collect form data
    const formData = new FormData(form);

    // Build the resulting values object
    const values = {};
    formData.forEach((value, key) => {
        // Skip specified keys
        if (skip.includes(key)) return;

        // Always overwrite the value for each key (no arrays)
        values[key] = value;
    });

    return values;
}

const addReasonToForm = (form, reason) => {
    const hiddenField = document.createElement("input");

    // Set attributes for the hidden field
    hiddenField.type = "hidden";
    hiddenField.name = "reason"; // The field name
    hiddenField.value = `${reason}`; // The field value

    // Append the hidden field to the form
    form.appendChild(hiddenField);
}


export const useFormModal = (selector) => {
    const form = document.querySelector(selector); // Replace with your form's ID or selector
    if (!form) return



    // do not include these key when checking for form values
    const skipValues = ['redcap_csrf_token']

    // Store original form content on load
    const originalFormValues = getFormValues(form, {skip: skipValues});
    
    // Add submit event listener
    form.addEventListener("submit", async (event) => {
        // Prevent the form from submitting
        event.preventDefault();
        
        // Get the current form values
        const currentFormValues = getFormValues(form, {skip: skipValues});

        // Compare original and current form values
        let isEdited = JSON.stringify(originalFormValues) !== JSON.stringify(currentFormValues);
        
        // comment this to show the dialog only if changes are detected
        // if(!isEdited) return form.submit();
        
        const reason = await showDialogWithTemplate('#permissions-changed-dialog-template');
        
        if (reason) {
            addReasonToForm(form, reason)
            // Proceed with form submission if confirmed
            form.submit();
        }
    });
}