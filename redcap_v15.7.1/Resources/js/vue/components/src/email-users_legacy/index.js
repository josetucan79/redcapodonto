import { createApp } from 'vue'
import { createStore } from '@/plugins/Store'
import App from './App.vue'
import { BootstrapVue } from 'bootstrap-vue'
import 'bootstrap-vue/dist/style.css'

import LocalizedText from './components/LocalizedText.vue'
import { useSettingsStore } from './store'

const init = (target) => {
    const app = createApp(App)
    app.use(createStore())
    app.use(BootstrapVue)
    // const settingsStore = useSettingsStore()
    // app.config.globalProperties.$tt = settingsStore.translate // provide access to the translate method globally
    app.component('tt-text', LocalizedText)
    app.mount(target)
    return app
}

export { init as default }
