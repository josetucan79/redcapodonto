import FetchClient from '../../../../../modules/FetchClient.js'

class SystemFetchClient extends FetchClient {
    #skipParams = ['pid']
    visitGlobalParams(params) {
        const filtered = {}
        for (const [key, value] of Object.entries(params)) {
            if(this.#skipParams.includes(key)) continue
            filtered[key] = value
        }
        return filtered
    }
}

let baseURL = '';
if(window.app_path_webroot_full) {
    baseURL = `${window.app_path_webroot_full}redcap_v${window.redcap_version}/`;
}else {
    baseURL = `${location.protocol}//${location.host}/api`
}
const client = new SystemFetchClient(baseURL);

export default{
    getSettings() {
        const params = {
            route: 'EmailUsersController:getSettings',
        }
        return client.send('', {params, method: 'GET'})
    },
    getUsers(page=1, perPage=100, query='') {
        const params = {
            route: 'EmailUsersController:getUsers',
            _page: page,
            _per_page: perPage,
            _query: query,
        }
        return client.send('', {params, method: 'GET'})
    },
    scheduleEmails(data) {
        const params = {
            route: 'EmailUsersController:scheduleEmails',
            }
        return client.send('', {params, data, method: 'POST'})
    },
}