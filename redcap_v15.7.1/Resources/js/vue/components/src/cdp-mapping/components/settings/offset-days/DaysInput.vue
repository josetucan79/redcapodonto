<template>
    <div>
        <input
            class="form-control form-control-sm"
            type="number"
            :step="STEP"
            :min="MIN_DAYS"
            :max="MAX_DAYS"
            v-model="days"
        />
    </div>
</template>

<script setup>
import { STEP, MIN_DAYS, MAX_DAYS } from '@/cdp-mapping/constants'

const days = defineModel('days', { default: MIN_DAYS, required: true })
</script>

<style scoped></style>
b
