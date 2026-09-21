<template>
    <slot :text="shouldDisplayLabel ? label : null"></slot>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: { type: String, required: true },
    index: { type: Number, required: true },
    labels: { type: Array, required: true },
})
const shouldDisplayLabel = computed(() => {
    // Always display the label for the first item
    if (props.index === 0) {
        return true
    }
    // Compare current label with the previous item's label
    const previousLabel = props.labels[props.index - 1]
    return props.label !== previousLabel
})
</script>

<style scoped></style>
