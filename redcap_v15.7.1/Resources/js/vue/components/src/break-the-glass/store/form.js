import { defineStore } from 'pinia'
import { nextTick, reactive, ref, watch } from 'vue'
import { useValidation, required } from '../../utils/useValidation'
import API from '../API'
import { debounce } from '../../utils'
import { usePatientsStore } from './' // Import the patients store

const collection = 'form'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const patientsStore = usePatientsStore() // Access patients data

    const validate = useValidation({
        mrns: [required({ message: `provide at leat one MRN` })],
        password: [required({ message: `'password' is required` })],
        UserID: [required({ message: `'UserID' is required` })],
        UserIDType: [required({ message: `'UserIDType' is required` })],
        Reason: [required({ message: `'Reason' is required` })],
        Explanation: [required({ message: `'Explanation' is required` })],
    })

    const validation = ref()
    const validationEnabled = ref(true)
    const loading = ref(false)
    const error = ref()
    const results = ref([])

    const formData = reactive({
        mrns: patientsStore.selected,
        password: undefined,
        // BTG specific params matching AcceptDTO
        UserID: undefined,
        UserIDType: undefined,
        Reason: undefined,
        Explanation: undefined,
    })

    const submit = async () => {
        try {
            results.value = []
            loading.value = true
            validation.value = validate(formData)
            if (validation.value.hasErrors()) return
            const response = await API.breakTheGlass({ ...formData })
            results.value = response.data
            patientsStore.resetSelection()
        } catch (_error) {
            error.value = _error
            return error
        } finally {
            loading.value = false
        }
    }

    const updatePropertyWithoutValidation = (key, value) => {
        validationEnabled.value = false // Disable validation temporarily

        // Update the property based on the key
        if (key in formData) {
            formData[key] = value
        } else {
            console.warn(`Invalid property key: ${key}`)
        }
        nextTick(() => {
            validationEnabled.value = true
        })
    }

    const runValidation = () => (validation.value = validate(formData))
    runValidation()

    const debounceValidation = debounce(runValidation, 300)

    watch(
        formData,
        (value, prev) => {
            if (!validationEnabled.value) return
            debounceValidation()
        },
        { immediate: true }
    )

    watch(
        () => patientsStore.selected,
        (value) => {
            formData.mrns = value
        },
        { immediate: true }
    )

    return {
        loading,
        error,
        validation,
        formData,
        results,
        runValidation,
        updatePropertyWithoutValidation,
        submit,
    }
})

export { useStore as default }
