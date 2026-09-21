import { useClient, useBaseUrl } from '@/utils/ApiClient'
import { PER_PAGE } from '../variables'

let baseURL = useBaseUrl()

const client = useClient(baseURL, ['pid'], { timeout: 0 })


export default {
    // balance
    getRecords(page = 1, perPage = PER_PAGE) {
        const params = {
            route: 'CdpController:getDdpRecordsDataStats',
            _page: page,
            _per_page: perPage,
        }
        return client.get('', { params })
    },
    processField(field) {
        const params = {
            route: 'CdpController:processField',
        }
        const data = { ...field }
        return client.post('', data, { params })
    },
    processCachedData(background, send_feedback) {
        const params = {
            route: 'CdpController:processCachedData',
        }
        const data = { background, send_feedback }
        return client.post('', data, { params })
    },
}
