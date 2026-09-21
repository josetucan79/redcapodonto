import axios from 'axios'

/**
 * get the base URL depending on the current environment
 * and the availability of the app_path_webroot_full parameter
 * @returns {string}
 */
const useBaseUrl = () => {
    let baseURL = ''
    if (window.app_path_webroot_full) {
        baseURL = `${window.app_path_webroot_full}redcap_v${window.redcap_version}/`
    } else {
        baseURL = `${location.protocol}//${location.host}/api`
    }
    return baseURL
}

/**
 * @param {String} url base URL
 * @param {Array} contextKeys list of query params to extract from location.search
 * @returns {AxiosInstance}
 */
const useClient = (url, contextKeys = [], options = {}) => {
    const defaultOptions = {
        baseURL: url,
        timeout: 60000,
    }
    options = { ...defaultOptions, ...options }
    const axiosInstance = axios.create(options)
    // Add a request interceptor
    axiosInstance.interceptors.request.use(
        function (config) {
            const contextParams = getContextParams(contextKeys)
            for (const [key, value] of Object.entries(contextParams)) {
                config.params[key] = value
            }

            // Set the headers
            config.headers = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...config.headers,
            }

            return config
        },
        function (error) {
            return Promise.reject(error)
        }
    )

    function getContextParams(keys = []) {
        let params = new URLSearchParams(location.search)
        let query_params = {}
        for (let [key, value] of params.entries()) {
            if (!keys.includes(key)) continue
            query_params[key] = value
        }
        // always include redcap_csrf_token
        if (window.redcap_csrf_token)
            query_params.redcap_csrf_token = window.redcap_csrf_token // csrf token for post requests
        return query_params
    }

    return axiosInstance
}

const useError = (error) => {
    let response = ''
    const messages = [
        `Something happened in setting up the request that triggered an Error`,
    ]

    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        /* console.log(error.response.data)
        console.log(error.response.status)
        console.log(error.response.headers) */
        let responseMessage = `The request was made and the server responded with a status code ${error.response.status}`
        if (error?.response?.data?.message) responseMessage += ` - ${error?.response?.data?.message}`
        messages.push(responseMessage)
        response = error?.response?.data
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        /* console.log(error.request) */
        messages.push(`The request was made but no response was received`)
        response = error.request
    }
    if (error.message) {
        // Something happened in setting up the request that triggered an Error
        /* console.log('Error', error.message) */
        messages.push(error.message)
    }
    if (response && response !== '') {
        if (typeof response === 'string' || response instanceof String)
            messages.push(response)
    }
    let message = messages.join(`\n`)
    // console.log(message)
    return message
}
export { useClient, useBaseUrl, useError }
