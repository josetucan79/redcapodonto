import { useClient, useBaseUrl } from '@/utils/ApiClient'

let baseURL = useBaseUrl()

/**
 * make sure to include:
 *  - pid for current project ID
 *  - request_id if a new revision/project is requested
 **/
const client = useClient(baseURL, ['pid', 'request_id'], { timeout: 0 })

// get app settings
const getSettings = async () => {
    const config = {
        params: {
            route: 'DataMartController:getSettings',
        },
    }
    return client.get('', config)
}

const searchMRNs = async (query, start = 0, limit = 10) => {
    if (typeof query !== 'string' && !(query instanceof String)) return
    const config = {
        params: {
            route: 'DataMartController:searchMrns',
            query,
            start,
            limit,
        },
    }
    return client.get('', config)
}

const importRevision = async (file) => {
    var formData = new FormData()
    formData.append('files[]', file)
    const config = {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
        params: {
            route: 'DataMartController:importRevision',
        },
    }
    return client.post('', formData, config)
}

const deleteRevision = async (id) => {
    const config = {
        params: {
            route: 'DataMartController:deleteRevision',
        },
        data: {
            revision_id: id,
        },
    }
    return client.delete('', config)
}

/* const exportRevision = async (id) => {
    const config = {
        params: {
            route: 'DataMartController:exportRevision',
        },
    }
    const data = { revision_id: id }
    return client.post('', data, config)
} */

const runRevision = async (revision_id, mrn, config = {}) => {
    config = {
        ...config,
        params: {
            route: 'DataMartController:runRevision',
        },
    }
    const data = { revision_id, mrn }
    return client.post('', data, config)
}

const scheduleRevision = async (revision_id, mrn_list, send_feedback = false) => {
    const config = {
        params: {
            route: 'DataMartController:scheduleRevision',
        },
    }
    const data = { revision_id, mrn_list, send_feedback }
    return client.post('', data, config)
}

const addRevision = async (
    fields,
    date_min,
    date_max,
    date_range_categories,
    mrns = [],
    request_id = null
) => {
    const config = {
        params: {
            route: 'DataMartController:addRevision',
            request_id,
        },
    }
    const data = {
        mrns,
        fields,
        date_min,
        date_max,
        date_range_categories,
        request_id,
    }
    return client.post('', data, config)
}


const approveRevision = async (revision_id) => {
    const config = {
        params: {
            route: 'DataMartController:approveRevision',
        },
    }
    const data = { revision_id }
    return client.post('', data, config)
}

const useAbortController = () => {
    const controller = new AbortController()
    return controller
}

export {
    useAbortController,
    getSettings,
    searchMRNs,
    importRevision,
    deleteRevision,
    approveRevision,
    addRevision,
    runRevision,
    scheduleRevision,
}
