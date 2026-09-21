export default class SPA {
    constructor() {
        this.dynamicScripts = [];
        this.contentDiv = document.getElementById("content-container");

        // Attach event listeners for navigation
        window.addEventListener("hashchange", () => this.handleNavigation());
        window.addEventListener("DOMContentLoaded", () => this.handleNavigation());
    }

    handleNavigation() {
        const page = location.hash.substring(1) || "home"; // Default to "home"
        this.loadPage(page);
    }

    async loadPage(page) {
        try {
            this.cleanupScripts();
            this.clearContent();

            const html = await this.fetchPageContent(page);
            if (!html) throw new Error(`Page ${page} not found`);

            this.injectContent(html);
            this.loadScripts();
        } catch (error) {
            console.error(error);
            this.contentDiv.innerHTML = `<h2>404 - Page Not Found</h2>`;
        }
    }

    async fetchPageContent(page) {
        try {
            const response = await fetch(`/examples/${page}.html`);
            if (!response.ok) return null;

            const html = await response.text();
            if (html.includes("<nav") && html.includes("<title>")) {
                throw new Error(`Vite served index.html instead of ${page}.html`);
            }
            return html;
        } catch (error) {
            console.error("Error fetching page content:", error);
            return null;
        }
    }

    injectContent(html) {
        const tempDiv = document.createElement("div");
        tempDiv.innerHTML = html;
        this.contentDiv.innerHTML = tempDiv.innerHTML;
        this.scriptsToLoad = tempDiv.querySelectorAll("script");
    }

    async loadScripts() {
        for (const script of this.scriptsToLoad) {
            const scriptContent = script.src ? await this.fetchScriptContent(script.src) : script.textContent;
            if (!scriptContent) continue;

            const isModule = /\bimport\b/.test(scriptContent);
            this.injectScript(scriptContent, isModule);
        }
    }

    async fetchScriptContent(src) {
        try {
            const response = await fetch(src);
            if (!response.ok) throw new Error(`Failed to fetch ${src}`);
            return await response.text();
        } catch (error) {
            console.error(error);
            return null;
        }
    }

    injectScript(content, isModule) {
        const scriptElement = document.createElement("script");
        scriptElement.setAttribute("data-dynamic", true);

        if (isModule) {
            scriptElement.type = "module";
            scriptElement.textContent = content;
        } else {
            const blob = new Blob([content], { type: "application/javascript" });
            const blobUrl = URL.createObjectURL(blob);
            scriptElement.src = blobUrl;
            scriptElement.onload = () => URL.revokeObjectURL(blobUrl);
        }

        document.body.appendChild(scriptElement);
        this.dynamicScripts.push(scriptElement);
    }

    cleanupScripts() {
        document.querySelectorAll("script[data-dynamic]").forEach(script => script.remove());
        this.dynamicScripts.forEach(script => script.remove());
        this.dynamicScripts.length = 0;
    }

    clearContent() {
        this.contentDiv.innerHTML = "";
    }
}
