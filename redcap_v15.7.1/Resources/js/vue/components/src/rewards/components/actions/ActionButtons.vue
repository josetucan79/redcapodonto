<template>
    <div class="action-buttons-wrapper">
        <button
            type="button"
            class="btn btn-sm btn-outline-primary"
            @click="onRejectClicked"
            :disabled="loading || !canReject"
        >
            <i class="fas fa-thumbs-down fa-fw me-1"></i>
            <span>Reject</span>
        </button>
        <button
            type="button"
            class="btn btn-sm btn-outline-primary"
            @click="onApproveClicked"
            :disabled="loading || !canApprove"
        >
            <i class="fas fa-thumbs-up fa-fw me-1"></i>
            <span>Approve</span>
        </button>
    </div>
</template>

<script setup>
import { inject, toRefs } from 'vue'
import { ORDER_STATUS } from '../../variables'

const approvalService = inject('approval-service')
// please note: thse are methods, so I'm not using toRefs
const { canReject, canApprove, approve, reject } = approvalService
const { loading, status } = toRefs(approvalService)

const emit = defineEmits(['approve', 'reject'])
async function onRejectClicked() {
    emit('reject')
    await reject()
}
async function onApproveClicked() {
    emit('approve')
    await approve()
    if (status.value === ORDER_STATUS.COMPLETED) console.log(`🥳🎉`)
}
</script>

<style scoped>
.action-buttons-wrapper {
    display: contents;
}
</style>
