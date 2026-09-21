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
            route: 'EmailUsersController:getSettings',
        },
    }
    return client.get('', config)
}

export const getQueries = async () => {
    const config = {
        params: {
            route: 'EmailUsersController:getQueries',
        },
    }
    return client.get('', config)
}

export const saveQuery = async ({id, query, name, description}) => {
    const config = {
        params: {
            route: 'EmailUsersController:saveQuery',
        },
    }
    const data = {id, query, name, description}
    return client.post('', data, config)
}

export const deleteQuery = async (id) => {
    const config = {
        params: {
            route: 'EmailUsersController:deleteQuery',
            id: id,
        },
    }
    return client.delete('', config)
}

export const testQuery = async (query, page=1, perPage=50) => {
    const config = {
        params: {
            route: 'EmailUsersController:testQuery',
            _page: page,
            _per_page: perPage,
        },
    }
    const data = {query}
    return client.post('', data, config)
}

export const generateCSV = async (query) => {
    const config = {
        params: {
            route: 'EmailUsersController:generateCSV',
        },
    }
    const data = {query}
    return client.post('', data, config)
}

export const sendEmails = async (data={from, subject, body, query, fromName, cc, bcc}) => {
    const config = {
        params: {
            route: 'EmailUsersController:sendEmails',
        },
    }
    return client.post('', data, config)
}

export const previewMessage = (subject, body, email) => {
    const config = {
        params: {
            route: 'EmailUsersController:previewMessage',
        },
    }
    const data = { subject, body, email}
    return client.post('', data, config)
}

export const getMessages = async (page=1, perPage=50) => {
    const config = {
        params: {
            route: 'EmailUsersController:getMessages',
            _page: page,
            _per_page: perPage,
        },
    }
    return client.get('', config)
}

export const deleteMessage = async (id) => {
    const config = {
        params: {
            route: 'EmailUsersController:deleteMessage',
            id: id,
        },
    }
    return client.delete('', config)
}