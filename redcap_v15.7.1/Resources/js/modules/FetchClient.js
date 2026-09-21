/**
 * how to use the abort controller:
 * 
 * const client = new FetchClient()
 * const controller = makeAbortController()
 * const promise = client.send('', {params, method: 'GET', signal:controller.signal})
 * controller.abort()
 */

export default class FetchClient {
    #baseURL

    constructor(baseURL='') {
		/* this.#client = axios.create({
			baseURL: URL,
			timeout: 60*1000*3,
			headers: {common: {'X-Requested-With': 'XMLHttpRequest'}}, // set header for REDCap ajax
			paramsSerializer: (params) => {
				params = Object.assign({}, this.globalQueryParams, params)
				const search_params =  new URLSearchParams(params)
				return search_params.toString()
			},
		}) */
        this.#baseURL = baseURL
	}

    get defaultConfig() {
        let defaultParams = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            // add an abort signal
            // signal: abortController.signal,
        }
        return defaultParams
    }

    #applyConfigOverride(url, config, override) {
        for (let [key, value] of Object.entries(override)) {
            switch (key) {
                case 'data':
                    if(value instanceof FormData) {
                        let body = {}
                        const formData = value
                        for (let [form_key, form_value] of formData.entries()) {
                            // store multiple keys with same name in arrays
                            if(form_key in body) {
                                // make sure it is an array
                                if(!Array.isArray(body[form_key])) body[form_key] = [body[form_key]]
                                body[form_key].push(form_value)
                            }else {
                                body[form_key] = form_value
                            }
                        }
                        config.body = JSON.stringify(body)
                    }else {
                        config.body = JSON.stringify(value)
                    }
                    break;
                case 'params':
                    // apply REDCap params and those supplied by the user
                    const redcapParams = this.globalQueryParams
                    const params = { ...redcapParams, ...value }
                    const searchParams = url.searchParams
                    for (const [p_key, p_value] of Object.entries(params)) {
                        searchParams.set(p_key, p_value)
                    }
                    break;
            
                default:
                    config[key] = value
                    break;
            }
        }
        return config
    }

    getFullURL(relativeURL) {
        return new URL(relativeURL, this.#baseURL)
    }
    
    makeRequest(url) {

        const fullURL = this.getFullURL(url)
        // define the send function
        const request = (override) => {
            let config = this.#applyConfigOverride(fullURL, this.defaultConfig, override)
            
            const promise = new Promise((resolve, reject) => {
                fetch(fullURL, config)
                    .then(async (response) => {
                        const isJson = response.headers.get('content-type')?.includes('application/json');
                        const data = isJson ? await response.json() : null;

                        // check for error response
                        if (!response.ok) {
                            // get error message from body or default to response status
                            const error = (data && data.message) || response.status;
                            return reject(error);
                        }
                        resolve(data);
                    })
                    .catch((error) => {
                        console.error('Error fetching data:', error);
                        reject(error)
                    });
            })
            return promise
        }
        return request
    }

    /**
     * use this function in a derived class to
     * override the global params
     * 
     * @param {Object} params 
     * @returns 
     */
    visitGlobalParams(params) {
        return params
    }

    get globalQueryParams() {
		let params = new URLSearchParams(location.search)
		// get PID, record ID and event ID and all query params from current location
		let query_params = {}
		for(let [key, value] of params.entries()) {
			query_params[key] = value
		}
		if(window.redcap_csrf_token) query_params.redcap_csrf_token = window.redcap_csrf_token // csrf token for post requests
		query_params = this.visitGlobalParams(query_params)
        return query_params
	}

    async send(url, config) {
        const request = this.makeRequest(url)
        return request(config)
    }

}

export const makeAbortController = () => new AbortController()