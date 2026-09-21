import { defineStore } from 'pinia'
import { computed, reactive, ref, toRaw } from 'vue'
import { useLang } from '../../directives/TranslateDirective'
import { deepCompare } from '../../utils'
import { saveSettings } from '../API'

const collection = 'app-settings'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const _lang = reactive({})
    const breakTheGlassUserTypes = ref([]) // user types for the rbeak-the-glass feature
    const redcapConfig = ref({}) // original data as it comes from the server
    const newConfig = ref({}) // data modified by the user that will be sent if saved
    const redirectURL = ref('')

    const isDirty = computed(() => {
        const equal = deepCompare(redcapConfig.value, newConfig.value)
        if (equal) return false
        return true
    })

    const lang = computed({
        get: () => _lang.value,
        set: (value) => {
            useLang(value)
            _lang.value = value
        },
    })

    function reset() {
        newConfig.value = {...redcapConfig.value}
        // newConfig.value = { ...redcapConfig.value }
    }

    async function save() {
        try {
            if (isDirty.value) {
                loading.value = true
                const response = await saveSettings(this.newConfig)
                return response
            }
        } catch (_error) {
            console.log(_error)
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    return {
        loading,
        error,
        breakTheGlassUserTypes,
        redcapConfig,
        newConfig,
        redirectURL,
        isDirty,
        lang,
        reset,
        save,
    }
})

export { useStore as default }
