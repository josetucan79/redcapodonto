import { createApp } from 'vue'
import App from './App.vue'
import useRouter from './router'
import store from './store'

// Vue UI utilities
import { BootstrapVue } from 'bootstrap-vue'
import 'bootstrap-vue/dist/style.css'

const init = (target) => {
    const app = createApp(App)
    app.use(BootstrapVue)
    const router = useRouter()
    app.use(router)
    app.mount(target)
    return { app, store, router }
}

export { init as default }
