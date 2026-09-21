
import { createApp } from 'vue'
import App from './App.vue'

// Vue UI utilities
import { BootstrapVue } from 'bootstrap-vue'
import 'bootstrap-vue/dist/style.css'

const init = (target) => {
    const app = createApp(App)
    app.use(BootstrapVue)
    app.mount(target)
    return app
}

export { init as default }
