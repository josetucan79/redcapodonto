import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import API from '../API'

const collection = 'patients'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const patients = ref([]) // all settings
    const selected = ref([]) // selected patients' MRNs

    async function fetchProtectedPatients() {
        try {
            loading.value = true
            const response = await API.getProtectedMrnList()
            const payload = response?.data ?? {}
            const list = payload?.data ?? []
            patients.value = list
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function removeMrn(mrn) {
        try {
            loading.value = true
            const response = await API.removeMrn(mrn)
            // Remove MRN from patients
            patients.value = patients.value.filter(p => p !== mrn)
            // Also update selected MRNs
            selected.value = selected.value.filter(s => s !== mrn)
            return response
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    function resetSelection() {
        selected.value = []
    }

    function toggleSelectMrn(mrn) {
        if (selected.value.includes(mrn)) {
            selected.value = selected.value.filter(s => s !== mrn)
        } else {
            selected.value.push(mrn)
        }
    }


    return {
        loading,
        error,
        patients,
        selected,
        resetSelection,
        fetchProtectedPatients,
        removeMrn,
        toggleSelectMrn,
    }
})

export { useStore as default }
