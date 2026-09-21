import { createApp } from 'vue'
import App from './App.vue'
import { createPinia } from 'pinia'
import {
    exposeStoresPlugin,
    getExposedStores,
    errorsManager,
} from '@/utils/store/plugins'

import { useEventBus, eventBus } from '../plugins/EventBus'

// Vue UI utilities
import { BootstrapVue } from 'bootstrap-vue'
import 'bootstrap-vue/dist/style.css'

const init = (target) => {
    const app = createApp(App)
    // store
    const pinia = createPinia()
    pinia.use(exposeStoresPlugin)
    pinia.use(errorsManager)
    app.use(pinia)
    // components
    app.use(BootstrapVue)
    // Eventtarget
    app.use(useEventBus())
    app.mount(target)
    const store = app.runWithContext(() => {
        // store must be obtained here or inject in the plugin will not work
        // run the store so the logic for errors is initialized
        return getExposedStores()
    })

    return { app, store, eventBus }
}

export { init as default }
