<template>
    <div class="d-flex flex-column justify-content-start align-items-start" v-if="currentOrder">
        <details v-if="record?.participant_details">
            <summary class="text-muted fst-italic">Participant Details...</summary>
            <div class="border rounded p-2" style="white-space: pre-wrap;">
                <code>{{ record.participant_details }}</code>
            </div>
        </details>

        <details>
            <summary class="text-muted fst-italic">Eligibility Logic...</summary>
            <div class="border rounded p-2">
                <code>{{ currentOrder?.eligibility_logic }}</code>
            </div>
        </details>
        <details v-if="currentOrder?.reference_order || currentOrder?.internal_reference">
            <summary class="text-muted fst-italic">Order Details...</summary>
            <div class="border rounded p-2">
                <code class="d-block">Order: {{ currentOrder.reference_order ?? '---' }}</code>
                <code class="d-block">Internal Code: {{ currentOrder.internal_reference ?? '---' }}</code>
            </div>
        </details>
    </div>
</template>

<script setup>
import { inject, toRefs } from 'vue'

const approvalService = inject('approval-service')
const { currentOrder = {}, record = {} } = toRefs(approvalService)
</script>

<style scoped>
details {
    text-align: left;
    width: 100%;
}
</style>
