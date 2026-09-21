import { useClient, useBaseUrl } from '@/utils/ApiClient'

let baseURL = useBaseUrl()

/**
 * make sure to include:
 *  - pid for current project ID
 *  - request_id if a new revision/project is requested
 **/
export const client = useClient(baseURL, [], { timeout: 0 })

// get app settings
export const getSettings = async () => {
    const config = {
        params: {
            route: 'CdisController:getSettings',
        },
    }
    return client.get('', config)
}

/**
 * save settigns that are common
 * to all FHIR systems
 *
 * @param {Object} settings
 * @returns {Promise}
 */
export const saveSettings = async (settings) => {
    const config = {
        params: {
            route: 'CdisController:saveSettings',
        },
    }
    return client.post('', { settings }, config)
}

/**
 * save settigns that are common
 * to all FHIR systems
 *
 * @param {Object} settings
 * @returns {Promise}
 */
export const saveCustomMapping = async (customMapping) => {
    const config = {
        params: {
            route: 'CdisController:saveCustomMapping',
        },
    }
    return client.post('', { customMapping }, config)
}

/**
 * save settigns of a FHIR system
 *
 * @param {Object} settings
 * @returns {Promise}
 */
export const upsertFhirSettings = async (settings) => {
    const config = {
        params: {
            route: 'CdisController:upsertFhirSettings',
        },
    }
    return client.post('', { settings }, config)
}

/**
 * save settigns of a FHIR system
 *
 * @param {Object} settings
 * @returns {Promise}
 */
export const deleteFhirSystem = async (ehr_id) => {
    const config = {
        params: {
            route: 'CdisController:deleteFhirSystem',
            ehr_id,
        },
    }
    return client.delete('', config)
}

export const updateFhirSystemsOrder = async (order) => {
    const config = {
        params: {
            route: 'CdisController:updateFhirSystemsOrder',
        },
    }
    return client.post('', { order }, config)
}

export const getExpiredTokens = async () => {
    const config = {
        params: {
            route: 'CdisController:getExpiredTokens',
        },
    }
    return client.get('', config)
}
export const deleteExpiredTokens = async (ehr_id) => {
    const config = {
        params: {
            route: 'CdisController:deleteExpiredTokens',
            ehr_id: ehr_id,
        },
    }
    return client.delete('', config)
}

export const useAbortController = () => {
    const controller = new AbortController()
    return controller
}
