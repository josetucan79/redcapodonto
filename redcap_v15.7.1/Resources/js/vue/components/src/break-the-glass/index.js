import { createApp } from 'vue'
import App from './App.vue'
import { createPinia } from 'pinia'
import { errorsManager } from '@/utils/store/plugins'
import { useEventBus, eventBus } from '../plugins/EventBus'

// Vue UI utilities
import { BootstrapVue } from 'bootstrap-vue'
import 'bootstrap-vue/dist/style.css'
import useStore from './store'
import { TranslateDirective, TooltipDirective } from '../directives'

const init = (target) => {
    const app = createApp(App)
    const pinia = createPinia()
    pinia.use(errorsManager)
    app.use(pinia)
    app.use(BootstrapVue)
    app.use(useEventBus())
    app.directive('tt', TranslateDirective)
    app.directive('mytooltip', TooltipDirective)
    // finally mount
    app.mount(target)
    // event bus
    const store = app.runWithContext(() => {
        // store must be obtained here or inject in the plugin will not work
        return useStore()
    })

    return { app, store, eventBus }
}

export { init as default }
