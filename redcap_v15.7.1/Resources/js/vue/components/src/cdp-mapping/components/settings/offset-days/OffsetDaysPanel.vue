<template>
    <div>
        <div class="d-flex gap-2">
            <PlusMinusSelect v-model:selected="days_plus_minus" />
            <DaysInput v-model:days="days" class="flex-fill" />
        </div>
        <DaysRange v-model:days="days" />
        <span
            class="text-muted fst-italic small"
            v-tt:min_max_days="{
                min_minutes: MIN_MINUTES,
                min_days: MIN_DAYS,
                max_days: MAX_DAYS,
            }"
        ></span>
    </div>
</template>

<script setup>
import { computed, inject, ref } from 'vue'
import { MIN_DAYS, MAX_DAYS, MIN_MINUTES } from '@/cdp-mapping/constants'
import PlusMinusSelect from './PlusMinusSelect.vue'
import DaysInput from './DaysInput.vue'
import DaysRange from './DaysRange.vue'

const settingsStore = inject('settings-store')

const days = computed({
    get: () => settingsStore.days,
    set: (value) => (settingsStore.days = parseFloat(value)),
})
const days_plus_minus = computed({
    get: () => settingsStore.days_plus_minus,
    set: (value) => settingsStore.days_plus_minus = value,
})
</script>

<style scoped></style>
b
