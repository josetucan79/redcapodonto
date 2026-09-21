<template>
    <div ref="custom-drawer">
        <Transition name="fade" @after-enter="emitShow" @after-leave="emitHide">
            <div v-if="isVisible" class="overlay" @click="handleOverlayClick"></div>
        </Transition>
        <!-- Drawer Container -->
        <div :class="['drawer', position, { open: isVisible }]" :style="drawerStyle">
            <slot :close-drawer="closeDrawer" :is-visible="isVisible" :is-hidden="isHidden" ></slot>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, useTemplateRef, watch, provide, nextTick } from 'vue'
import {useBodyOverflow} from './useBodyOverflow'

// Define events
const emit = defineEmits(['show', 'hide'])

// Create ref for the outer div
const customDrawer = useTemplateRef('custom-drawer')

// Emit events after transitions complete
const emitShow = () => {
    emit('show')
    isHidden.value = false
}

const emitHide = () => {
    emit('hide')
    isHidden.value = true
}

const props = defineProps({
    // Drawer placement: left, right, top, or bottom
    position: {
        type: String,
        default: 'left',
        validator: (value) =>
            ['left', 'right', 'top', 'bottom'].includes(value),
    },
    // Size of the drawer: for left/right this is width, for top/bottom this is height.
    size: {
        type: String,
        default: '300px',
    },
    // Option to disable closing when clicking the overlay
    closeOnOverlayClick: {
        type: Boolean,
        default: true,
    },
    // Optional callback function to run before closing
    beforeClose: {
        type: Function,
        default: null,
    },
    isOpen: {
        type: Boolean,
        default: false,
    },
})

// Internal visibility state
const isVisible = ref(false);
const isHidden = ref(!isVisible.value)

// Watch for changes to the isOpen prop
watch(() => props.isOpen, (newValue) => {
    nextTick(() => {
        isVisible.value = newValue;
    })
}, {immediate: true});

useBodyOverflow(isVisible)

// Close the drawer, with optional callback beforehand
const closeDrawer = async () => {
  // If a beforeClose callback is provided, run it
  if (props.beforeClose) {
    // Check if the callback returns a value (could be a promise)
    const shouldClose = await props.beforeClose();
    
    // Only close if the callback returns true or undefined/null
    if (shouldClose === false) {
      return; // Don't close if false is explicitly returned
    }
  }
  
  // Close the drawer
  isVisible.value = false;
};

provide('drawerState', {
    closeDrawer,
    isVisible,
})

// Handle overlay click
const handleOverlayClick = () => {
    if (!props.closeOnOverlayClick) return
    closeDrawer()
}

// Compute dynamic style based on drawer position
const drawerStyle = computed(() => {
    if (props.position === 'left' || props.position === 'right') {
        return { width: props.size }
    } else if (props.position === 'top' || props.position === 'bottom') {
        return { height: props.size }
    }
    return {}
})

defineExpose({
    closeDrawer,
})
</script>

<style scoped>
/* Overlay covers the entire screen */
.overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1050;
    background-color: rgba(0, 0, 0, 1); /* Full black */
    opacity: 0.5; /* Set opacity here instead of in the background-color */
}

/* Fade transition for the overlay */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Base style for the drawer */
.drawer {
    position: fixed;
    background: #fff;
    z-index: 1050;
    overflow: auto;
    transition: transform 0.3s ease;
}

/* Left drawer: slides in from the left */
.drawer.left {
    top: 0;
    bottom: 0;
    left: 0;
    transform: translateX(-100%);
}
.drawer.left.open {
    transform: translateX(0);
}

/* Right drawer: slides in from the right */
.drawer.right {
    top: 0;
    bottom: 0;
    right: 0;
    transform: translateX(100%);
}
.drawer.right.open {
    transform: translateX(0);
}

/* Top drawer: slides down from the top */
.drawer.top {
    left: 0;
    right: 0;
    top: 0;
    transform: translateY(-100%);
}
.drawer.top.open {
    transform: translateY(0);
}

/* Bottom drawer: slides up from the bottom */
.drawer.bottom {
    left: 0;
    right: 0;
    bottom: 0;
    transform: translateY(100%);
}
.drawer.bottom.open {
    transform: translateY(0);
}
</style>
