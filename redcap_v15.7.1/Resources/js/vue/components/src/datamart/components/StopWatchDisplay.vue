<template>
    <div class="stopwatch">
        <i class="fas fa-clock fa-fw me-1"></i>
        <span>Elapsed time: {{ seconds }}</span>
        <span>({{ readableTime }})</span>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import moment from 'moment'

const props = defineProps({
    time: { type: Number, default: 0 },
})

const seconds = computed(() => {
    if (isNaN(props.time)) return
    const seconds = (props.time / 1000).toFixed(2)
    return seconds
})
const readableTime = computed(() => {
    const now = moment()
    const elapsed = moment(Date.now() + props.time)
    return elapsed.fromNow(true)
})
</script>

<style scoped>
.stopwatch {
    font-variant-numeric: tabular-nums;
}
</style>
