<template>
    <div class="progress-stacked">
        <div class="progress" role="progressbar" :aria-valuenow="successPercentage" aria-valuemin="0" aria-valuemax="100" :style="`width: ${successPercentage}%`">
            <div class="progress-bar progress-bar-animated text-bg-primary">{{ success }}</div>
        </div>
        <div class="progress" role="progressbar" :aria-valuenow="warningsPercentage" aria-valuemin="0" aria-valuemax="100" :style="`width: ${warningsPercentage}%`">
            <div class="progress-bar progress-bar-animated text-bg-warning">{{ warnings }}</div>
        </div>
        <div class="progress" role="progressbar" :aria-valuenow="errorsPercentage" aria-valuemin="0" aria-valuemax="100" :style="`width: ${errorsPercentage}%`">
            <div class="progress-bar progress-bar-animated text-bg-danger">{{ errors }}</div>
        </div>
    </div>
</template>

<script setup>
import { computed, toRefs } from 'vue'

const props = defineProps({
    total: { type: Number, default: 0 },
    success: { type: Number, default: 0 },
    warnings: { type: Number, default: 0 },
    errors: { type: Number, default: 0 },
    text: { type: String, default: '' },
})

const {total, success, warnings, errors} = toRefs(props)
const successPercentage = computed(() => {
    if (total.value === 0) return 0
    return success.value / total.value * 100
})
const warningsPercentage = computed(() => {
    if (total.value === 0) return 0
    return warnings.value / total.value * 100
})
const errorsPercentage = computed(() => {
    if (total.value === 0) return 0
    return errors.value / total.value * 100
})

</script>

<style scoped>
.progress {
    transition-property: width;
    transition-timing-function: ease-in-out;
    transition-duration: 300ms;
}
</style>
