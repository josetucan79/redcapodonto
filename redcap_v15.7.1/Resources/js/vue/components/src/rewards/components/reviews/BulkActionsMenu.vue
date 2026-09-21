<template>
    <b-dropdown variant="outline-primary" size="sm">
        <template #button>
            <template v-if="loading">
                <i class="fas fa-spinner fa-spin fa-fw"></i>
            </template>
            <template v-else>
                <i class="fas fa-ellipsis-vertical fa-fw"></i>
            </template>
            <span>Bulk actions</span>
        </template>
        <b-dropdown-header>
            <small class="d-block small text-muted"
                >Approval items: {{ eligibleSelected.length }}</small
            >
        </b-dropdown-header>
        <b-dropdown-item
            :class="{
                disabled:
                    Gate.denies('review_eligibility') ||
                    loading ||
                    eligibleSelected.length === 0,
            }"
            @click="onReviewerApproveClicked"
        >
            <i class="fa fa-thumbs-up fa-fw text-success me-2"></i>
            <span>Reviewer:Approve</span>
        </b-dropdown-item>
        <b-dropdown-item
            :class="{
                disabled:
                    Gate.denies('review_eligibility') ||
                    loading ||
                    eligibleSelected.length === 0,
            }"
            @click="onReviewerRejectClicked"
        >
            <i class="fa fa-thumbs-down fa-fw text-danger me-2"></i>
            <span>Reviewer:Reject</span>
        </b-dropdown-item>
        <b-dropdown-divider />
        <b-dropdown-header>
            <small class="d-block small text-muted"
                >Order items: {{ approvedSelected.length }}</small
            >
        </b-dropdown-header>
        <b-dropdown-item
            :class="{
                disabled:
                    Gate.denies('place_orders') ||
                    loading ||
                    approvedSelected.length === 0,
            }"
            @click="onBuyerApproveClicked"
        >
            <i class="fa fa-thumbs-up fa-fw text-success me-2"></i>
            <span>Buyer:Approve</span>
        </b-dropdown-item>
        <b-dropdown-item
            :class="{
                disabled:
                    Gate.denies('place_orders') ||
                    loading ||
                    approvedSelected.length === 0,
            }"
            @click="onBuyerRejectClicked"
        >
            <i class="fa fa-thumbs-down fa-fw text-danger me-2"></i>
            <span>Buyer:Reject</span>
        </b-dropdown-item>
        <b-dropdown-divider />
        <b-dropdown-header>
            <small class="d-block small text-muted"
                >Total items: {{ selected.length }}</small
            >
        </b-dropdown-header>
    </b-dropdown>
</template>

<script setup>
import { ref, toRefs } from 'vue'
import { useSelectionStore } from '@/rewards/store/dynamic-review-selection'
import { useSchedulingService } from '@/rewards/services'
import { ACTION_EVENT } from '../../variables'

import Gate from '@/rewards/utils/Gate'

const loading = ref(false)
const selectionStore = useSelectionStore()
const schedulingService = useSchedulingService()

const {selected, eligibleSelected, approvedSelected} = toRefs(selectionStore)


async function onReviewerApproveClicked() {
    schedulingService.schedule(ACTION_EVENT.REVIEWER_APPROVAL, [...eligibleSelected.value])
}

async function onReviewerRejectClicked() {
    schedulingService.schedule(ACTION_EVENT.REVIEWER_REJECTION, [...eligibleSelected.value])
}
async function onBuyerApproveClicked() {
    schedulingService.schedule(ACTION_EVENT.BUYER_APPROVAL, [...approvedSelected.value])
}
async function onBuyerRejectClicked() {
    schedulingService.schedule(ACTION_EVENT.BUYER_REJECTION, [...approvedSelected.value])
}
</script>

<style scoped>
.disabled {
    pointer-events: none;
}
.disabled * {
    opacity: 0.5;
    cursor: default;
}
</style>
