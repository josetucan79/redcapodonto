<template>
    <div>
        <ProgressStacked>
            <ProgressBar
                label="adjudicated"
                :width="calcPercentage(stats.total_values, stats.adjudicated_values)"
                color-class="bg-success"
                showWidth
                striped
                :animated="processing"
            />
            <ProgressBar
                label="excluded"
                :width="calcPercentage(stats.total_values, stats.excluded_values)"
                color-class="bg-warning"
                showWidth
                striped
                :animated="processing"
            />
            <ProgressBar
                label="errors"
                :width="calcPercentage(stats.total_values, stats.unprocessed_values)"
                color-class="bg-danger"
                showWidth
                striped
                :animated="processing"
            />
        </ProgressStacked>
        <div class="small">
            <small>
                <span>Adjudicated: </span>
                <span class="fw-bold">{{ stats.adjudicated_values }}</span>
            </small>
        </div>
        <div class="small">
            <small>
                <span>Excluded: </span>
                <span class="fw-bold">{{ stats.excluded_values }}</span>
            </small>
        </div>
        <div class="small">
            <small>
                <span>Errors: </span>
                <span class="fw-bold">{{ stats.unprocessed_values }}</span>
            </small>
        </div>
    </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import ProgressStacked from '@/shared/ProgressBar/ProgressStacked.vue'
import ProgressBar from '@/shared/ProgressBar/ProgressBar.vue'

const adjudicationStore = inject('adjudication-store')

const stats = computed(() => adjudicationStore.stats)
const processing = computed(() => adjudicationStore.processing)

const calcPercentage = (total, value) => {
    if (value === 0 || total === 0) return 0
    return Math.round((value / total) * 100)
}
</script>

<style lang="scss" scoped></style>
