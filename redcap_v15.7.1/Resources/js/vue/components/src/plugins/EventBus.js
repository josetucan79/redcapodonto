export const eventBus = new EventTarget()

eventBus.emit = (eventName, detail = {}) => {
    eventBus.dispatchEvent(
        new CustomEvent(eventName, {
            detail: {
                ...detail,
                timestamp: Date.now(),
            },
        })
    )
}

export const useEventBus = () => {
    return {
        install: (app) => {
            // this will be shared among multiple apps
            app.config.globalProperties.$eventBus = eventBus
            app.provide('eventBus', eventBus)
        },
    }
}
