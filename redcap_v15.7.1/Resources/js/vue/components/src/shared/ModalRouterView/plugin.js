/**
 * this plugin exposes components, features, and directive
 * to the app
 */

import { inject, createApp, h } from 'vue'

import { ModalRouterView } from '.'
import TestComponent1 from '../../modal-routes/components/TestComponent1.vue'

// Define the plugin object
const MyPlugin = {
    install(app, options) {
        // Create a new div to mount the component
        const mountPoint = document.createElement('div')
        document.body.appendChild(mountPoint)

        // Dynamically create and mount the component
        const instance = createApp({
            render() {
                return h(ModalRouterView, {})
            },
        })

        setTimeout(() => {
            instance.mount(mountPoint)
        }, 1000)

        console.log(app)
    },
}

export { MyPlugin as default }
