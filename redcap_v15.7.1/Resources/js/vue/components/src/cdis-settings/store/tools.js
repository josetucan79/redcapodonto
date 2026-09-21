import { defineStore } from 'pinia'
import { ref } from 'vue'
import {getExpiredTokens, deleteExpiredTokens} from '../API'

const collection = 'tools'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const expiredTokens = ref([])

    async function fetchExpiredTokens() {
        try {
            loading.value = true
            const response = await getExpiredTokens()
            expiredTokens.value = response?.data?.expiredTokens ?? []
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function clearExpiredTokens(ehr_id) {
        try {
            loading.value = true
            const response = await deleteExpiredTokens(ehr_id)
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
        expiredTokens,
        fetchExpiredTokens,
        clearExpiredTokens,
    }
})

export { useStore as default }
