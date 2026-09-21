export default class SPA {
    dynamicScripts = [];
    contentDiv;
    
    constructor() {
        this.contentDiv = document.getElementById('content-container');

        // Function to detect hash changes
        const onHashChange = () => {
            const page = location.hash.substring(1) || "home"; // Default to home
            this.loadPage(page);
        };

        // Listen for hash changes
        window.addEventListener("hashchange", onHashChange);
        window.addEventListener("DOMContentLoaded", onHashChange); // Initial load
    }

    async loadPage(page) {
        try {
            // Remove old scripts
            this.cleanupScripts();

            // Clear previous content before injecting new content
            this.contentDiv.innerHTML = "";

            // Fetch the requested page HTML
            const response = await fetch(`/examples/${page}/template.html`);
            if (!response.ok) {
                throw new Error(`Page ${page} not found`);
            }

            // Inject HTML directly without script parsing
            this.contentDiv.innerHTML = await response.text();

            // Load the corresponding script
            this.loadScript(`/examples/${page}/index.js`);
            
        } catch (error) {
            console.error(error);
            this.contentDiv.innerHTML = `<h2>404 - Page Not Found</h2>`;
        }
    }

    cleanupScripts() {
        // Remove any dynamically loaded scripts
        this.dynamicScripts.forEach(script => script.remove());
        this.dynamicScripts.length = 0;
    }

    async loadScript(scriptUrl) {
        try {
            // Fetch the script content
            const response = await fetch(scriptUrl);
            if (!response.ok) throw new Error(`Failed to fetch script: ${scriptUrl}`);
            const scriptContent = await response.text();

            // Inject as a module script
            const scriptElement = document.createElement("script");
            scriptElement.setAttribute('data-dynamic', true);
            scriptElement.type = "module";
            scriptElement.textContent = scriptContent;

            document.body.appendChild(scriptElement);
            this.dynamicScripts.push(scriptElement);

            console.log(`Loaded script: ${scriptUrl}`);
        } catch (error) {
            console.error(error);
        }
    }
}
