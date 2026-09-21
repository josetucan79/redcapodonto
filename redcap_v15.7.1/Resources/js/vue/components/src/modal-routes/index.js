import { createApp, inject } from 'vue'

import App from './App.vue'
import useRouter from './router'
// import { ModalRouter } from '../shared/ModalRouterView'


// Vue UI utilities
import { BootstrapVue } from 'bootstrap-vue'
import 'bootstrap-vue/dist/style.css'

const init = (target) => {
    const app = createApp(App)

    app.use(BootstrapVue)
    const router = useRouter()
    app.use(router)
    // app.use(ModalRouter)
    app.mount(target)

    return { app, router }
}

export { init as default }
