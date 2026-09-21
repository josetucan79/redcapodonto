import { defineStore } from 'pinia'
import { computed, reactive, ref } from 'vue'
import { getMessages, deleteMessage } from '../API'

const collection = 'messages'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const list = ref([])
    const page = ref(1)
    const perPage = ref(10)
    const selected = ref()
    const metadata = reactive({})

    const total = computed(() => metadata?.total ?? 0)

    async function load() {
        try {
            loading.value = true
            const response = await getMessages(page.value, perPage.value)
            const _list = response?.data?.data
            Object.assign(metadata, response?.data?.metadata ?? {})
            list.value = _list
        } catch (_error) {
            list.value = []
            error.value = _error
        } finally {
            updateSelection()
            loading.value = false
        }
    }

    async function remove(id) {
        try {
            loading.value = true
            await deleteMessage(id)
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    function updateSelection() {
        if(!selected.value?.id) return
        const selectedID = selected.value.id
        const found = list.value.find(item => item.id == selectedID)
        selected.value = found
    }

    function selectMessage(message) {
        selected.value = message
    }

    return {
        loading,
        error,
        list,
        selected,
        metadata,
        page,
        perPage,
        total,
        load,
        remove,
        selectMessage,
    }
})

export { useStore as default }
