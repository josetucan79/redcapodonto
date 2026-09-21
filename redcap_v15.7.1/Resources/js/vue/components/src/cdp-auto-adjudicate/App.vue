<template>
    <div class="border rounded p-2 d-inline-block">
        <div>
            <i class="fas fa-check fa-fw text-success me-2"></i>
            <span class="fw-bold">Instant adjudication is enabled</span>
        </div>
        <AdjudicationPanel />
        <AdjudicationModal />
        <SummaryModal />
    </div>
</template>

<script setup>
import { onMounted, provide } from 'vue'
import { useRecordsMetadataStore, useAdjudicationStore } from './store'
import AdjudicationPanel from './components/AdjudicationPanel.vue'
import AdjudicationModal from './components/AdjudicationModal/AdjudicationModal.vue'
import SummaryModal from './components/SummaryModal/SummaryModal.vue'

const adjudicationStore = useAdjudicationStore()
const recordsMetadataStore = useRecordsMetadataStore()

provide('adjudication-store', adjudicationStore)
provide('records-store', recordsMetadataStore)

onMounted(() => {
    recordsMetadataStore.getRecords(1)
})
</script>

<style scoped>

</style>