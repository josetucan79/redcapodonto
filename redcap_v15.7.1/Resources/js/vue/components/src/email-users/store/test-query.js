import { defineStore } from 'pinia'
import { computed, reactive, ref } from 'vue'
import { testQuery } from '../API'

const collection = 'test-query'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const list = ref([])
    const metadata = reactive({})
    const page = ref(1)
    const perPage = ref(50)

    const totalUsers = computed(() => metadata?.total ?? 0)

    async function test(query) {
        try {
            loading.value = true
            const response = await testQuery(query, page.value, perPage.value)
            list.value = response?.data?.data
            Object.assign(metadata, response?.data?.metadata ?? {})
            return true
        } catch (_error) {
            reset()
            error.value = _error
            return false
        } finally {
            loading.value = false
        }
    }

    function reset() {
        page.value = 1
        list.value = []
        Object.assign(metadata, {})
    }

    return {
        loading,
        error,
        list,
        metadata,
        page,
        perPage,
        totalUsers,
        test,
        reset,
    }
})

export { useStore as default }
