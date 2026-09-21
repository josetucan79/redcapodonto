import { defineStore } from 'pinia'
import { ref, reactive, watchEffect } from 'vue'
import useArmNum from '@/cdp-mapping/utils/useRouteArmParam'

const collection = 'mapping'

/* this store manages mappings editing */
const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const arms = reactive({})
    const arm_num = useArmNum()
    const list = ref([])

    return {
        loading,
        error,
        arms,
        list,
    }
})

export { useStore as default }
