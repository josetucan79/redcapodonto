<template>
    <div ref="divRef" @click="onElementClicked" :class="{'fw-bold': animating}">{{ text }}</div>
</template>

<script setup>
import { computed, onMounted, ref, watchEffect } from 'vue'
import { useAnimatedCounter } from '../../utils/use'

const divRef = ref()
const text = ref(0)
const animating = ref(false)

const animatedCounter = useAnimatedCounter((value, progress, percentage) => {
    text.value = parseInt(value)
})

function getRandomInt(max) {
    return Math.floor(Math.random() * max)
}

async function onElementClicked() {
    if (animating.value) return
    const start = text.value
    const end = start - getRandomInt(200)
    console.log(start, end)
    animating.value = true
    const done = await animatedCounter(start, end, 800)
    animating.value = false
}
</script>

<style scoped>
div::after {
    content: counter(--my-number);
    display: block;
    background-color: red;
    width: 20px;
    height: 20px;
}
</style>
