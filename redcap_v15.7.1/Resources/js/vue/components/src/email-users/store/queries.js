import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getQueries, saveQuery, deleteQuery } from '../API'

const collection = 'queries'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const list = ref([])
    const selected = ref()

    async function load() {
        try {
            loading.value = true
            const response = await getQueries()
            const _queries = response?.data?.data
            list.value = _queries
        } catch (_error) {
            list.value = []
            error.value = _error
        } finally {
            updateSelection()
            loading.value = false
        }
    }

    function updateSelection() {
        if(!selected.value?.id) return
        const selectedID = selected.value.id
        const found = list.value.find(item => item.id == selectedID)
        selected.value = found
    }

    async function save(data) {
        try {
            loading.value = true
            await saveQuery(data)
        } catch (_error) {
            console.log(_error)
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function remove(id) {
        try {
            loading.value = true
            await deleteQuery(id)
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    function getQuery(id) {
        return list.value.find(entry => entry?.id == id)
    }

    function selectQuery(query) {
        selected.value = query
    }

    return {
        loading,
        error,
        list,
        selected,
        load,
        save,
        remove,
        selectQuery,
        getQuery,
    }
})

export { useStore as default }
