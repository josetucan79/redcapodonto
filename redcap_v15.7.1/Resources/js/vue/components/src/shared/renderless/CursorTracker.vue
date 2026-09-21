<template>
    <!-- Renderless: we only provide a scoped slot -->
    <slot :onBlur="handleBlur"></slot>
</template>

<script setup>
import { defineEmits, onMounted, ref, useSlots } from 'vue'

const slots = useSlots()
// Emit the updated cursor position event
const emit = defineEmits(['updateCursorPosition'])
const lastposition = ref(0)

/**
 * Called when the wrapped input/textarea emits a blur event.
 * Extracts the cursor position from the event target and emits it.
 * @param {Event} e - The blur event.
 */
function handleBlur(e) {
    lastposition.value = e.target?.selectionStart ?? 0
    emit('updateCursorPosition', lastposition.value)
}

onMounted(() => {
    console.log(slots.default()[0])
})
</script>
