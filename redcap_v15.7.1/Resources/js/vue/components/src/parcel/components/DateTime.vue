<template>
    <span>{{ date }}<slot></slot></span>
</template>

<script setup>
import moment from 'moment'
import { computed } from 'vue'

const props = defineProps({
    value: { type: String, default: null },
})
const date = computed(() => {
    const today = moment()
    const target = new Date(props.value)
    if (today.isSame(target, 'day')) return moment(target).format('LT')
    return moment(target).format('L')
})
</script>

<style scoped></style>
