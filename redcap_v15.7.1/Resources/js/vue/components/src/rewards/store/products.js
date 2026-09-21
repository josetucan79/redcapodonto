import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import API from '../API'

const collection = 'products'
const paginationInitialState = {
    page: 1,
    perPage: 25,
    total: undefined,
}

const useStore = defineStore(collection, () => {
    const currentItem = ref()
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

    async function fetchList(page = undefined, perPage = undefined) {
        try {
            loading.value = true
            const response = await API.listProducts(page, perPage)
            const { data: _data, metadata: _metadata } = response?.data ?? {}
            list.value = _data
            pagination.total = _metadata?.total ?? 0
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    function findItem(product_id) {
        const items = list.value ?? []
        for (const item of items) {
            if (item?.product_id === product_id) return item
        }
        return
    }

    return {
        loading,
        error,
        list,
        pagination,
        resetList,
        fetchList,
        findItem,
    }
})

export { useStore as default }
