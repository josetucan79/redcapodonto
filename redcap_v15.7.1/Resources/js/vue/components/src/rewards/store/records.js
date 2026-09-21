import { defineStore } from 'pinia'
import { ref, reactive, watch, nextTick } from 'vue'
import API from '../API'
import useArmNumber from '@/rewards/utils/useRouteArmParam'

const collection = 'records'
const paginationInitialState = {
    page: 1,
    perPage: 25,
    total: undefined,
    totalPages: 0,
}

const useStore = defineStore(collection, () => {
    const currentItem = ref()
    const loading = ref(false)
    const list = ref([])
    const error = ref()
    const pagination = reactive({ ...paginationInitialState })
    const query = ref('')
    const selectedStatus = ref([])
    const arm_num = useArmNumber()
    const cached = ref(false)

    let disablePaginationWatcher = ref(false)

    function resetList() {
        list.value = []
        for (const [key, value] of Object.entries(paginationInitialState)) {
            pagination[key] = value
        }
    }

    async function fetch(id) {
        try {
            loading.value = true
            const response = await API.getRecord(id)
            const { data: _data } = response?.data ?? {}
            currentItem.value = _data
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function updatePaginationSilently(property, value) {
        if (!(property in pagination)) return
        disablePaginationWatcher.value = true
        pagination[property] = value
        await nextTick() // await, or we will experience duoble fetch from the watch
        disablePaginationWatcher.value = false
    }

    async function fetchList(arm_num = 1) {
        try {
            loading.value = true
            const response = await API.listRecords({
                arm_num,
                page: pagination.page,
                perPage: pagination.perPage,
                query: query.value,
                status: selectedStatus.value,
            })
            const { data: _data, metadata: _metadata } = response?.data ?? {}
            list.value = _data
            pagination.total = _metadata?.total ?? 0
            cached.value = _metadata?.cached ?? false
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function clearCache() {
        try {
            const key = cached.value
            if (!key) return
            loading.value = true
            const response = await API.clearCache(key)
            return response
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    // shortcut with arm number included
    const loadRecords = async () => {
        return await fetchList(arm_num.value)
    }

    // Watch for changes to 'total' or 'perPage' and update 'totalPages'
    watch(
        [() => pagination.total, () => pagination.perPage],
        ([newTotal, newPerPage]) => {
            if (disablePaginationWatcher.value) return
            // Calculate totalPages based on total and perPage
            pagination.totalPages =
                newTotal && newPerPage ? Math.ceil(newTotal / newPerPage) : 0
            /**
             * Ensure the current page does not exceed the total number of pages.
             * If `pagination.page` is greater than `pagination.totalPages`, the page
             * is reset to 1. This prevents navigating beyond available pages and
             * ensures a valid pagination state.
             */
            if (pagination.page > pagination.totalPages) pagination.page = 1
        }
    )
    // Watch `pagination.page` and `pagination.perPage`
    watch([() => pagination.page, () => pagination.perPage], () => {
        if (loading.value) return // Prevent double-fetch during loading
        if (disablePaginationWatcher.value) return // Prevent fetch if watchers are disabled
        loadRecords()
    })
    return {
        loading,
        error,
        currentItem,
        list,
        pagination,
        query,
        selectedStatus,
        disablePaginationWatcher,
        cached,
        updatePaginationSilently,
        resetList,
        fetch,
        fetchList,
        clearCache,
        loadRecords,
    }
})

export { useStore as default }
