import {createApp} from 'vue'
import component from './Modal.vue'


/**
 * helper class that can create
 * alert and confirm dialogs
 */
export default class Manager {
    static make(params) {
        const target = document.createElement('div')
        document.body.appendChild(target)

        const app = createApp(component, {...params})
        return app.mount(target)
    }
    static async confirm(params) {
        const modal = Manager.make(params)
        
        return Manager.show(modal)
    }
    static async alert(params) {
        const modal = Manager.make({okOnly:true, ...params})
        return Manager.show(modal)
    }
    static async prompt(params) {
        const modal = Manager.make({showPrompt:true, ...params})
        return Manager.show(modal)
    }
    static async show(modal) {
        const cleanUp = () => {
            // remove element from DOM when done
            const wrapper = modal.$el.parentNode
            wrapper.parentNode.removeChild(wrapper)
        }
        const promise = new Promise((resolve,reject) => {
            setTimeout(async () => {
                const response = await modal.show()
                resolve(response)
                cleanUp()
            }, 0) // settimeut 0 is needed or show will not work
        }) 
        return promise
    }
}