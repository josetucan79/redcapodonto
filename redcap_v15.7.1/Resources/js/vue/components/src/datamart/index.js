import { createApp, inject } from 'vue'
import { createStore } from '@/plugins/Store'
import App from './App.vue'
import useRouter from './router'

// Vue UI utilities
import { BootstrapVue } from 'bootstrap-vue'
import 'bootstrap-vue/dist/style.css'
import { useStore } from './store'

const init = (target) => {
    const app = createApp(App)
    app.use(BootstrapVue)
    const router = useRouter()
    app.use(router)
    app.use(createStore())
    app.mount(target)
    const store = app.runWithContext(() => {
        // store must be obtained here or inject in the plugin will not work
        return useStore()
    })

    return { app, router, store }
}

export { init as default }
