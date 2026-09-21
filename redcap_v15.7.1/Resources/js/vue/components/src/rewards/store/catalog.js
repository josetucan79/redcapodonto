import { defineStore } from 'pinia'
import { ref, reactive, watchEffect } from 'vue'
import API from '../API'

const collection = 'catalog'
const paginationInitialState = {
    page: 1,
    perPage: 5,
    total: undefined,
}

const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const catalog = ref()
    const selectedItem = ref()
    const error = ref()

    async function fetchCatalog(record_id, order_id) {
        try {
            loading.value = true
            const response = await API.getCatalog(record_id, order_id)
            const { data: _data, metadata: _metadata } = response?.data ?? {}
            catalog.value = _data
            return _data
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    function findItem(utid) {
        const brands = catalog.value?.brands ?? []
        for (const brand of brands) {
            const items = brand?.items ?? []
            for (const item of items) {
                if (item?.utid === utid) return item
            }
        }
        return
    }

    async function getOrFetchCatalog() {
        if (!catalog.value) {
            await fetchCatalog()
        }
        return catalog.value
    }

    return {
        loading,
        error,
        catalog,
        selectedItem,
        findItem,
        fetchCatalog,
        getOrFetchCatalog,
    }
})

export { useStore as default }
