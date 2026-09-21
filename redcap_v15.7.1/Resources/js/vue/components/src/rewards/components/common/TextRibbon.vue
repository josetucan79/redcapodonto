<template>

        <svg
        ref="root"
        data-ribbon
        :width="width"
        :height="height"
        viewBox="0 0 100 100"
        preserveAspectRatio="xMidYMid meet"
    >
        <polygon
            points="0,0 100,100 100,50 50,0"
            :fill="backgroundColor"
            style="opacity: 0.8"
        ></polygon>
        <text
            text-anchor="middle"
            dominant-baseline="middle"
            x="50"
            y="50"
            :fill="textColor"
            transform="rotate(45,50,50) translate(0,-16)"
            style="
                font-size: 14px;
                font-family: Open Sans, Helvetica, Arial, sans-serif;
                font-weight: bold;
            "
        >
            {{ text }}
        </text>
    </svg>

</template>

<script setup>
import { ref, onMounted } from 'vue'

const root = ref()
const width = ref(100);
const height = ref(100);

const props = defineProps({
    text: { type: String, default: '' },
    textColor: { type: String, default: 'white' },
    backgroundColor: { type: String, default: '#A00000' },
})

const resizeRibbon = () => {
    const container = root.value
    if (container) {
        const containerHeight = container.clientHeight
        height.value = containerHeight
        width.value = containerHeight
    }
}

onMounted(() => {
    resizeRibbon()
    window.addEventListener('resize', resizeRibbon)
})
</script>

<style>
[data-ribbon] {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0.8;
    pointer-events: none;
    transform: rotate(270deg);
}
*:has([data-ribbon]) {
    position: relative;
}
</style>
