<template>
    <slot :animating="animating" :text="text">
        <span :class="{ animating }">{{ text }}</span>
    </slot>
</template>

<script setup>
/**
 * animate a counter with a progressive value
 * the counter start5s from the previus value
 * of the component
 */
import { ref, watchEffect } from 'vue'
import { useAnimatedCounter } from '../../utils/use'

const props = defineProps({
    value: { type: Number, default: 0 },
    duration: { type: Number, default: 300 }, //milliseconds
})
const animating = ref(false)
const prev = ref(0)
const text = ref('')

const animateCounter = useAnimatedCounter((value) => {
    text.value = value
})

watchEffect(async () => {
    const previous = prev.value
    const current = props.value
    prev.value = current
    animating.value = true
    await animateCounter(previous, current, props.duration)
    animating.value = false
})
</script>

<style scoped></style>
