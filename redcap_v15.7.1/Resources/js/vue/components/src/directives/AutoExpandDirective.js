// autoExpand.js
export default {
    mounted(el, binding) {
        // Mark up the element with no resizing or overflow by default
        el.style.overflow = 'hidden'
        el.style.resize = 'none'

        // The "maxRows" can be a number you pass in: v-auto-expand="5"
        // If there's no binding or it's zeroish, we treat it as infinite
        const maxRows =
            binding.value && binding.value > 0 ? binding.value : Infinity

        // A helper that recalculates the textarea height
        const resizeTextarea = () => {
            // Step 1: reset height to auto so scrollHeight is accurate
            el.style.height = 'auto'

            // Step 2: figure out how tall we want to make it
            const scrollHeight = el.scrollHeight

            // If we do have a finite maxRows:
            if (maxRows !== Infinity) {
                // a) get line-height from computed style
                const lineHeight =
                    parseFloat(window.getComputedStyle(el).lineHeight) || 0
                // b) compute maximum allowed height
                const maxHeight = lineHeight * maxRows

                if (scrollHeight > maxHeight) {
                    // If content is bigger than our cap, fix the height and show scrollbar
                    el.style.height = maxHeight + 'px'
                    el.style.overflowY = 'auto'
                    return
                }
            }

            // Otherwise, just expand to scrollHeight, no scrollbar
            el.style.height = scrollHeight + 'px'
            el.style.overflowY = 'hidden'
        }

        // Attach event listener to resize on input
        el.resizeTextareaEvent = resizeTextarea
        el.addEventListener('input', el.resizeTextareaEvent)

        // If there's initial content, resize once on mount
        // (Use setTimeout or nextTick if needed to ensure DOM is painted)
        resizeTextarea()
    },
    beforeUnmount(el) {
        // Clean up event listeners
        el.removeEventListener('input', el?.resizeTextareaEvent)
    },
}
