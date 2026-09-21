import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { saveCustomMapping } from '../API'
import { deepCompare } from '../../utils'
import { convertToBoolean } from '../../utils'
import {
    useValidation,
    required,
    contains,
    isTrue,
    isFalse,
    firstError,
} from '../../utils/useValidation'

const allowedProperties = [
    'field',
    'temporal',
    'label',
    'description',
    'category',
    'subcategory',
    'identifier',
    'disabled',
    'disabled_reason',
]

export const useHeaders = () => [
    'field',
    'label',
    'description',
    'category',
    'subcategory',
    'temporal',
    'identifier',
    'disabled',
    'disabled_reason',
]

export const useSanitize = (allowedProperties = []) => {
    return (data) => {
        if (!data || typeof data !== 'object') return
        for (const [key, value] of Object.entries(data)) {
            if (allowedProperties.includes(key)) continue
            delete data[key]
        }
        return { ...data }
    }
}

export const normalize = (entry) => {
    const booleanKeys = ['temporal', 'identifier', 'disabled']
    for (const [key, value] of Object.entries(entry)) {
        if (booleanKeys.includes(key)) entry[key] = convertToBoolean(value)
    }
    return entry
}

export const sanitize = useSanitize(allowedProperties)

const collection = 'custom-mappings'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const validCategories = ref([])
    const originalList = ref([])
    const list = ref([])

    const isDirty = computed(() => {
        if (originalList.value.length !== list.value.length) return true

        for (const index in originalList.value) {
            const itemA = originalList.value[index]
            const itemB = list.value[index]
            const equal = deepCompare(itemA, itemB)
            if (!equal) return true
        }
        return false
    })

    function useDefaultEntry() {
        return {
            temporal: true,
            identifier: false,
            disabled: false,
        }
    }

    function reset() {
        list.value = []
        for (const item of originalList.value) {
            list.value.push({ ...item })
        }
    }
    

    function remove(item) {
        const _list = [...list.value]
        const index = _list.findIndex((element) => element === item)
        if (index < 0) return
        const found = _list[index] // keep track of the item that will be deleted
        _list.splice(index, 1)
        list.value = _list
    }

    function add(data) {
        list.value.push(data)
    }

    function edit(item, data) {
        const index = list.value.findIndex((element) => element === item)
        if (index < 0) return // item not found in the list of items
        const currentItem = list.value[index]
        // update each property of the item
        for (const [key, value] of Object.entries(data)) {
            currentItem[key] = value
        }
        list.value[index] = currentItem
    }

    // set the list of available FHIR systems and set the first one as current
    function init(data) {
        const _validCategories = data?.validCategories ?? []
        validCategories.value = [..._validCategories]
        const list = data?.list ?? []
        originalList.value = [...list]
        reset()
    }

    async function save() {
        try {
            loading.value = true
            const response = await saveCustomMapping(list.value)
            return response
        } catch (_error) {
            console.log(error)
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    function validate(entry) {
        const rules = {
            field: [required()],
            category: [
                firstError([required(), contains(validCategories.value)]),
            ],
            label: [required()],
            temporal: [
                isTrue({
                    message: `the 'temporal' field must be set to 'true'`,
                }),
            ],
            identifier: [
                isFalse({
                    message: `the 'identifier' field must be set to 'false'`,
                }),
            ],
        }
        const validate = useValidation(rules)

        const validation = validate(entry)
        return validation
    }

    return {
        loading,
        error,
        validCategories,
        originalList,
        list,
        isDirty,
        useDefaultEntry,
        reset,
        remove,
        add,
        edit,
        init,
        save,
        validate,
    }
})

export { useStore as default }