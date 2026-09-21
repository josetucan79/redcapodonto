import { ref, computed, watch } from 'vue'
const stores = {}

const errors = ref([])
let isResetting = false // Flag to control whether we are resetting

// render all stores available
export const errorsManager = ({ store }) => {
    // Add each store to the exposedStores object
    stores[store.$id] = store

    watch(
        () => store.error,
        (newError) => {
            if (isResetting) return // Skip updates if resetting
            // Update the errors list
            updateErrors()
        },
        { immediate: true } // Run immediately to capture initial state
    )
}

function updateErrors() {
    const collectedErrors = Object.values(stores)
        .map((_store) => _store?.error)
        .filter((error) => error != null)

    // Only update errors if there is a change
    if (JSON.stringify(collectedErrors) !== JSON.stringify(errors.value)) {
        errors.value = collectedErrors
    }
}

export const getErrors = () => errors
export const resetErrors = () => {
    isResetting = true // Set the flag to true to disable watchers

    // Reset the `error` property of each store
    Object.values(stores).forEach((store) => {
        if (store.error !== undefined) {
            store.error = null // Or any default value indicating no error
        }
    })

    // Clear the errors list
    errors.value = []

    isResetting = false // Re-enable watchers after resetting
}
