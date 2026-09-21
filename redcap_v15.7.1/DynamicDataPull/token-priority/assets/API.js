const baseURL = window.app_path_webroot
const { useFetch } = await import(`${baseURL}Resources/js/Composables/index.es.js.php`)

export default class API {
    /**
     *
     */
    constructor(eventBus) {
        this.eventBus = eventBus
        this.fetchClient = useFetch(baseURL)
        this.fetchClient.addCsrfToken()
    }

    async getSettings() {
        const route = 'CdisTokenController:getSettings'
        try {
            const response = await this.fetchClient.get(route);
            const json = await response.json();
            const {rules=[], users=[]} = json
            return json;
        } catch (error) {
            console.error('Error:', error);
            throw error;
        }
    }

    async fetchRules() {
        const route = 'CdisTokenController:getRules'
        try {
            const response = await this.fetchClient.get(route);
            const json = await response.json();
            const {rules=[]} = json
            return rules;
        } catch (error) {
            console.error('Error:', error);
            throw error;
        }
    }

    async saveChanges(changes) {
        const route = 'CdisTokenController:saveChanges'
        const data = {
            changes: changes
        }

        try {
            const response = await this.fetchClient.post(route, data);
            const json = await response.json();
            return json;
        } catch (error) {
            console.error('Error:', error);
            throw error;
        }
    }

    async addRule(rule) {
        const route = 'CdisTokenController:addRule'
        const data = {
            rule: rule
        }

        try {
            const response = await this.fetchClient.post(route, data);
            const json = await response.json();
            return json;
        } catch (error) {
            console.error('Error:', error);
            throw error;
        }
    }
}