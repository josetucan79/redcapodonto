export default {
    mounted(el, binding) {
        let posX = 0,
            posY = 0,
            startMouseX = 0,
            startMouseY = 0
        let offsetX = 0,
            offsetY = 0
        let isDragging = false

        // Create an AbortController and store it on the element
        const abortController = new AbortController()
        const signal = abortController.signal
        el._abortController = abortController

        // Use binding.value as the selector for the draggable target
        const target = binding.value
            ? el.querySelector(binding.value)
            : el // If no value, use the entire element

        if (!target) {
            console.warn(`v-draggable: Target "${binding.value}" not found.`)
            return
        }

        target.style.cursor = 'move'

        const dragStart = (event) => {
            // Enforce "direct click only" if `binding.arg` is present
            if (binding.arg === 'direct' && event.target !== target) {
                return
            }

            event.preventDefault()

            // Calculate the offsets when dragging starts
            const rect = el.getBoundingClientRect()
            startMouseX = event.clientX
            startMouseY = event.clientY
            offsetX = startMouseX - (rect.left + window.scrollX)
            offsetY = startMouseY - (rect.top + window.scrollY)
            isDragging = true

            // Attach mousemove and mouseup listeners with the signal
            document.addEventListener('mousemove', dragElement, { signal })
            document.addEventListener('mouseup', stopDragging, { signal })
        }

        const dragElement = (event) => {
            if (!isDragging) return // Prevent unnecessary calls

            event.preventDefault()

            // Calculate the new position of the modal
            const newLeft = event.clientX - offsetX
            const newTop = event.clientY - offsetY

            // Update element's position
            el.style.position = 'absolute'
            el.style.left = `${newLeft}px`
            el.style.top = `${newTop}px`
        }

        const stopDragging = () => {
            if (!isDragging) return
            isDragging = false

            // Dragging has stopped
            console.log('Dragging stopped.')
        }

        // Attach mousedown listener to the target
        target.addEventListener('mousedown', dragStart, { signal })
    },

    unmounted(el) {
        // Abort all event listeners
        if (el._abortController) {
            el._abortController.abort()
            delete el._abortController
        }
    },
}
