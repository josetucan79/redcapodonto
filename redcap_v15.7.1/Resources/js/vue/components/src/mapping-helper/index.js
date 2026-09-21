import { createApp } from 'vue'
import { createStore } from '@/plugins/Store'
import App from './App.vue'
import useRouter from './router'

// Vue UI utilities
import { BootstrapVue } from 'bootstrap-vue'
import 'bootstrap-vue/dist/style.css'

const init = (target) => {
    const app = createApp(App)
    app.use(BootstrapVue)
    app.use(createStore())
    const router = useRouter()
    app.use(router)
    app.mount(target)
    return { app }
}

export { init as default }
