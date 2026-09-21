import { computed } from 'vue'
import { useAppStore } from '@/rewards/store'

class Gate {
    static allows(action) {
        const appStore = useAppStore()
        const permissions = computed(() => appStore.permissions)
        return permissions.value?.[action] ?? false
    }

    static denies(action) {
        return !Gate.allows(action)
    }
}

export default Gate
