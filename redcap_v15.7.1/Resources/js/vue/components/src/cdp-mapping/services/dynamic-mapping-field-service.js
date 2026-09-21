import { defineStore } from 'pinia'
import { computed, reactive, ref, toRefs, watch } from 'vue'
import { useAppStore } from '@/cdp-mapping/store'
import { deepCompare, deepClone, stripHTML } from '@/utils'

export const FHIR_ENTRY_TYPE = Object.freeze({
    STANDARD: 'STANDARD',
    TEMPORAL: 'TEMPORAL',
    IDENTIFIER: 'IDENTIFIER',
})

// a reference to all dynamic stores that have been created
// export const stores = reactive({})

const useMappingFieldService = (storeID, _field) => {
    const appStore = useAppStore()
    const originalField = _field

    // const storeID = `mapping-service-${originalField?.map_id}`
    // if (stores?.[storeID]) return stores[storeID]
    // if (stores?.[storeID]) delete stores[storeID]

    const store = defineStore(storeID, () => {
        const _id = ref(storeID)
        const loading = ref(false)
        const error = ref()
        const duplicates = ref([])
        /* { 
            "map_id": "6186", 
            "external_source_field_name": "id", 
            "is_record_identifier": "1", 
            "project_id": "98", 
            "event_id": "166", 
            "field_name": "first_name", 
            "temporal_field": null,
            "preselect": null,
            "_isNew": false
            "_isDeleted": false
        } */
        const field = reactive(deepClone(originalField)) // original field

        const reset = () => {
            const clone = deepClone(originalField)
            Object.assign(field, clone)
            return clone
        }

        // extract data from the settings
        const { fhir_fields, project_temporal_fields: temporal_fields } =
            toRefs(appStore.settings ?? {})
        const {
            events,
            metadata: projectMetadata,
            events_forms,
            arms,
            forms,
        } = toRefs(appStore.settings.project)

        const equals = (otherData) => {
            const current = {
                external_source_field_name: field?.external_source_field_name,
                event_id: field?.event_id,
                field_name: field?.field_name,
                temporal_field: field?.temporal_field,
                preselect: field?.preselect,
            }
            const other = {
                external_source_field_name:
                    otherData?.external_source_field_name,
                event_id: otherData?.event_id,
                field_name: otherData?.field_name,
                temporal_field: otherData?.temporal_field,
                preselect: otherData?.preselect,
            }
            return deepCompare(current, other)
        }

        /**
         * compare the current mapping
         * with the original one
         */
        const isDirty = computed(() => {
            const isEqual = equals(originalField)
            return !isEqual
        })

        const isNew = computed(() => field?._isNew === true)
        const isDeleted = computed(() => field?._isDeleted ?? false)
        const markDeleted = (deleted = true) => {
            field._isDeleted = deleted
        }
        const isDuplicate = computed(() => duplicates.value.length > 0)

        const isTemporal = computed(() => {
            const fhir_field = field?.external_source_field_name
            if (!fhir_field) return false
            return fhir_fields.value?.[fhir_field]?.temporal ?? false
        })

        // a composite object with metadata for the fhir fields
        const externalSourceFieldData = computed(() => {
            const mapping_id = field.external_source_field_name
            const metadata = fhir_fields.value?.[mapping_id] ?? {}
            const label = metadata?.label ?? ''
            const description = metadata?.description ?? ''

            return {
                mapping_id: mapping_id,
                label: metadata?.label ?? '',
                description: label != description ? description : null,
                metadata: metadata,
                type: getFhirFieldType(metadata),
            }
        })

        // a composite object with metadata for the REDCap fields
        const redcapFieldData = computed(() => {
            return getFieldData(projectMetadata.value, field.field_name)
        })

        // a composite object with metadata for the temporal fields
        const temporalFieldData = computed(() => {
            return getFieldData(projectMetadata.value, field.temporal_field)
        })

        /**
         * remove some type of field types and the form_complete ones
         * @param {Object} formFields 
         * @returns 
         */
        const filterMappableFields = (formFields) => {
            const invalidTypes = ['descriptive', 'checkbox', 'file']
            const clone = deepClone(formFields)
            for (const [form_name, form_data] of Object.entries(clone)) {
                const formCompleteRE = new RegExp(`^${form_name}_complete$`,'i')
                for (const field_name of Object.keys(form_data?.fields ?? {})) {
                    const field = projectMetadata.value?.[field_name]
                    const fieldType = field?.element_type
                    const fieldName = field?.field_name ?? ''
                    if (
                        invalidTypes.includes(fieldType) ||
                        fieldName.match(formCompleteRE)
                    ) {
                        delete clone[form_name].fields[field_name]
                    }
                }
            }
            return clone
        }

        // get the list of forms that are part of the currently selected event_id
        const eventFields = computed(() => {
            const _events_forms = events_forms?.value ?? {}
            const _forms = forms?.value ?? {}
            const formsList = [...(_events_forms?.[field?.event_id] ?? [])]
            let filtered = filterObjectByKey(_forms, formsList)
            return filterMappableFields(filtered)
        })

        const eventFieldsTemporal = computed(() => {
            const formsWithFieldsForEventID = deepClone(eventFields.value)
            const temporal_fields_names = temporal_fields.value.map(
                (_field) => _field.field_name
            )
            for (const [form_name, form_metdata] of Object.entries(
                formsWithFieldsForEventID
            )) {
                for (const [field_name, field_label] of Object.entries(
                    form_metdata?.fields
                )) {
                    if (!temporal_fields_names.includes(field_name))
                        delete form_metdata?.fields?.[field_name]
                }
            }
            return filterMappableFields(formsWithFieldsForEventID)
        })

        /**
         * deselect REDCap field if not in current event ID
         */
        watch(
            () => field?.event_id,
            () => {
                const formsForCurrentEventId = Object.keys(
                    eventFields.value ?? []
                )
                const formForCurrentField =
                    projectMetadata?.value?.[field.field_name]?.form_name
                // check if the form of the current field is included in the forms for the current Event ID
                if (formsForCurrentEventId.includes(formForCurrentField)) return
                else field.field_name = null // deselect
            },
            { immediate: true }
        )

        /**
         * clear temporal data if the selected fhir resource id is not temporal
         */
        watch(
            () => field.external_source_field_name,
            () => {
                if (!isTemporal.value) {
                    field.temporal_field = null
                    field.preselect = null
                }
            },
            { immediate: true }
        )

        return {
            _id,
            error,
            loading,
            field, // the mapping entry
            duplicates,
            // helpers
            externalSourceFieldData,
            redcapFieldData,
            temporalFieldData,
            isDirty,
            isNew,
            isDeleted,
            isTemporal,
            isDuplicate,
            // read-only
            projectMetadata,
            temporal_fields,
            eventFields,
            eventFieldsTemporal,
            // methods
            equals,
            reset,
            markDeleted,
        }
    })()
    // stores[storeID] = store
    return store
}

const getFieldData = (projectMetadata, _field_name) => {
    const field = projectMetadata?.[_field_name]
    return {
        name: field?.field_name,
        label: stripHTML(field?.element_label ?? ''),
        form: String(field?.form_menu_description ?? field?.form_name ?? ''),
    }
}

const getFhirFieldType = (metadata) => {
    if (metadata?.identifier) return FHIR_ENTRY_TYPE.IDENTIFIER
    else if (metadata.temporal) return FHIR_ENTRY_TYPE.TEMPORAL
    else return FHIR_ENTRY_TYPE.STANDARD
}

function filterObjectByKey(obj, keysToKeep) {
    return Object.keys(obj).reduce((filteredObj, key) => {
        if (keysToKeep.includes(key)) {
            filteredObj[key] = obj[key]
        }
        return deepClone(filteredObj)
    }, {})
}

export default useMappingFieldService
