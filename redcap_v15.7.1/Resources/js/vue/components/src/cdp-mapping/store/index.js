import { computed, ref, watch } from 'vue'
import useAppStore from './app'
import useSettingsStore from './settings'
import useMappingStore from './mapping'

// make a global object for errors from any store
const errors = ref([])

const storeRegistry = {
    app: useAppStore,
    settings: useSettingsStore,
    mapping: useMappingStore,
}

const useStore = () => {
    const stores = Object.fromEntries(
        Object.entries(storeRegistry).map(([key, storeFn]) => [key, storeFn()])
    )

    return {
        ...stores,
    }
}

export {
    useStore as default,
    useAppStore,
    useSettingsStore,
    useMappingStore,
    errors,
}
