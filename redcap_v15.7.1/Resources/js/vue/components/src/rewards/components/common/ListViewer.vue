<template>
    <slot v-bind="{ ...slotProps }"></slot>

    <slot name="footer" v-bind="{ ...slotProps }">
        <button
            type="button"
            class="btn btn-xs btn-outline-secondary"
            @click="onPrevClicked"
        >
            <i class="fas fa-chevron-left fa-fw"></i>
        </button>
        <button
            type="button"
            class="btn btn-xs btn-outline-secondary"
            @click="onNextClicked"
        >
            <i class="fas fa-chevron-right fa-fw"></i>
        </button>
    </slot>
</template>

<script setup>
import { ref, toRefs, computed } from 'vue'

const props = defineProps({
    totalItems: { type: Number, default: 0 },
})
const { totalItems } = toRefs(props)

const index = ref(0)

const prev = () => {
    if (index.value <= 0) {
        index.value = 0
        return
    }
    index.value = index.value - 1
}

const next = () => {
    if (totalItems.value === 0) return
    if (index.value >= totalItems.value - 1) {
        index.value = totalItems.value - 1
        return
    }
    index.value = index.value + 1
}

const slotProps = {
    index,
    totalItems,
    prev,
    next,
}

const onPrevClicked = () => prev()
const onNextClicked = () => next()
</script>

<style scoped></style>
