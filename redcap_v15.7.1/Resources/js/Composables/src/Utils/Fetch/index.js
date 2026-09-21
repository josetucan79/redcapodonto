import FetchClient from './FetchClient.js'

const useFetch = (baseURL) => {
    return new FetchClient(baseURL)
}

export { useFetch, FetchClient }