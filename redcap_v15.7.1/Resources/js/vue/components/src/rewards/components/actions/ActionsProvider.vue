<template>
    <div>
        <template v-if="status === ORDER_STATUS.COMPLETED">
            <div class="d-flex gap-2 justify-content-center">
                <RedeemLinkButton />
                <ReSendEmailButton />
            </div>
        </template>
        <template v-else-if="status === ORDER_STATUS.INVALID"></template>
        <template v-else-if="[ORDER_STATUS.ELIGIBLE, ORDER_STATUS.BUYER_REJECTED].includes(status)">
            <FeedbackPanel class="mb-2" />
            <div class="d-flex gap-2 justify-content-center">
                <ReviewRejectButton />
                <ReviewApproveButton />
            </div>
        </template>
        <template v-else-if="status === ORDER_STATUS.REVIEWER_APPROVED">
            <FeedbackPanel class="mb-2" />
            <div class="d-flex gap-2 justify-content-center">
                <FinancialRejectButton />
                <FinancialApproveButton />
            </div>
        </template>
        <template v-else-if="status === ORDER_STATUS.BUYER_APPROVED">
            <div class="d-flex gap-2 justify-content-center">
                <PlaceOrderButton />
            </div>
        </template>
        <template v-else-if="status === ORDER_STATUS.ORDER_PLACED">
            <div class="d-flex gap-2 justify-content-center">
                <SendEmailButton>Send Email</SendEmailButton>
            </div>
        </template>
        <template v-else-if="status === ORDER_STATUS.REVIEWER_REJECTED">
            <FeedbackPanel class="mb-2" />
            <div class="d-flex gap-2 justify-content-center">
                <ReviewRestoreButton />
            </div>
        </template>

        <template v-else-if="status === ORDER_STATUS.PENDING"></template>
        <template v-else-if="status === ORDER_STATUS.BUYER_REJECTED"></template>
        <template v-else-if="status === ORDER_STATUS.INELIGIBLE"></template>
        <template v-else-if="status === ORDER_STATUS.SCHEDULED"></template>
        <template v-else-if="status === ORDER_STATUS.CANCELED"></template>
        <template v-else-if="status === ORDER_STATUS.ERROR"></template>
        <template v-else-if="status === ORDER_STATUS.UNKNOWN"></template>
        <template v-else-if="status === ORDER_STATUS.PROCESSING"></template>
    </div>
</template>

<script setup>
import { inject, toRefs } from 'vue'
import { ORDER_STATUS } from '@/Rewards/variables'

import FeedbackPanel from './FeedbackPanel.vue'
import ReSendEmailButton from './Buttons/ReSendEmailButton.vue'
import SendEmailButton from './Buttons/SendEmailButton.vue'
import FinancialApproveButton from './Buttons/FinancialApproveButton.vue'
import FinancialRejectButton from './Buttons/FinancialRejectButton.vue'
import PlaceOrderButton from './Buttons/PlaceOrderButton.vue'
import ReviewApproveButton from './Buttons/ReviewApproveButton.vue'
import ReviewRejectButton from './Buttons/ReviewRejectButton.vue'
import ReviewRestoreButton from './Buttons/ReviewRestoreButton.vue'
import RedeemLinkButton from './Buttons/RedeemLinkButton.vue'

const approvalService = inject('approval-service')
const { loading, status } = toRefs(approvalService)
</script>

<style scoped></style>
