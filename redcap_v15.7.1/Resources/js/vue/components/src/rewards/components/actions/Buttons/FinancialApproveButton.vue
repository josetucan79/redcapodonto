<template>
    <button type="button" class="btn btn-sm btn-outline-secondary" @click="onClick" :disabled="disabled">
        <i class="fas fa-thumbs-up fa-fw text-success"></i>
        <span class="ms-1">Approve</span>
    </button>
</template>

<script setup>
import { computed, inject, toRefs } from 'vue'
import { ORDER_STATUS, ACTION_EVENT } from '@/Rewards/variables'

const ACTION = ACTION_EVENT.BUYER_APPROVAL

const disabled = computed(() => loading.value || !canPerformAction(ACTION))

const approvalService = inject('approval-service')
// please note: thse are methods, so I'm not using toRefs
const { canPerformAction, performAction } = approvalService
const { loading, status } = toRefs(approvalService)

async function onClick() {
    await performAction(ACTION)

    if (status.value === ORDER_STATUS.COMPLETED) {
        console.log(`🥳🎉`)
    }
}
</script>

<style scoped></style>
