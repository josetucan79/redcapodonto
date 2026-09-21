import { defineStore } from 'pinia'
import { computed, reactive, ref } from 'vue'
import API from '../API'
import { useLang } from '@/directives/TranslateDirective'

const collection = 'settings'

const defaultLegalMessage = `The patient file you are attempting to access is restricted. If you have a clinical/business need to access the patient's file, please enter a reason and password and you may proceed.`

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const settings = reactive({}) // all settings

    async function getSettings() {
        try {
            loading.value = true
            const response = await API.getSettings()
            const _settings = response?.data ?? {}
            const { translations = {} } = _settings
            useLang(translations)
            Object.assign(settings, _settings)
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    const initialize = computed(() => settings?.initialize ?? null)
    const LegalMessage = computed(
        () => settings?.initialize?.LegalMessage ?? defaultLegalMessage
    )
    const Reasons = computed(() => settings?.initialize?.Reasons ?? [])
    const ehrUser = computed(() => settings.ehrUser)
    const preferredUserType = computed(() => settings.userType)
    const userTypes = computed(() =>
        Array.isArray(settings.userTypes) ? settings.userTypes : []
    )

    return {
        loading,
        error,
        settings,
        ehrUser,
        preferredUserType,
        userTypes,
        LegalMessage,
        Reasons,
        initialize,
        getSettings,
    }
})

export { useStore as default }
