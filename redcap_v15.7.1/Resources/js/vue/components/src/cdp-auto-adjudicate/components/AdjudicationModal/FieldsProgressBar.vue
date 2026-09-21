<template>
    <div>
        <ProgressStacked>
            <ProgressBar
                label="successful adjudications"
                :width="calcPercentage(stats.total_fields, stats.successful_fields)"
                color-class="bg-success"
                showWidth
                striped
                :animated="processing"
            />
            <ProgressBar
                label="errors"
                :width="calcPercentage(stats.total_fields, stats.errors)"
                color-class="bg-danger"
                showWidth
                striped
                :animated="processing"
            />
        </ProgressStacked>
        <div class="small">
            <small>
                <span>Successful adjudications: </span>
                <span class="fw-bold">{{ stats.successful_fields }}</span>
            </small>
        </div>
        <div class="small">
            <small>
                <span>Errors: </span>
                <span class="fw-bold">{{ stats.errors }}</span>
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
