import useSettingsStore from './settings'
import usePatientsStore from './patients'
import useFormStore from './form'

const storeRegistry = {
    settings: useSettingsStore,
    patients: usePatientsStore,
    form: useFormStore,
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
    useSettingsStore,
    usePatientsStore,
    useFormStore,
}
