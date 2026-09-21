import { defineStore } from 'pinia'
import { ref, reactive, computed } from 'vue'
import API from '../API'
import { PER_PAGE } from '../variables'

const collection = 'recordsMetadata'

const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const data = ref([])
    const metadata = reactive({})

    // extract metadata values
    const page = computed(() => metadata?.page ?? 0)
    const total = computed(() => metadata?.total ?? 0)
    const total_pages = computed(() => metadata?.total_pages ?? 0)
    const next_page = computed(() => metadata?.next_page ?? 0)
    const per_page = computed(() => metadata?.per_page ?? PER_PAGE)
    const total_current_page = computed(() => metadata?.total_current_page ?? 0)

    // total values ready to be adjudicated
    const totalValues = computed(() => {
        let total = 0
        for (const record of data.value) {
            total += parseInt(record?.total) ?? 0
        }
        return total
    })

    async function getRecords(page, perPage) {
        try {
            loading.value = true
            const response = await API.getRecords(page, perPage)
            const { data: _data, metadata: _metadata } = response?.data ?? {}
            data.value = _data
            Object.assign(metadata, _metadata)
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    return {
        loading,
        error,
        data,
        metadata,
        page,
        total,
        total_pages,
        next_page,
        totalValues,
        per_page,
        total_current_page,
        getRecords,
    }
})

export { useStore as default }
