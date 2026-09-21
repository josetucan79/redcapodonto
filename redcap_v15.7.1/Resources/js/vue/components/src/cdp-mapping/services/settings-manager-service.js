import { defineStore } from 'pinia'
import {
    useAppStore,
    useSettingsStore,
    useMappingStore,
} from '@/cdp-mapping/store'
import { MIN_DAYS, ADJUDICATION_METHOD } from '@/cdp-mapping/constants'
import API from '@/cdp-mapping/API'
import { useModal, useToaster } from 'bootstrap-vue'
import { computed, reactive, ref, watch, watchEffect } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import useArmNum from '@/cdp-mapping/utils/useRouteArmParam'
import { deepCompare } from '@/utils'
import { useLang } from '@/directives/TranslateDirective'

const useService = defineStore('settings-service', () => {
    const loading = ref(false)
    const error = ref()

    const modal = useModal()
    const toaster = useToaster()
    const router = useRouter()
    const route = useRoute()
    const appStore = useAppStore()
    const settingsStore = useSettingsStore()
    const mappingStore = useMappingStore()
    const arm_num = useArmNum()
    const originalSettings = reactive({
        preview_fields: [],
        days: null,
        days_plus_minus: null,
        adjudication_method: null,
    })

    /**
     * compare the current setting
     * with the ones coming from the server
     */
    const isDirty = computed(() => {
        const fieldsA = { ...settings.value }
        const fieldsB = {
            preview_fields: [...(appStore?.settings?.preview_fields || [])],
            days: String(
                appStore?.settings?.project?.realtime_webservice_offset_days
            ),
            days_plus_minus:
                appStore?.settings?.project
                    ?.realtime_webservice_offset_plusminus,
            adjudication_method:
                appStore?.settings?.project?.adjudication_method,
        }
        return !deepCompare(fieldsA, fieldsB)
    })

    const recursiveSet = (object, params) => {
        for (const [key, value] of Object.entries(params)) {
            delete object[key]
            object[key] = value
        }
    }

    async function getSettings(record_id, order_id) {
        try {
            loading.value = true
            const response = await API.getSettings()
            const data = response?.data ?? {}
            recursiveSet(originalSettings, data)
            recursiveSet(appStore.settings, originalSettings)
            const { translations = {} } = data
            useLang(translations)
        } catch (_error) {
            appStore.error = _error
        } finally {
            loading.value = false
        }
    }

    const settings = computed(() => {
        return {
            preview_fields: [...settingsStore.preview_fields],
            days: String(settingsStore.days),
            days_plus_minus: settingsStore.days_plus_minus,
            adjudication_method: settingsStore.adjudication_method,
        }
    })

    function reset() {
        recursiveSet(appStore.settings, originalSettings)
    }
    async function save() {
        try {
            loading.value = true
            const response = await API.setSettings(settings.value)
            return response
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    /**
     * sync the settings with the data coming from the server
     */
    const syncSettings = () => {
        settingsStore.preview_fields = [...(appStore?.settings?.preview_fields ?? [])]
        settingsStore.days = parseFloat(
            appStore?.settings?.project?.realtime_webservice_offset_days ??
                MIN_DAYS
        )
        settingsStore.days_plus_minus =
            appStore?.settings?.project?.realtime_webservice_offset_plusminus

        settingsStore.adjudication_method =
            appStore?.settings?.project?.adjudication_method ??
            ADJUDICATION_METHOD.MANUAL
    }

    const syncMapping = () => {
        mappingStore.list = [...(appStore?.settings?.mapping ?? [])]
    }

    watch(
        () => appStore,
        (value, previous) => {
            syncSettings()
            syncMapping()
        },
        {
            immediate: true,
            deep: true,
        }
    )

    return {
        loading,
        isDirty,
        getSettings,
        reset,
        save,
    }
})

export default useService
