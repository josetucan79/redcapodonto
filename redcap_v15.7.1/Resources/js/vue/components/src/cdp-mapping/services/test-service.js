import { defineStore } from 'pinia'
import { useAppStore } from '@/cdp-mapping/store'
import { useModal, useToaster } from 'bootstrap-vue'
import { computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import useArmNum from '@/cdp-mapping/utils/useRouteArmParam'

const useService = defineStore('records-service', () => {
    const modal = useModal()
    const toaster = useToaster()
    const router = useRouter()
    const route = useRoute()
    const appStore = useAppStore()
    const arm_num = useArmNum()

    // console.log(route, route.params, route.params?.arm_num)

    return {}
})

export default useService
