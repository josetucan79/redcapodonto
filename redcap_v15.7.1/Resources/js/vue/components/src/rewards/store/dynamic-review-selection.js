import { defineStore } from 'pinia'
import { computed, reactive, ref, watchEffect } from 'vue'
import useRecordsStore from './records'
import { ORDER_STATUS } from '@/rewards/variables'
import useArmNum from '@/rewards/utils/useRouteArmParam'

const collection = 'review-selection'

export const stores = reactive({})

const useStore = (arm_number, reward_option_id) => {
    const storeID = `${collection}-${arm_number}-${reward_option_id}`
    if(!stores?.[arm_number]) stores[arm_number] = {}
    if (stores[arm_number]?.[storeID]) {
        return stores[arm_number][storeID]
    }

    const store = defineStore(storeID, () => {
        const recordsStore = useRecordsStore()
        const selected = ref([])
        const loading = ref(false)
        const error = ref()
        const id = computed(() => reward_option_id)

        // make a mapped version of the records
        const records = computed(() => {
            const mapped = {}
            for (const record of recordsStore.list) {
                mapped[record.record_id] = record
            }
            return mapped
        })

        const selectionByStatus = computed(() => {
            const selection = {}
            for (const record_id of selected.value) {
                const status =
                    records.value?.[record_id]?.reward_options?.[
                        reward_option_id
                    ]?.status
                if (!(status in selection)) selection[status] = []
                selection[status].push(record_id)
            }
            return selection
        })

        const deselectByStatus = (status) => {
            selected.value = selected.value.filter((record_id) => {
                const recordStatus =
                    records.value?.[record_id]?.reward_options?.[
                        reward_option_id
                    ]?.status
                return recordStatus !== status
            })
        }

        const selectByStatus = (status) => {
            const recordsToSelect = records.value?.filter((record) => {
                const recordStatus =
                    record?.reward_options?.[reward_option_id]?.status
                return recordStatus === status
            })
            selected.value = [
                ...selected.value,
                ...recordsToSelect.map((record) => record.id),
            ]
        }

        const toggle = (record_id) => {
            const entry = `${record_id}`
            const index = findEntry(entry)
            if (index < 0) addEntry(entry)
            else removeEntry(entry)
        }

        const findEntry = (entry) => {
            return selected.value.findIndex((item) => item == entry)
        }

        const addEntry = (entry) => {
            const index = findEntry(entry)
            if (index >= 0) return
            selected.value.push(entry)
        }

        const removeEntry = (entry) => {
            const index = findEntry(entry)
            if (index < 0) return
            selected.value.splice(index, 1)
        }

        const selectableRecords = computed(() => {
            const selectableStatuses = [ORDER_STATUS.ELIGIBLE, ORDER_STATUS.REVIEWER_APPROVED]
            const list = []
            for (const record of Object.values(records.value)) {
                const status =
                    record?.reward_options?.[reward_option_id]?.status
                if (!selectableStatuses.includes(status)) continue
                list.push(`${record.record_id}`)
            }
            return list
        })

        const checkboxState = computed(() => {
            const selection = selected.value
            const selectable = selectableRecords.value
            return {
                length: selection.length,
                total: selectable.length,
                indeterminate:
                    selection.length > 0 &&
                    selection.length != selectable.length,
                checked:
                    selection.length > 0 &&
                    selectable.length === selection.length,
                disabled: selectable.length === 0,
            }
        })

        const checkSelection = (record_id) => {
            return findEntry(record_id) > -1
        }

        const toggleGroup = () => {
            let _selectable = selectableRecords.value
            let _selected = selected.value
            const shouldCheck =
                _selected.length === 0 ||
                _selected.length !== _selectable.length
            for (const record_id of _selectable) {
                const entry = record_id
                if (shouldCheck) {
                    addEntry(entry)
                } else {
                    removeEntry(entry)
                }
            }
        }

        return {
            id,
            loading,
            error,
            selected,
            selectionByStatus,
            checkboxState,
            deselectByStatus,
            selectByStatus,
            toggle,
            toggleGroup,
            checkSelection,
        }
    })()
    stores[arm_number][storeID] = store
    return store
}

export { useStore as default }

/**
 * this is a store that combines all stores for selections
 */
export const useSelectionStore =  defineStore(collection, () => {
    const arm_num = useArmNum()
    const selected = ref()
    const eligibleSelected = ref()
    const approvedSelected = ref()
    const arm_stores = computed(() => {
        return stores?.[arm_num.value] ?? {}
    })
    
    const delesectStatusInStores = (status) => {
        for (const [_, store] of Object.entries(arm_stores.value)) {
            store.deselectByStatus(status)
        }
    }
    
    // reset selection in all stores
    const reset = () => {
        for (const [_, _store] of Object.entries(arm_stores.value)) {
            _store.selected = []
        }
    }
    
    const updateSelected = () => {
        selected.value = []
        eligibleSelected.value = []
        approvedSelected.value = []

        for (const [_, store] of Object.entries(arm_stores.value)) {
            const reward_option_id = store.id
    
            const _eligibleSelected = (
                store.selectionByStatus?.[ORDER_STATUS.ELIGIBLE] ?? []
            ).map((record_id) => ({ [reward_option_id]: record_id }))
    
            const _approvedSelected = (
                store.selectionByStatus?.[ORDER_STATUS.REVIEWER_APPROVED] ?? []
            ).map((record_id) => ({ [reward_option_id]: record_id }))
    
            eligibleSelected.value.push(..._eligibleSelected)
            approvedSelected.value.push(..._approvedSelected)
            selected.value.push(..._eligibleSelected, ..._approvedSelected)
        }
    }
    // update selection by status whenever an update is detected
    watchEffect(() => updateSelected())
    
    return {
        arm_stores,
        selected,
        eligibleSelected,
        approvedSelected,
        delesectStatusInStores,
        reset,
        updateSelected,
    }
})
