import { default as useAppStore } from './app'
import { default as useEmailStore } from './email'
import { default as useQueriesStore } from './queries'
import { default as useMessagesStore } from './messages'
import { default as useTestQueryStore } from './test-query'

const storeRegistry = {
    app: useAppStore,
    email: useEmailStore,
    queries: useQueriesStore,
    messages: useMessagesStore,
    testQuery: useTestQueryStore,
}

const useStore = () => {
    const stores = Object.fromEntries(
        Object.entries(storeRegistry).map(([key, storeFn]) => [key, storeFn()])
    )

    return {
        ...stores,
    }
}

export {
    useStore as default,
    useAppStore,
    useEmailStore,
    useQueriesStore,
    useMessagesStore,
    useTestQueryStore,
}