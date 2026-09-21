<template>
    <div class="step" :class="{completed, active}">
        <div class="circle">
            <span class="content">{{ index }}</span>
        </div>
        <div class="step-label">
            <slot></slot>
        </div>
        <div class="indicator">
            <span class="progress"></span>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    index: { type: Number, default: 1 },
    completed: { type: Boolean, default: false },
    active: { type: Boolean, default: false },
})
</script>

<style scoped>
.step {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    position: relative;
}
.circle {
    position: relative;
    width: 50px;
    height: 50px;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1;
}
.circle::before {
    content: '';
    position: absolute;
    inset: 0;
    border: solid 2px green;
    background-color: white;
    border-radius: 50px;
    z-index: 0;
}
.circle .content {
    z-index: 1;
}
.indicator {
    width: 100%;
    height: 5px;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(0, -50%);
}
.step:not(:last-child) .indicator::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 5px;
    background-color: grey;
    z-index: 0;
}
.progress {
    position: absolute;
    background-color: red;
    height: 100%;
    width: 0;
    z-index: 1;
    transition-property: width;
    transition-duration: 300ms;
    transition-timing-function: ease-in-out;
}
.step.completed:not(:last-child) .progress {
    width: 100%;
}
.step-label {
    position: absolute;
    top: 100%;
    width: 80%;
    background-color: red;
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>