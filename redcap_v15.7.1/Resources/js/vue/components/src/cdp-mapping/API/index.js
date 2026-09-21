import { useClient, useBaseUrl } from '@/utils/ApiClient'

let baseURL = useBaseUrl()

const client = useClient(baseURL, ['pid'], { timeout: 0 })

export default {
    getSettings() {
        const params = {
            route: 'CdpController:getSettings',
        }
        return client.get('', { params })
    },
    setSettings(settings) {
        const params = {
            route: 'CdpController:setSettings',
        }
        const data = { settings }
        return client.post('', data, { params })
    },
    setMappings(mappings) {
        const params = {
            route: 'CdpController:setMappings',
        }
        const data = { mappings }
        return client.post('', data, { params })
    },
    exportMappings() {
        const params = {
            route: 'CdpController:exportMappings',
        }
        const data = { }
        return client.post('', data, { params })
    },
    importMappings(file) {
        const params = {
            route: 'CdpController:importMappings',
        }
        const data = new FormData()
        data.append('file', file)
        return client.post('', data, { params })
    },
}
