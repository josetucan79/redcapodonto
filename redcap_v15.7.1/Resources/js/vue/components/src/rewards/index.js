import { createApp, inject } from 'vue'
import { createStore } from '@/plugins/Store'
import App from './App.vue'
import useRouter from './router'
import { createPinia } from 'pinia'
import {
    resetSelectionOnPaginationChange,
    exposeStoresPlugin,
    getExposedStores,
} from './store/plugins/'
import { TooltipDirective, DraggableDirective } from '../directives'
import { errorsManager } from '../utils/store/plugins/errorsManager'


// Vue UI utilities
import { BootstrapVue } from 'bootstrap-vue'
import 'bootstrap-vue/dist/style.css'

const init = (target) => {
    const app = createApp(App)
    // app.config.errorHandler = (err, instance, info) => {
    //     console.log(err, instance, info)
    // }
    app.directive('mytooltip', TooltipDirective)
    app.directive('draggable', DraggableDirective)
    const pinia = createPinia()
    pinia.use(resetSelectionOnPaginationChange)
    pinia.use(exposeStoresPlugin)
    pinia.use(errorsManager)
    app.use(pinia)
    app.use(BootstrapVue)
    const router = useRouter()
    app.use(router)
    app.use(createStore())
    app.mount(target)
    const store = app.runWithContext(() => {
        // store must be obtained here or inject in the plugin will not work
        // run the store so the logic for errors is initialized
        return getExposedStores()
    })

    return { app, router, store }
}

export { init as default }
