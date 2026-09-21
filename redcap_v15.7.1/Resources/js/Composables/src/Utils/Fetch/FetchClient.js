export default class FetchClient {
    constructor(baseURL) {
        this.baseURL = baseURL;
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }
    }
    
    // Generic method to update default headers
    setHeader(key, value) {
        if (value) {
            this.defaultHeaders[key] = value;
        } else {
            delete this.defaultHeaders[key];
        }
    }

    // Method to add CSRF token
    addCsrfToken() {
        if (window.redcap_csrf_token) {
            this.setHeader('X-Csrf-Token', window.redcap_csrf_token);
        } else {
            console.warn('CSRF token is not available.');
        }
    }

    buildURL(route, params = {}) {
        const searchParams = new URLSearchParams(location.search);
        searchParams.append('route', route);

        // Override default searchParams with those in `params`
        Object.entries(params).forEach(([key, value]) => {
            searchParams.set(key, value); // `set` replaces the value if the key exists
        });

        return `${this.baseURL}?${searchParams.toString()}`;
    }

    async request(route, method = 'GET', data = null, config = {}) {
        const { headers = {}, params = {}, controller, ...restConfig } = config;

        // Extract signal from controller if provided
        const signal = controller?.signal;


        // Merge default headers with custom headers
        const mergedHeaders = { ...this.defaultHeaders, ...headers };

        // Build the URL with overridden searchParams
        const url = this.buildURL(route, params);

        // Prepare the request configuration
        const requestConfig = {
            method,
            headers: mergedHeaders,
            signal, // Include signal if available
            ...restConfig, // Include any additional config
        };

        // Attach the body if data is provided
        if (data) {
            requestConfig.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, requestConfig);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response;
        } catch (error) {
            console.error('Error:', error);
            throw error;
        }
    }

    // Method for each HTTP verb
    async get(route, config = {}) {
        return this.request(route, 'GET', null, config);
    }

    async post(route, data, config = {}) {
        return this.request(route, 'POST', data, config);
    }

    async put(route, data, config = {}) {
        return this.request(route, 'PUT', data, config);
    }

    async delete(route, data, config = {}) {
        return this.request(route, 'DELETE', data, config);
    }
}
