import { useClient, useBaseUrl } from '@/utils/ApiClient'

let baseURL = useBaseUrl()

const client = useClient(baseURL, ['pid'], { timeout: 0 })

export default {
    // balance
    checkBalance() {
        const params = {
            route: 'RewardsController:checkBalance',
        }
        return client.get('', { params })
    },
    getSettings() {
        const params = {
            route: 'RewardsController:getSettings',
        }
        return client.get('', { params })
    },
    // reward options
    getRewardOption(id) {
        const params = {
            route: 'RewardsController:getRewardOption',
            reward_option_id: id,
        }
        return client.get('', { params })
    },
    listRewardOptions(page = 1, perPage = 100) {
        const params = {
            route: 'RewardsController:listRewardOptions',
            _page: page,
            _per_page: perPage,
        }
        return client.get('', { params })
    },
    createRewardOption(product, value_amount, eligibility_logic) {
        const params = {
            route: 'RewardsController:createRewardOption',
        }
        const data = { product, value_amount, eligibility_logic }
        return client.post('', data, { params })
    },
    updateRewardOption(id, product, value_amount, eligibility_logic) {
        const params = {
            route: 'RewardsController:updateRewardOption',
            reward_option_id: id,
        }
        const data = { product, value_amount, eligibility_logic }
        return client.post('', data, { params })
    },
    deleteRewardOption(id, force_delete = false) {
        const params = {
            route: 'RewardsController:deleteRewardOption',
            reward_option_id: id,
            force: force_delete ? 1 : 0,
        }
        return client.delete('', { params })
    },
    restoreRewardOption(id) {
        const params = {
            route: 'RewardsController:restoreRewardOption',
            reward_option_id: id,
        }
        const data = {}
        return client.post('', data, { params })
    },
    // products
    listProducts(page = 1, perPage = 100) {
        const params = {
            route: 'RewardsController:listProducts',
            _page: page,
            _per_page: perPage,
        }
        return client.get('', { params })
    },
    // products
    getCatalog(page = 1, perPage = 100) {
        const params = {
            route: 'RewardsController:getCatalog',
            _page: page,
            _per_page: perPage,
        }
        return client.get('', { params })
    },
    getChoiceProducts(page = 1, perPage = 100) {
        const params = {
            route: 'RewardsController:getChoiceProducts',
            _page: page,
            _per_page: perPage,
        }
        return client.get('', { params })
    },
    getChoiceProduct(utid) {
        const params = {
            route: 'RewardsController:getChoiceProduct',
            utid: utid,
        }
        return client.get('', { params })
    },
    // participants
    getRecord(id) {
        const params = {
            route: 'RewardsController:getRecord',
            record_id: id,
        }
        return client.get('', { params })
    },
    listRecords({
        arm_num = 1,
        page = 1,
        perPage = 100,
        query = '',
        status = [],
    }) {
        const params = {
            route: 'RewardsController:listRecords',
            _page: page,
            _per_page: perPage,
            _filter: { query, status },
            arm_num,
        }
        return client.get('', { params })
    },
    sendOrderEmail(arm_num, record_id, reward_option_id, order_id) {
        const params = {
            route: 'RewardsController:sendOrderEmail',
        }
        const data = {
            redcap_record_id: record_id,
            reward_option_id: reward_option_id,
            order_id: order_id,
            arm_num: arm_num,
        }
        return client.post('', data, { params })
    },
    performAction(action, record_id, option_id, arm_num, comment = '') {
        const params = {
            route: 'RewardsController:performAction',
        }
        const data = {
            action,
            redcap_record_id: record_id,
            reward_option_id: option_id,
            arm_num,
            comment,
        }
        return client.post('', data, { params })
    },
    scheduleAction(action, reward_record_pairs, arm_num, comment = '') {
        const params = {
            route: 'RewardsController:scheduleAction',
        }
        const data = {
            action,
            reward_record_pairs,
            arm_num,
            comment,
        }
        return client.post('', data, { params })
    },
    clearCache(key) {
        const params = {
            route: 'RewardsController:clearCache',
        }
        const data = {
            key,
        }
        return client.post('', data, { params })
    },
    recalculateRecordStatus(record_id, option_id, arm_num) {
        const params = {
            route: 'RewardsController:recalculateRecordStatus',
        }
        const data = {
            redcap_record_id: record_id,
            reward_option_id: option_id,
            arm_num,
        }
        return client.post('', data, { params })
    },
}
