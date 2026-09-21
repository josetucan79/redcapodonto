const exposedStores = {}

// render all stores available
export const exposeStoresPlugin = ({ store }) => {
    // Add each store to the exposedStores object
    exposedStores[store.$id] = store
}

export const getExposedStores = () => exposedStores
