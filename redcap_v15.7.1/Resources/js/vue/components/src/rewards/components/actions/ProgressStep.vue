<template>
    <div class="step" :class="classes">
        <div class="step-circle">
            <span class="content">
                <template v-if="status === STATUS.COMPLETED">
                    <i class="fas fa-check fa-fw text-light"></i>
                </template>
                <template v-else-if="status === STATUS.REJECTED">
                    <i class="fas fa-times fa-fw text-light"></i>
                </template>
                <template v-else>
                    <slot name="circle">
                        <span>•</span>
                    </slot>
                </template>
            </span>
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
import { computed, toRefs } from 'vue'
import { PROGRESS_STATUS as STATUS } from '@/Rewards/variables'

const props = defineProps({
    active: { type: Boolean, default: false },
    status: { type: String, default: STATUS.PENDING },
    label: { type: String, default: `Step 0` },
})

const { active, status } = toRefs(props)

const classes = computed(() => {
    return {
        active: active.value,
        completed: status.value === STATUS.COMPLETED,
        rejected: status.value === STATUS.REJECTED,
    }
})
</script>

<style scoped>
.step {
    display: flex;
    flex-direction: column;
    justify-content: start;
    align-items: center;
    position: relative;
}
.step-circle {
    position: relative;
    width: var(--circle-size);
    height: var(--circle-size);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1;
    color: var(--active-color);
}
.step.active .step-circle {
    color: white;
}
.step-circle::before {
    content: '';
    position: absolute;
    inset: 0;
    border: solid 2px var(--active-color);
    background-color: white;
    border-radius: 50px;
    z-index: 0;
}
.step.completed .step-circle::before {
    background-color: var(--active-color);
}
.step.rejected .step-circle::before {
    background-color: var(--rejected-color);
    border-color: var(--rejected-color);
}
.step.active .step-circle::before {
    background-color: var(--active-color);
}
.step-circle .content {
    z-index: 1;
}
.indicator {
    width: 100%;
    height: 5px;
    position: absolute;
    top: calc(var(--circle-size) / 2);
    left: 50%;
    transform: translate(0, -50%);
}
.step:not(:last-child) .indicator::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 5px;
    background-color: var(--inactive-color);
    z-index: 0;
}
.progress {
    position: absolute;
    background-color: var(--active-color);
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
    /* position: absolute; */
    top: 100%;
    width: 80%;
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
}
.step.active:not(:last-child) .step-label {
    font-weight: 700;
    color: var(--active-color);
}
</style>
