import { useClient, useBaseUrl } from '../../utils/apiClient'

const baseURL = useBaseUrl()
const client = useClient(baseURL, ['pid'], { timeout: 0 })

/* 
pid: 15
route: FhirMappingHelperController:getResources
fhir_category: Laboratory
mrn: 202434
options: {"category":"laboratory","date":["ge2023-08-02","le2023-08-10"]}
*/

const fetchResource = (mrn, fhir_category = null, searchParams = {}) => {
    const params = {
        route: 'FhirMappingHelperController:getResource',
        mrn,
        fhir_category,
        params: searchParams,
    }

    return client.get('', { params })
}

const makeCustomRequest = (method, relative_url, options) => {
    const params = {
        route: 'FhirMappingHelperController:getFhirRequest',
    }
    const data = {
        relative_url: relative_url,
        options: options,
        method: method,
    }
    return client.post('', data, { params })
}

const fetchSettings = (searchParams = {}) => {
    const params = {
        route: 'FhirMappingHelperController:getSettings',
        params: searchParams,
    }

    return client.get('', { params })
}

export { fetchResource, makeCustomRequest, fetchSettings }
