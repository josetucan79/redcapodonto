import { createApp } from 'vue'
import { createStore } from '@/plugins/Store'
import App from './App.vue'
import NotificationBadge from '@/parcel/components/NotificationBadge.vue'
import { default as router, init as initRouter } from '@/parcel/router/index.js'
import { BootstrapVue } from 'bootstrap-vue'

const init = async (target) => {
    initRouter()
    const app = createApp(App)
    app.use(createStore())
    app.use(BootstrapVue)
    app.use(router)
    app.mount(target)
    return app
}

const initBadge = async (target) => {
    const app = createApp(NotificationBadge)
    app.use(createStore())
    app.use(BootstrapVue)
    app.mount(target)
    return app
}

export { init as default, initBadge }
