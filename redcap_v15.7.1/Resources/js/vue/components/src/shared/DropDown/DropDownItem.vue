<template>
    <div data-dropdown-item @click="onClick">
        <slot></slot>
    </div>
</template>

<script>
/**
 * specify data-no-close on an element to prevent closing the dropdown
 */
import { ref } from 'vue'

export default {
    emits: ['click'],
    setup (props, context) {
        
        function onClick() {
            context.emit('click', this)
        }
        
        return {
            onClick
        }
    }
}
</script>

<style scoped>
[data-dropdown-item] {
    padding: .25rem 1.5rem;
    white-space: nowrap;
    --active-bg-color: rgb(0 123 255);
    --active-color: rgb(255 255 255);
    --hover-bg-color: rgba(0 0 0 / .025);
}
[data-dropdown-item],
[data-dropdown-item] :deep(*) {
    cursor: pointer;
}
[data-dropdown-item]:hover {
    background-color: var(--hover-bg-color);
}
[data-dropdown-item][active=true] {
    background-color: var(--active-bg-color);
    color: var(--active-color);
}
</style>