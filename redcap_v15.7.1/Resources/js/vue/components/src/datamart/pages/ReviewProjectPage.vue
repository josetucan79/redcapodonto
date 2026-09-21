<template>
    <div>
        <RevisionForm
            :revision="revision?.data"
            :user_id="userID"
            :request_id="requestID"
        />
        <RevisionDetails :revision="revision">
            <template #body-end>
                <div class="mt-2">
                    <span class="fw-bold">MRNs</span>
                    <MrnList :mrns="revision?.data?.mrns" />
                </div>
            </template>
        </RevisionDetails>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRevisionsStore } from '../store'
import RevisionForm from '../components/RevisionForm.vue'
import MrnList from '../components/MrnList.vue'
import RevisionDetails from '../components/RevisionDetails.vue'

const revisionsStore = useRevisionsStore()
const revision = computed(() => revisionsStore?.selected)
const userID = computed(() => revision.value?.metadata?.creator?.id)
const requestID = computed(() => revision.value?.metadata?.request_id)
</script>

<style scoped></style>
