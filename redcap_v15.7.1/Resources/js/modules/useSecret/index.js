export default (selectorOrElement, config = {}) => {
    let elements = [];

    // Determine if the argument is a selector, an HTMLElement, or an array of HTMLElements
    if (typeof selectorOrElement === 'string') {
        elements = document.querySelectorAll(selectorOrElement);
    } else if (selectorOrElement instanceof HTMLElement) {
        elements = [selectorOrElement];
    } else if (Array.isArray(selectorOrElement) && selectorOrElement.every(el => el instanceof HTMLElement)) {
        elements = selectorOrElement;
    } else {
        throw new Error('Invalid argument: expected a selector, an HTMLElement, or an array of HTMLElements.');
    }

    // Function to initialize each input element
    const initializeElement = (inputElement) => {
        // Check if the element is already initialized
        if (inputElement.getAttribute('data-secret-initialized')) {
            return; // If initialized, do nothing
        }

        // Mark the element as initialized
        inputElement.setAttribute('data-secret-initialized', 'true');

        // Wrap the input in a container to position the button correctly
        const container = document.createElement('div');
        container.style.position = 'relative';
        container.style.display = 'inline-block';
        container.style.width = '100%';
        container.setAttribute('data-secret-container', 'true');
        inputElement.parentNode.insertBefore(container, inputElement);
        container.appendChild(inputElement);

        // Default settings
        const defaultSettings = {
            startHidden: true
        };

        // Override default settings with config object and attributes
        const settings = {
            startHidden: config.startHidden !== undefined ? config.startHidden : (inputElement.getAttribute('data-start-hidden') !== 'false')
        };

        // Create the toggle button
        const toggleButton = createToggleButton();

        // Show method
        function show() {
            inputElement.type = 'text';
            container.setAttribute('data-secret-state', 'visible');
            toggleButton.innerHTML = '<i class="fa-solid fa-eye-slash"></i>'; // Change icon to 'eye-slash'
        }

        // Hide method
        function hide() {
            inputElement.type = 'password';
            container.setAttribute('data-secret-state', 'hidden');
            toggleButton.innerHTML = '<i class="fa-solid fa-eye"></i>'; // Change icon to 'eye'
        }

        // Toggle function to switch between show and hide
        function toggleSecret() {
            if (inputElement.type === 'text') {
                hide();
            } else if (inputElement.type === 'password') {
                show();
            }
        }

        // Attach the toggle functionality to the button
        toggleButton.addEventListener('click', toggleSecret);

        // Insert the button into the container
        container.appendChild(toggleButton);

        // Apply initial state based on settings
        if (settings.startHidden) {
            hide();
        } else {
            show();
        }
    };

    // Function to create the toggle button
    const createToggleButton = () => {
        const button = document.createElement('button');
        button.type = 'button';
        button.style.position = 'absolute';
        button.style.right = '10px';
        button.style.top = '50%';
        button.style.transform = 'translateY(-50%)';
        button.style.backgroundColor = 'transparent';
        button.style.border = 'none';
        button.style.cursor = 'pointer';
        button.style.padding = '0';
        button.style.fontSize = '1em';
        return button;
    };

    // Initialize each element
    elements.forEach(initializeElement);
}
