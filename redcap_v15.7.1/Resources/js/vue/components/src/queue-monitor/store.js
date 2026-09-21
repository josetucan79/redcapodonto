import {reactive} from 'vue'
import FetchClient from '../../../../modules/FetchClient.js';

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

const store = reactive({
    loading: false,
    data: [],
    metadata: {},
    list: [], //
    async fetchMessages(page=0, perPage=0, query='') {
        try {
            this.loading = true
            const params = {
                route: 'QueueController:getList',
                page, perPage, query,
            };
            const response = await client.send('', {params, method: 'GET'});
            const {data, metadata} = response
            this.data = data
            this.metadata = metadata
        } catch (error) {
            console.log(error)
        }finally {
            this.loading = false
        }
    },
    async updatePriority(ID, priority) {
        try {
            const params = {
                route: 'QueueController:setPriority',
            };
            const formData = {ID, priority}
            const response = await client.send('', {method: 'POST', params, data: formData,});
            const { data } = response
            return true
        } catch (error) {
            return false
        }finally {

        }
    },
    async deleteMessage(ID) {
        try {
            const params = {
                route: 'QueueController:deleteMessage',
            };
            const formData = {ID}
            const response = await client.send('', {method: 'DELETE', params, data: formData,});
            const { data } = response
            return true
        } catch (error) {
            return false
        }finally {

        }
    },

})





export { store as default }
