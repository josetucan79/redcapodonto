/**
 * store is declared here so it is accesible both
 * in defineStore and createStore.
 * a store is only available at app level
 */
let store

/**
 * a shared store can be controlled by multiple apps
 */
const sharedStore = {}

export const defineStore = (name, dataFunc, shared = false) => {
    const useStore = () => {
        let _store = shared ? sharedStore : store
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
    store = {}
    return {
        install: (app) => {
            app.config.globalProperties.$store = store
            app.provide('store', store)
            // this will be stored among multiple apps
            app.config.globalProperties.$sharedStore = store
            app.provide('sharedStore', sharedStore)
        },
    }
}
