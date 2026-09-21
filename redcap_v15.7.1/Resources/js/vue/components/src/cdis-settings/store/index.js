import { default as useMainStore } from './main'
import { default as useAppSettingsStore } from './app-settings'
import { default as useCustomMappingsStore } from './custom-mappings'
import { default as useFhirSystemStore } from './fhir-system'
import { default as useToolsStore } from './tools'

const storeRegistry = {
    app_settings: useAppSettingsStore,
    main: useMainStore,
    tools: useToolsStore,
    custom_mappings: useCustomMappingsStore,
    fhir_system: useFhirSystemStore,
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
    useMainStore,
    useAppSettingsStore,
    useToolsStore,
    useCustomMappingsStore,
    useFhirSystemStore,
}