import { defineStore } from 'pinia'
import { ref, reactive, computed } from 'vue'
import API from '../API'
import { PER_PAGE } from '../variables'
import { useRecordsMetadataStore } from '.'

const collection = 'adjudication'

const useStore = defineStore(collection, () => {
    const recordsMetadataStore = useRecordsMetadataStore()
    const loading = ref(false)
    const processing = ref(false)
    const showProcessingModal = ref(false)
    const showSummaryModal = ref(false)
    const error = ref()

    const results = ref([])
    const errors = ref([])
    const totalFields = ref(0)
    const totalValues = ref(0)
    const stop = ref(false) // flag to stop a running process
    const currentField = ref()
    const stats = reactive({
        total_fields: 0,
        total_values: 0,
        processed_fields: 0,
        successful_fields: 0,
        errors: 0,
        adjudicated_values: 0,
        excluded_values: 0,
        unprocessed_values: 0,
    })

    const checkErrors = (field, result) => {
        const error = result?.error
        if (!error) return
        errors.value.push({
            label: `${field?.record}-${field?.event_id}-${field?.field_name}`,
            field: field,
            error: error,
        })
    }

    const addResult = (field, result) => {
        results.value.push({
            ...result,
            total_values: field.total ?? 0,
        })
    }

    const resetStats = () => {
        const fields = [...(recordsMetadataStore?.data ?? [])]
        stats.total_fields = fields.length ?? 0
        stats.total_values = 0
        fields.forEach(field => {
            stats.total_values += field?.total ?? 0
        })
        stats.processed_fields = 0
        stats.successful_fields = 0
        stats.errors = 0
        stats.adjudicated_values = 0
        stats.excluded_values = 0
        stats.unprocessed_values = 0
    }

    const calcStats = (field, result) => {
        const error = result?.error ?? null
        stats.processed_fields += 1
        stats.successful_fields += error ? 0 : 1
        stats.errors += error ? 1 : 0
        stats.adjudicated_values += result?.adjudicated ?? 0
        stats.excluded_values += result?.excluded ?? 0
        stats.unprocessed_values += error ? field?.total ?? 0 : 0
    }

    const reset = () => {
        resetStats()
        stop.value = false
        results.value = []
        errors.value = []
        totalFields.value = 0
        totalValues.value = 0
    }

    const process = async () => {
        try {
            // reset results
            reset()
            showSummaryModal.value = false
            showProcessingModal.value = true
            processing.value = true
            const fields = recordsMetadataStore.data
            totalFields.value = fields.length
            totalValues.value = recordsMetadataStore.totalValues

            for (const field of fields) {
                if (stop.value) break
                currentField.value = field
                const response = await API.processField(field)
                // Attach field's total to the response for error handling
                const result = response.data
                checkErrors(field, result)
                addResult(field, result)
                calcStats(field, result)
            }
            currentField.value = null
        } catch (_error) {
            console.log(_error)
            error.value = _error
        } finally {
            processing.value = false
            showProcessingModal.value = false
            showSummaryModal.value = true
        }
    }

    const stopProcess = async () => {
        stop.value = true
    }

    const schedule = async (sendMessage = false) => {
        try {
            loading.value = true
            const background = true
            const response = await API.processCachedData(background, sendMessage)
            return response.data
        } catch (_error) {
            console.log(_error)
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    return {
        processing,
        error,
        showProcessingModal,
        showSummaryModal,
        currentField,
        results,
        totalFields,
        totalValues,
        errors,
        stats,
        process,
        stopProcess,
        schedule,
    }
})

export { useStore as default }
