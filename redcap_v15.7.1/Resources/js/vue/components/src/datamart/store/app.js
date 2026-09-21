import { reactive } from 'vue'
import { getSettings } from '../API'
import { useError } from '../../utils/ApiClient'
// stores
import { useUserStore, useSettingsStore, useRevisionsStore } from './'

export default () => {
    const userStore = useUserStore()
    const settingsStore = useSettingsStore()
    const revisionsStore = useRevisionsStore()



    const store = reactive({
        ready: false, // set to true after first time the settings are loaded
        loading: false,
        error: null,
        async init() {
            try {
                this.loading = true
                await this.loadSettings()
                this.ready = true
            } catch (error) {
                this.error = useError(error)
            } finally {
                this.loading = false
            }
        },
        async loadSettings() {
            // apply keys and values from source that match existing keys in target
            const applyExistingKeys = (source, target) => {
                for (const [key, value] of Object.entries(source)) {
                    if (key in target) target[key] = value
                }
            }
            const response = await getSettings()
            const { app_settings, fhir_metadata, revisions, user } = response.data
            applyExistingKeys(app_settings, settingsStore)
            settingsStore.fhirMetadata = fhir_metadata
            applyExistingKeys(user, userStore)
            revisionsStore.setList(revisions)
        },
    })
    return store
}
