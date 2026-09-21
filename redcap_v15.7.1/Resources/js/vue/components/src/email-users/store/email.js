import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { sendEmails } from '../API'

const collection = 'email'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const ready = ref(false)
    const loading = ref(false)
    const error = ref()
    const from = ref('')
    const subject = ref('')
    const body = ref('')
    const fromName = ref(null)
    const cc = ref(null)
    const bcc = ref(null)

    const previewAvailable = computed(() => Boolean(subject.value?.trim() || body.value?.trim()))

    const send = async (query) => {
        try {

            loading.value = true
            const response = await sendEmails({
                from: from.value,
                subject: subject.value,
                body: body.value,
                query: query,
                fromName: fromName.value,
                cc: cc.value,
                bcc: bcc.value,
            })
            return response.data
        } catch (_error) {
            error.value = _error
            return false
        } finally {
            loading.value = false
        }
    }

    return {
        ready,
        loading,
        error,
        from,
        fromName,
        subject,
        body,
        cc,
        bcc,
        previewAvailable,
        send,
    }
})

export { useStore as default }
