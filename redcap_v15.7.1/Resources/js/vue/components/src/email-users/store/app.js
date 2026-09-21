import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getSettings } from '../API'
import { useLang } from '@/directives/TranslateDirective'

const collection = 'app'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const ready = ref(false)
    const loading = ref(false)
    const error = ref()
    const settings = ref()
    const lang = ref()
    const user = ref()
    const variables = ref()
    const fieldsConfig = ref()

    async function init() {
        try {
            loading.value = true
            const response = await getSettings()
            const _settings = response?.data?.data
            const {
                lang:_lang = {},
                user: _user={},
                variables: _variables={},
                fieldsConfig: _fieldsConfig=[],
            } = _settings // these are the properties we are going to use

            settings.value = _settings
            lang.value = _lang
            user.value = _user
            variables.value = _variables
            fieldsConfig.value = _fieldsConfig
            useLang(lang.value) // load the translations
            ready.value = true
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    return {
        ready,
        loading,
        error,
        settings,
        lang,
        user,
        variables,
        fieldsConfig,
        init,
    }
})

export { useStore as default }
