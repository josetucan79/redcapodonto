import { useClient, useBaseUrl } from '../../utils/apiClient'

const baseURL = useBaseUrl()
const client = useClient(baseURL, ['pid'])

const checkDesign = (searchParams = {}) => {
    const params = {
        route: 'DataMartController:checkDesign',
        params: searchParams,
    }

    return client.get('', { params })
}

const executeFixCommand = (command, searchParams = {}) => {
    const params = {
        route: 'DataMartController:executeFixCommand',
        params: searchParams,
    }
    const data = { command }

    return client.post('', data, { params })
}

const fixDesign = (searchParams = {}) => {
    const params = {
        route: 'DataMartController:fixDesign',
        params: searchParams,
    }
    const data = {}
    return client.post('', data, { params })
}

const notifyFix = (searchParams = {}) => {
    const params = {
        route: 'DataMartController:notifyFix',
        params: searchParams,
    }
    const data = {}
    return client.post('', data, { params })
}

export { checkDesign, executeFixCommand, fixDesign, notifyFix }
