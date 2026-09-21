import { defineStore } from 'pinia'
import { ref, reactive, watchEffect } from 'vue'
import API from '../API'
import useArmNum from '@/rewards/utils/useRouteArmParam'

const collection = 'app'

const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const settings = reactive({})
    const arms = reactive({})
    const permissions = reactive({})
    const arm_num = useArmNum()

    const resetObject = (object) => {
        for (const key of Object.keys(object)) {
            delete object[key]
        }
    }

    const fillObject = (target, source) => {
        for (const [key, value] of Object.entries(source)) {
            target[key] = value
        }
    }

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
            const {
                settings: _settings,
                arms: _arms,
                permissions: _permissions,
            } = data

            recursiveSet(settings, _settings)
            recursiveSet(arms, _arms)
            recursiveSet(permissions, _permissions)
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function sendOrderEmail(record_id, reward_option_id, order_id) {
        try {
            loading.value = true
            const arm_number = arm_num?.value
            const response = await API.sendOrderEmail(
                arm_number,
                record_id,
                reward_option_id,
                order_id
            )
            const { data: _data, metadata: _metadata } = response?.data ?? {}
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    return {
        loading,
        error,
        settings,
        arms,
        permissions,
        getSettings,
        sendOrderEmail,
    }
})

export { useStore as default }
