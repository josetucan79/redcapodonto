import { defineStore } from 'pinia'
import { useAppStore, useSettingsStore } from '@/cdp-mapping/store'
import { useSettingsManagerService } from './'
import { useModal, useToaster } from 'bootstrap-vue'
import { computed, reactive, ref, toRef, toRefs, watch, watchEffect } from 'vue'
import { uuidv4, deepClone } from '@/utils'
import API from '@/cdp-mapping/API'
import useMappingFieldService from './dynamic-mapping-field-service'
import { ADJUDICATION_METHOD } from '@/cdp-mapping/constants'
import { translate, applyReplacements } from '@/directives/TranslateDirective'

/**
 * sort an array of objects based on properties
 * @param {Array} order array of property names defines the priority for sorting
 * @returns 1|-1|0
 */
const CreateMultiPropertySorter = (order = []) => {
    return (A, B) => {
        for (let prop of order) {
            if (A[prop] < B[prop]) return -1
            if (A[prop] > B[prop]) return 1
        }
        return 0 // If all properties are equal, return 0
    }
}

function createFhirFieldSorter(fhir_fields) {
    return (A, B) => {
        // Retrieve category and subcategory from fhir_fields for each element
        const categoryA =
            fhir_fields[A.external_source_field_name]?.category || ''
        const categoryB =
            fhir_fields[B.external_source_field_name]?.category || ''
        const subcategoryA =
            fhir_fields[A.external_source_field_name]?.subcategory || ''
        const subcategoryB =
            fhir_fields[B.external_source_field_name]?.subcategory || ''

        // Sort by category first
        if (categoryA < categoryB) return -1
        if (categoryA > categoryB) return 1

        // If categories are the same, sort by subcategory
        if (subcategoryA < subcategoryB) return -1
        if (subcategoryA > subcategoryB) return 1

        // If both category and subcategory are the same, sort by external_source_field_name
        if (A.external_source_field_name < B.external_source_field_name)
            return -1
        if (A.external_source_field_name > B.external_source_field_name)
            return 1

        return 0 // If all are equal
    }
}

const getDefaultFieldData = () => {
    return {
        _isDeleted: false,
        _isNew: true,
        map_id: uuidv4(),
        event_id: null,
        external_source_field_name: null,
        field_name: null,
        is_record_identifier: false,
        preselect: null,
        project_id: null,
        temporal_field: null,
    }
}

const useIncrementalID = () => {
    let id = 0
    return () => ++id
}

const useService = defineStore('mapping-service', () => {
    const modal = useModal()
    const toaster = useToaster()
    const appStore = useAppStore()
    const settingsStore = useSettingsStore()
    const settingsManagerService = useSettingsManagerService()
    const { mapping, fhir_fields } = toRefs(appStore?.settings)
    const incrementalID = useIncrementalID()

    // manage the record identifier mapping for the current arm
    const recordIdentifierStore = ref()

    const loading = ref(false)
    const error = ref()
    // list of stores for each mapping
    const stores = ref([])

    const registerEntry = (entry) => {
        const entryID = (entry._id = incrementalID())
        const storeID = `mapping-service-${entryID}`
        const store = useMappingFieldService(storeID, entry)
        return store
    }

    const insertMapping = (entry, index = undefined) => {
        if (typeof index === 'undefined') index = stores.value.length
        const registered = registerEntry(entry)
        stores.value.splice(index, 0, registered)
    }

    const createEntry = () => {
        const newEntry = getDefaultFieldData()
        insertMapping(newEntry)
    }

    const duplicateEntry = (entry, index) => {
        const clone = { ...entry, _isDeleted: false }
        clone.map_id = uuidv4() // override
        clone._isNew = true // override
        insertMapping(clone, index + 1)
    }

    const removeEntry = (entry) => {
        const index = stores.value.findIndex(
            (store) => store?.field?.map_id === entry?.map_id
        )
        const store = stores.value?.[index]
        if (!store) return
        if (store.isNew) {
            store.$dispose()
            stores.value.splice(index, 1)
        } else {
            store.markDeleted()
        }
    }

    const restoreEntry = (entry) => {
        const index = stores.value.findIndex(
            (store) => store?.field?.map_id === entry?.map_id
        )
        const store = stores.value?.[index]
        if (!store) return
        store.markDeleted(false)
    }

    async function exportMappings() {
        try {
            loading.value = true
            const response = await API.exportMappings()
            return response?.data?.download_url
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function importMappings(file) {
        try {
            loading.value = true
            const response = await API.importMappings(file)
            const imported = response?.data?.imported
            toaster.toast({
                title: 'Success',
                body: `Total imported mappings: ${imported?.length}`,
            })
            await settingsManagerService.getSettings()
            return imported
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    /**
     *
     * @param {Array} list
     * @param {Object} fhir_fields
     * @returns {Array}
     */
    const sortMapping = (list, fhir_fields) => {
        const sortFn = createFhirFieldSorter(fhir_fields)
        const sorted = list.sort(sortFn)
        return sorted
    }

    const allStores = computed(() => {
        const list = []
        if (recordIdentifierStore.value) list.push(recordIdentifierStore.value)
        list.push(...stores.value)
        return list
    })

    /**
     * normalize the record identifier store
     * @param {Object} entry
     * @returns store
     */
    const registerRecordIdentifierStore = (entry) => {
        const entryClone = deepClone(entry)
        const recordIdentifierFhirFields = appStore.recordIdentifierFields.map(item => item.field)
        entryClone.is_record_identifier = true
        entryClone.temporal_field = null
        if (!recordIdentifierFhirFields.includes(entryClone.external_source_field_name)) {
            entryClone.external_source_field_name = recordIdentifierFhirFields?.[0]
        }
        return registerEntry(entryClone)
    }

    const resetStores = () => {
        for (const [_, store] of allStores.value.entries()) {
            store.$dispose()
        }
        recordIdentifierStore.value = registerRecordIdentifierStore(getDefaultFieldData())
        stores.value = []
    }

    const isDirty = computed(() => {
        for (const [_, store] of allStores.value.entries()) {
            if (store.isDeleted) return true
            if (store.isDirty) return true
            if (store.isNew) return true
        }
        return false
    })

    const allTemporalFieldsSetForInstantAdjudication = computed(() => {
        for (const store of stores.value) {
            if (!store.isTemporal) continue
            const field = store?.field ?? {}
            if (!field?.preselect) return false
            if (!field?.temporal_field) return false
        }
        return true
    })

    const hasDuplicates = computed(() => {
        for (const [_, store] of allStores.value.entries()) {
            if (store.isDuplicate) return true
        }
        return false
    })

    async function save() {
        try {
            if (hasDuplicates.value) {
                modal.alert({title: 'Error', body: `You must remove duplicate entries before you can save.`})
                return
            }
            loading.value = true
            const adjudication_method = settingsStore?.adjudication_method
            if (
                adjudication_method !== ADJUDICATION_METHOD.MANUAL &&
                !allTemporalFieldsSetForInstantAdjudication.value
            ) {
                const proceed = await modal.confirm({
                    title: translate('incorrect_adjudication_strategy_warning_title'),
                    body: translate('incorrect_adjudication_strategy_warning_message'),
                    textCancel: 'Cancel',
                    textOk: 'Continue',
                })
                if (!proceed) {
                    return
                } else {
                    // set to manual
                    settingsStore.adjudication_method = ADJUDICATION_METHOD.MANUAL
                    await settingsManagerService.save()
                }
            }
            const recordIdentifierField = recordIdentifierStore.value?.field
            const mappings = [recordIdentifierField] // add the record identifier first
            for (const [_, store] of stores.value.entries()) {
                mappings.push({ ...store.field })
            }
            const response = await API.setMappings(mappings)
            return response
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function reset() {
        for (const [_, store] of allStores.value.entries()) {
            if (store.isDirty) store.reset()
            if (store.isDeleted) store.reset()
            if (store.isNew) removeEntry(store.field)
        }
    }

    /**
     * Sort mapping
     */
    watch(
        mapping,
        () => {
            try {
                // reset stores
                resetStores()
                // sort
                const sorted = sortMapping(mapping.value, fhir_fields.value)
                for (const entry of sorted) {
                    // skip record identifiers
                    if (entry?.is_record_identifier == true) {
                        recordIdentifierStore.value = registerRecordIdentifierStore(entry)
                        continue
                    }
                    entry._isNew = false
                    entry._isDeleted = false
                    const store = registerEntry(entry)
                    stores.value.push(store)
                }
            } catch (_error) {
                console.log(_error)
            }
        },
        { immediate: true }
    )

    watch(
        () => stores.value.map((store) => store.field), // Watch the 'field' property of each object
        () => {
            // Clear previous duplicates for each store
            stores.value.forEach((store) => (store.duplicates = []))
            // Track which objects have already been processed as duplicates
            const processed = new Set()

            for (let i = 0; i < stores.value.length; i++) {
                const storeA = stores.value[i]
                if (processed.has(storeA)) continue // Skip already processed objects
                for (let j = i + 1; j < stores.value.length; j++) {
                    const storeB = stores.value[j]
                    if (storeA.equals(storeB.field)) {
                        console.log('equales')
                        // Add each store as a duplicate to the other
                        storeA.duplicates.push(storeB)
                        storeB.duplicates.push(storeA)
                        // Mark storeB as processed to avoid re-checking it
                        processed.add(storeB)
                    }
                }
                // Mark storeA as processed after checking all duplicates
                processed.add(storeA)
            }
        },
        { deep: true }
    )

    // console.log(route, route.params, route.params?.arm_num)

    return {
        loading,
        error,
        stores,
        recordIdentifierStore,
        fhir_fields,
        isDirty,
        hasDuplicates,
        allTemporalFieldsSetForInstantAdjudication,
        registerEntry,
        createEntry,
        removeEntry,
        restoreEntry,
        duplicateEntry,
        save,
        reset,
        exportMappings,
        importMappings,
    }
})

export default useService
