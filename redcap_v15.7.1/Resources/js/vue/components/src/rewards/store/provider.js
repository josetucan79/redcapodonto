import { defineStore } from 'pinia'
import { ref } from 'vue'
import API from '../API'

const collection = 'provider'


const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const balance = ref(0)
    const error = ref()

    async function checkBalance() {
        try {
            loading.value = true
            const response = await API.checkBalance()
            balance.value = response.data
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    return {
        loading,
        error,
        balance,
        checkBalance,
    }
})

export { useStore as default }
