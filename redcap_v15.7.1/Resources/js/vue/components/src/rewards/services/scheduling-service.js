import { ref } from 'vue'
import { defineStore } from 'pinia'
import { useSelectionStore, useRecordsStore } from '@/rewards/store'
import { useModal, useToaster } from 'bootstrap-vue'
import useArmNum from '@/rewards/utils/useRouteArmParam'
import API from '@/rewards/API'

export default defineStore('scheduling-service', () => {
    const modal = useModal()
    const toaster = useToaster()
    const selectionStore = useSelectionStore()
    const recordsStore = useRecordsStore()
    const loading = ref(false)
    const error = ref()
    const armNum = useArmNum()

    const showConfirmDialog = async (text = 'Are you sure?') => {
        const confirmed = await modal.confirm({
            title: 'Confirm',
            body: text,
        })
        return confirmed
    }

    const schedule = async (action, reward_record_pairs) => {
        try {
            error.value = null
            loading.value = true
            console.log(action, 'armNum', armNum?.value, reward_record_pairs, API)
            await API.scheduleAction(action, reward_record_pairs, armNum?.value)
            toaster.toast({
                title: 'Success',
                body: `The selected items have been scheduled for '${action}'.`,
            })
        } catch (_error) {
            error.value = _error
            console.error(_error)
        } finally {
            loading.value = false
            selectionStore.reset()
            recordsStore.loadRecords()
        }
    }
    return {
        loading,
        error,
        schedule,
    }
})
