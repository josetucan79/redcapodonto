import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import API from '../API'
import useRecordsStore from './records'
import useRewardOptionsStore from './rewardOptions'

const collection = 'review-selection'
const SELECTION_STATUS = 'approved'

const useStore = defineStore(collection, () => {
    const recordsStore = useRecordsStore()
    const rewardOptionsStore = useRewardOptionsStore()
    // const selected = ref({ 2: { 102: 'pending' } })
    const selected = ref([
        { reward_option_id: 2, record_id: 103, status: 'pending' },
        { reward_option_id: 2, record_id: 102, status: 'pending' },
    ])
    const loading = ref(false)
    const error = ref()

    const records = computed(() => recordsStore.list ?? [])

    const getRewardOptionRecordsByStatus = (reward_option_id, status) => {
        const list = []
        for (const record of Object.values(records.value)) {
            const _status = record?.reward_options?.[reward_option_id]?.status
            if (_status !== status) continue
            list.push = record
        }
        return list
    }

    const makeEntry = (reward_option_id, record_id, status = undefined) => {
        return {
            reward_option_id: `${reward_option_id}`,
            record_id: `${record_id}`,
            status: `${status}`,
        }
    }

    const toggle = (reward_option_id, record_id, status) => {
        const entry = makeEntry(reward_option_id, record_id, status)
        const index = findEntry(entry)
        if (index < 0) addEntry(entry)
        else removeEntry(entry)
    }

    const findEntry = (entry) => {
        const index = selected.value.findIndex((item) => {
            return (
                item.record_id == entry.record_id &&
                item.reward_option_id == entry.reward_option_id
            )
        })
        return index
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

    const getSelectedRecordsPerReward = (_reward_option_id) => {
        const list = []
        for (const entry of Object.values(selected.value)) {
            if (entry.reward_option_id != _reward_option_id) continue
            list.push(entry.record_id)
        }
        return list
    }

    const getSelectableRecordsPerReward = (reward_option_id) => {
        const selectableStatuses = ['pending', 'approved']
        const list = []
        for (const record of Object.values(records.value)) {
            const status = record?.reward_options?.[reward_option_id]?.status
            if (!selectableStatuses.includes(status)) continue
            list.push(`${record.record_id}`)
        }
        return list
    }

    const getRewardCheckboxState = (_reward_option_id) => {
        const selection = getSelectedRecordsPerReward(_reward_option_id)
        const selectable = getSelectableRecordsPerReward(_reward_option_id)
        const state = {
            length: selection.length,
            total: selectable.length,
            indeterminate:
                selection.length > 0 && selection.length != selectable.length,
            checked:
                selection.length > 0 && selectable.length === selection.length,
            disabled: selectable.length === 0,
        }
        return state
    }

    const checkSelection = (reward_option_id, record_id) => {
        let isSelected = false
        for (const entry of Object.values(selected.value)) {
            if (entry.reward_option_id != reward_option_id) continue
            if (entry.record_id != record_id) continue
            isSelected = true
        }
        return isSelected
    }

    const toggleGroup = (reward_option_id) => {
        let _selectable = getSelectableRecordsPerReward(reward_option_id)
        let _selected = getSelectedRecordsPerReward(reward_option_id)
        console.log(_selected, _selectable)
        const shouldCheck =
            _selected.length === 0 || _selected.length !== _selectable.length
        for (const record_id of _selectable) {
            const entry = makeEntry(reward_option_id, record_id)
            if (shouldCheck) {
                addEntry(entry)
            } else {
                removeEntry(entry)
            }
        }
    }

    return {
        loading,
        error,
        selected,
        toggle,
        getSelectedRecordsPerReward,
        toggleGroup,
        checkSelection,
        getRewardCheckboxState,
    }
})

export { useStore as default }
