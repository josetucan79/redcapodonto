import { ref } from 'vue'

// Helper to create tooltip element
const createTooltipElement = (binding) => {
    const position = binding.arg || 'top'
    const tooltip = document.createElement('div')
    tooltip.className = `tooltip tooltip-${position}`
    tooltip.style.position = 'absolute'
    tooltip.style.padding = '5px 10px'
    tooltip.style.backgroundColor = 'rgba(0, 0, 0, 0.75)'
    tooltip.style.color = 'white'
    tooltip.style.fontWeight = '200'
    tooltip.style.borderRadius = '4px'
    tooltip.style.whiteSpace = 'nowrap'
    tooltip.style.transition = 'opacity 0.2s'
    tooltip.style.opacity = '0'
    tooltip.style.pointerEvents = 'none'
    tooltip.innerText = binding.value
    return tooltip
}

// Helper to create caret element
const createCaretElement = () => {
    const caret = document.createElement('div')
    caret.className = 'tooltip-caret'
    caret.style.position = 'absolute'
    caret.style.width = '0'
    caret.style.height = '0'
    caret.style.borderStyle = 'solid'
    return caret
}

// Helper to set caret styles based on position
const setCaretStyles = (caret, position) => {
    // reset positions
    caret.style.top = 'revert'
    caret.style.right = 'revert'
    caret.style.bottom = 'revert'
    caret.style.left = 'revert'

    switch (position) {
        case 'top':
            caret.style.borderWidth = '5px 5px 0 5px'
            caret.style.borderColor =
                'rgba(0, 0, 0, 0.75) transparent transparent transparent'
            caret.style.bottom = '-5px'
            caret.style.left = '50%'
            caret.style.transform = 'translateX(-50%)'
            break
        case 'bottom':
            caret.style.borderWidth = '0 5px 5px 5px'
            caret.style.borderColor =
                'transparent transparent rgba(0, 0, 0, 0.75) transparent'
            caret.style.top = '-5px'
            caret.style.left = '50%'
            caret.style.transform = 'translateX(-50%)'
            break
        case 'left':
            caret.style.borderWidth = '5px 0 5px 5px'
            caret.style.borderColor =
                'transparent transparent transparent rgba(0, 0, 0, 0.75)'
            caret.style.top = '50%'
            caret.style.right = '-5px'
            caret.style.transform = 'translateY(-50%)'
            break
        case 'right':
            caret.style.borderWidth = '5px 5px 5px 0'
            caret.style.borderColor =
                'transparent rgba(0, 0, 0, 0.75) transparent transparent'
            caret.style.top = '50%'
            caret.style.left = '-5px'
            caret.style.transform = 'translateY(-50%)'
            break
        case 'move':
            caret.style.display = 'none' // Hide caret in move mode
            break
        case 'auto':
        default:
            caret.style.borderWidth = '0 5px 5px 5px'
            caret.style.borderColor =
                'transparent transparent rgba(0, 0, 0, 0.75) transparent'
            caret.style.top = '-5px'
            caret.style.left = '50%'
            caret.style.transform = 'translateX(-50%)'
            break
    }
}

// Helper to set tooltip position
const setPosition = (event, el, tooltip, caret, position) => {
    if (!tooltip) return
    const rect = el.getBoundingClientRect()
    const tooltipRect = tooltip.getBoundingClientRect()
    let top, left

    switch (position) {
        case 'top':
            top = rect.top + window.scrollY - tooltipRect.height - 8
            left =
                rect.left +
                window.scrollX +
                (rect.width - tooltipRect.width) / 2
            break
        case 'bottom':
            top = rect.bottom + window.scrollY + 8
            left =
                rect.left +
                window.scrollX +
                (rect.width - tooltipRect.width) / 2
            break
        case 'left':
            top =
                rect.top +
                window.scrollY +
                (rect.height - tooltipRect.height) / 2
            left = rect.left + window.scrollX - tooltipRect.width - 8
            break
        case 'right':
            top =
                rect.top +
                window.scrollY +
                (rect.height - tooltipRect.height) / 2
            left = rect.right + window.scrollX + 8
            break
        case 'move':
            top = event.pageY + 10
            left = event.pageX + 10
            break
        case 'auto':
        default:
            top = rect.bottom + window.scrollY + 8
            left =
                rect.left +
                window.scrollX +
                (rect.width - tooltipRect.width) / 2
            break
    }

    tooltip.style.top = `${top}px`
    tooltip.style.left = `${left}px`
    setCaretStyles(caret, position)
}

// Initialize tooltip
const initTooltip = (el, binding) => {
    const position = binding.arg || 'top'
    let tooltip, caret

    // State to control tooltip visibility and position
    const showTooltip = ref(false)

    // Event handlers to show/hide tooltip and position it
    const show = (event) => {
        if (!tooltip) {
            // Create tooltip and caret elements on first show
            tooltip = createTooltipElement(binding)
            caret = createCaretElement()
            tooltip.appendChild(caret)
            document.body.appendChild(tooltip)
            // Save references for cleanup
            el._tooltip = tooltip
        }
        showTooltip.value = true
        tooltip.style.opacity = '1'
        setPosition(event, el, tooltip, caret, position)
    }

    const hide = () => {
        if (!tooltip) return
        showTooltip.value = false
        tooltip.style.opacity = '0'
        // remove on transitionend
        tooltip.addEventListener(
            'transitionend',
            () => {
                const parent = tooltip?.parentNode
                if (!parent || !parent.contains(tooltip)) return
                parent.removeChild(tooltip)
                el._tooltip = tooltip = null
            },
            { once: true }
        )
    }

    const updatePosition = (event) => {
        if (showTooltip.value) {
            setPosition(event, el, tooltip, caret, position)
        }
    }

    // Attach event listeners
    const abortController = new AbortController()
    el.addEventListener('mouseenter', show, { signal: abortController.signal })
    el.addEventListener('mouseleave', hide, { signal: abortController.signal })
    el.addEventListener('mousemove', updatePosition, {
        signal: abortController.signal,
    })

    // Save references for cleanup
    el._abortController = abortController
}

// Destroy tooltip
const destroyTooltip = (el) => {
    el._abortController.abort()
    if (!el._tooltip) return
    document.body.removeChild(el._tooltip)
}

export default {
    mounted(el, binding) {
        initTooltip(el, binding)
    },
    beforeUnmount(el) {
        destroyTooltip(el)
    },
}
