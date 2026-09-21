import { getCurrentInstance, inject } from 'vue'

function uuidv4() {
    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) =>
        (
            c ^
            (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))
        ).toString(16)
    )
}
/**
 * a shared store can be controlled by multiple apps
 */
const sharedStore = {}

/**
 * 
 * @param {String} name 
 * @param {Function} dataFunc 
 * @param {String} sharedStoreID name of the store that will be share among multilpe apps
 * @returns {Function}
 */
export const defineStore = (name, dataFunc, shared = false) => {
    const storeID = uuidv4() // define a unique ID for the shared store
    if (shared) sharedStore[storeID] = {}

    const useStore = () => {
        const store = inject('store')
        let _store = shared ? sharedStore[storeID] : store
        if (typeof _store[name] === 'undefined') {
            _store[name] = dataFunc()
        }
        return _store[name]
    }
    return useStore
}

export const createStore = () => {
    /**
     * store is defined here so every instance of createStore
     * will be indipendent from each other
     */
    const store = {}

    return {
        install: (app) => {
            app.config.globalProperties.$store = store
            app.provide('store', store)
            // this will be shared among multiple apps
            app.config.globalProperties.$sharedStore = store
            app.provide('sharedStore', sharedStore)
        },
    }
}
