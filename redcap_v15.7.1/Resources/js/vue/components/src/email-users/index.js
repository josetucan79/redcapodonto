import { createApp } from 'vue'
import App from './App.vue'
import useRouter from './router'
import { createPinia } from 'pinia'
import { errorsManager } from '@/utils/store/plugins'
import { useEventBus, eventBus } from '../plugins/EventBus'

// Vue UI utilities
import { BootstrapVue } from 'bootstrap-vue'
import 'bootstrap-vue/dist/style.css'
import useStore from './store'
import { TranslateDirective, TooltipDirective, AutoExpandDirective } from '../directives'

const init = (target) => {
    const app = createApp(App)
    // app.config.errorHandler = (err, instance, info) => {
    //     console.log(err, instance, info)
    // }
    const router = useRouter()
    const pinia = createPinia()
    pinia.use(errorsManager)
    
    app.use(pinia)
    app.use(BootstrapVue)
    app.use(useEventBus())
    app.use(router)
    app.directive('tt', TranslateDirective)
    app.directive('mytooltip', TooltipDirective)
    app.directive('auto-expand', AutoExpandDirective)
    app.mount(target)
    const store = app.runWithContext(() => {
        // store must be obtained here or inject in the plugin will not work
        return useStore()
    })

    return { app, router, store, eventBus }
}

// eventBus.addEventListener('body-textarea-ready', (e) => {
//     console.log(e.detail?.element)
// })

export { init as default }
