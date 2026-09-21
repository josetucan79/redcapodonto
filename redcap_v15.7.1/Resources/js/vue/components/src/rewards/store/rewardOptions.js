import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import API from '../API'

const collection = 'rewardOptions'
const paginationInitialState = {
    page: 1,
    perPage: 25,
    total: undefined,
}

const useStore = defineStore(collection, () => {
    const currentItem = ref({})
    const loading = ref(false)
    const list = ref([])
    const error = ref()
    const pagination = reactive({ ...paginationInitialState })

    function resetList() {
        list.value = []
        for (const [key, value] of Object.entries(paginationInitialState)) {
            pagination[key] = value
        }
    }

    async function fetch(id) {
        try {
            loading.value = true
            const response = await API.getRewardOption(id)
            const { data: _data } = response?.data ?? {}
            currentItem.value = _data
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function fetchList(page = undefined, perPage = undefined) {
        try {
            loading.value = true
            const response = await API.listRewardOptions(page, perPage)
            const { data: _data, metadata: _metadata } = response?.data ?? {}
            list.value = _data
            pagination.total = _metadata?.total ?? 0
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function create(product, value_amount, eligibility_logic) {
        try {
            loading.value = true
            const response = await API.createRewardOption(product, value_amount, eligibility_logic)
            return response
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function update(id, product, value_amount, eligibility_logic) {
        try {
            loading.value = true
            const response = await API.updateRewardOption(id, product, value_amount, eligibility_logic)
            return response
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function remove(id) {
        try {
            loading.value = true
            const response = await API.deleteRewardOption(id)
            return response
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function forceRemove(id) {
        try {
            loading.value = true
            const response = await API.deleteRewardOption(id, true)
            return response
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function restore(id) {
        try {
            loading.value = true
            const response = await API.restoreRewardOption(id)
            return response
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    return {
        loading,
        error,
        currentItem,
        list,
        pagination,
        resetList,
        fetch,
        fetchList,
        create,
        update,
        remove,
        forceRemove,
        restore,
    }
})

export { useStore as default }