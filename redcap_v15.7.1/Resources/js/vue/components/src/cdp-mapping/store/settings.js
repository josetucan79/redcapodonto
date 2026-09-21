import { defineStore } from 'pinia'
import { ref, reactive, watchEffect } from 'vue'
import API from '../API'
import { MIN_DAYS, ADJUDICATION_METHOD } from '@/cdp-mapping/constants'
import useArmNum from '@/cdp-mapping/utils/useRouteArmParam'

const collection = 'settings'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const arms = reactive({})
    const arm_num = useArmNum()
    const preview_fields = ref([])
    const days_plus_minus = ref()
    const days = ref(MIN_DAYS)
    const adjudication_method = ref()

    return {
        loading,
        error,
        arms,
        preview_fields,
        days_plus_minus,
        days,
        adjudication_method,
    }
})

export { useStore as default }
