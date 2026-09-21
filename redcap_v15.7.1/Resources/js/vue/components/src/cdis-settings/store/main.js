import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import useAppSettingsStore from './app-settings'
import useFhirSystemStore from './fhir-system'
import useCustomMappingsStore from './custom-mappings'
import { getSettings } from '../API'

const collection = 'main'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const appSettingsStore = useAppSettingsStore()
    const fhirSystemStore = useFhirSystemStore()
    const customMappingsStore = useCustomMappingsStore()

    const ready = ref(false)
    const loading = ref(false)
    const error = ref()

    // check if any store has pending changes
    const savePending = computed(() => {
        return (
            appSettingsStore.isDirty ||
            fhirSystemStore.isDirty ||
            fhirSystemStore.orderChanged ||
            fhirSystemStore.listChanged ||
            customMappingsStore.isDirty
        )
    })

    async function init() {
        try {
            loading.value = true
            await loadSettings()
            ready.value = true
        } catch (error) {
            error.value = useError(error)
        } finally {
            loading.value = false
        }
    }

    async function loadSettings() {
        const response = await getSettings()
        const {
            lang,
            redcapConfig,
            redirectURL,
            breakTheGlassUserTypes,
            fhirSystems,
            customMappingsData,
        } = response.data
        // useLang(lang)
        appSettingsStore.lang = lang
        appSettingsStore.redirectURL = redirectURL
        appSettingsStore.redcapConfig = redcapConfig
        appSettingsStore.breakTheGlassUserTypes = breakTheGlassUserTypes
        appSettingsStore.newConfig = { ...redcapConfig }
        fhirSystemStore.init(fhirSystems)
        customMappingsStore.init(customMappingsData)
    }

    // reset changes in all stoers
    function resetChanges() {
        appSettingsStore.reset()
        fhirSystemStore.reset()
        customMappingsStore.reset()
    }

    async function saveChanges() {
        try {
            loading.value = true
            let reload = false
            if (appSettingsStore.isDirty) {
                await appSettingsStore.save()
                reload = true
            }
            if (fhirSystemStore.isDirty || fhirSystemStore.listChanged) {
                await fhirSystemStore.save()
                reload = true
            }
            if (fhirSystemStore.orderChanged) {
                await fhirSystemStore.updateOrder()
                reload = true
            }
            if (customMappingsStore.isDirty) {
                await customMappingsStore.save()
                reload = true
            }
            if (reload) await loadSettings()
        } catch (error) {
            console.log(error)
        } finally {
            loading.value = false
        }
    }

    return {
        ready,
        loading,
        error,
        savePending,
        init,
        loadSettings,
        resetChanges,
        saveChanges,
    }
})

export { useStore as default }
