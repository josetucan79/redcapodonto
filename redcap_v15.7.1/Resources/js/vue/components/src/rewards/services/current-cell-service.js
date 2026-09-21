// useCurrentCellStore.js
import { defineStore } from 'pinia'
import { ref, reactive, computed, toRefs } from 'vue'
import { ACTION_EVENT, ENABLED_STATUS_LIST } from '@/rewards/variables'
import Gate from '@/rewards/utils/Gate'
import API from '@/rewards/API'
import { useToaster } from 'bootstrap-vue'
import { useRecordsStore, useRewardOptionsStore } from '@/rewards/store'
import { resetObject } from '@/utils'

const useService = defineStore('currentCellStore', () => {
    const toaster = useToaster()
    const recordsStore = useRecordsStore()
    const rewardOptionsStore = useRewardOptionsStore()

    // Reactive state
    const loading = ref(false)
    const error = ref(null)
    const record = reactive({})
    const reward_option = reactive({})
    const comment = ref('')

    // Computed values
    const data = computed(
        () => record?.reward_options?.[reward_option?.reward_option_id] ?? {}
    )
    const status = computed(() => data.value.status ?? null)
    const orders = computed(() => data.value.orders ?? [])
    const orderIndex = ref(-1) // default: last order
    const currentOrder = computed(() => data.value.orders?.at(orderIndex.value))
    const { eligibility_logic } = currentOrder.value || {}

    const orderAvailable = computed(() =>
        ENABLED_STATUS_LIST.includes(status.value)
    )

    // Methods
    function resetData() {
        comment.value = ''
    }

    function update(newRecord, newRewardOption) {
        resetObject(record, newRecord)
        resetObject(reward_option, newRewardOption)
        resetData()
    }

    function canPerformAction(action) {
        switch (action) {
            case ACTION_EVENT.REVIEWER_APPROVAL:
            case ACTION_EVENT.REVIEWER_REJECTION:
            case ACTION_EVENT.REVIEWER_RESTORE:
                return Gate.allows('review_eligibility')
            case ACTION_EVENT.BUYER_APPROVAL:
            case ACTION_EVENT.BUYER_REJECTION:
            case ACTION_EVENT.PLACE_ORDER:
                return Gate.allows('place_orders')
            case ACTION_EVENT.SEND_EMAIL:
            case ACTION_EVENT.REVERT:
                return (
                    Gate.allows('review_eligibility') ||
                    Gate.allows('place_orders')
                )
            default:
                return false
        }
    }

    async function performAction(action, {
            success = undefined,
            error:_error = undefined
        }={}) {
        try {
            const reward_option_id = reward_option?.reward_option_id
            const record_id = record?.record_id
            loading.value = true
            const response = await API.performAction(
                action,
                record_id,
                reward_option_id,
                record?.arm_number,
                comment.value
            )

            toaster.toast({
                title: 'Success',
                body: success ?? `'${action}' performed`,
            })

            
            // update the data in the store
            
            return response
        } catch (_error) {
            error.value = _error
            if(_error) {
                toaster.toast({
                    title: 'Error',
                    body: _error,
                })
            }
        } finally {
            // Reload data after action
            await recordsStore.loadRecords()
            reloadAndUpdateCurrentCell()
            loading.value = false
        }
    }

    async function reloadAndUpdateCurrentCell() {
        const record_id = record.record_id
        const reward_option_id = reward_option.reward_option_id

        // 1. Find the updated record and reward_option in the newly loaded data
        const updatedRecord = recordsStore.list.find(
            (r) => r.record_id === record_id
        )
        const updatedRewardOption = rewardOptionsStore.list.find(
            (r) => r.reward_option_id === reward_option_id
        )
        // 2. If we have a currently selected cell, update the currentCellStore with the fresh data
        if (updatedRecord && updatedRewardOption) {
            update(updatedRecord, updatedRewardOption)
        } else {
            // If the current cell no longer exists or is invalid
            update({}, {})
        }
    }

    return {
        error,
        loading,
        comment,
        record,
        reward_option,
        data,
        currentOrder,
        status,
        orders,
        orderAvailable,
        eligibility_logic,
        update,
        canPerformAction,
        performAction,
    }
})

export default useService
