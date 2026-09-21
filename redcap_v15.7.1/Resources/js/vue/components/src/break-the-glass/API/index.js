import { useClient, useBaseUrl } from '@/utils/ApiClient'

let baseURL = useBaseUrl()

const client = useClient(baseURL, ['pid'], { timeout: 0 })

export default {
    getSettings() {
        const params = {
            route: 'GlassBreakerController:getSettings',
        }
        return client.get('', { params })
    },
    getProtectedMrnList() {
        const params = {
            route: 'GlassBreakerController:getProtectedMrnList',
        }
        return client.get('', { params })
    },
    breakTheGlass(btgData) {
        const params = {
            route: 'GlassBreakerController:breakTheGlass',
        }
        const data = { ...btgData }
        return client.post('', data, { params })
    },
    removeMrn(mrn) {
        const params = {
            route: 'GlassBreakerController:removeMrn',
        }
        const data = { mrn }
        return client.post('', data, { params })
    },

}
