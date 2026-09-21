<template>
    <div data-wrapper>
        <span data-badge class="text-bg-light">
            <template v-if="status === 'loading'">
                <i class="fas fa-spinner fa-spin fa-fw"></i>
                <span></span>
            </template>
            <template v-else-if="status === ORDER_STATUS.COMPLETED">
                <i class="fas fa-circle-check fa-fw text-success"></i>
                <span>Completed</span>
            </template>
            <template v-else-if="status === ORDER_STATUS.ELIGIBLE">
                <i class="fas fa-clipboard fa-fw text-secondary"></i>
                <span>Eligible</span>
            </template>
            <template v-else-if="status === ORDER_STATUS.UNKNOWN">
                <i class="fas fa-circle-question fa-fw text-success"></i>
                <span>Unknown</span>
            </template>
            <template v-else-if="status === ORDER_STATUS.REVIEWER_APPROVED">
                <i class="fas fa-thumbs-up fa-fw text-success"></i>
                <span>{{ status }}</span>
            </template>
            <template v-else-if="status === ORDER_STATUS.REVIEWER_REJECTED">
                <i class="fas fa-thumbs-down fa-fw text-danger"></i>
                <span>{{ status }}</span>
            </template>
            <template v-else-if="status === ORDER_STATUS.BUYER_APPROVED">
                <i class="fas fa-thumbs-up fa-fw text-success"></i>
                <span>{{ status }}</span>
            </template>
            <template v-else-if="status === ORDER_STATUS.BUYER_REJECTED">
                <i class="fas fa-thumbs-down fa-fw text-danger"></i>
                <span>{{ status }}</span>
            </template>
            <template v-else-if="status === ORDER_STATUS.INELIGIBLE">
                <i class="fas fa-ban fa-fw text-danger"></i>
                <span>Ineligible</span>
            </template>
            <template v-else-if="status === ORDER_STATUS.INVALID">
                <i class="fas fa-triangle-exclamation fa-fw text-warning"></i>
                <span>Invalid</span>
            </template>
            <template v-else-if="status === ORDER_STATUS.SCHEDULED">
                <i class="fas fa-triangle-exclamation fa-fw text-warning"></i>
                <span>Scheduled</span>
            </template>
            <template v-else-if="status === ORDER_STATUS.ORDER_PLACED">
                <i class="fas fa-gift fa-fw text-primary"></i>
                <span>{{ status }}</span>
            </template>
            <template v-else>
                <i class="fas fa-ban fa-fw text-danger"></i>
                <span v-text="status"></span>
            </template>
        </span>
    </div>
</template>

<script setup>
import { ORDER_STATUS } from '../../variables'

const props = defineProps({
    status: { type: String, default: 'unknown' },
})
</script>

<style scoped>
:has([data-badge]) {
    display: flex;
    position: relative;
    height: 1.5em;
    width: 100%;
}
[data-badge] {
    font-size: 0.75em;
    display: grid !important;
    grid-template-columns: min-content 0fr;
    gap: 5px;
    align-items: center;
    border-radius: 0.375rem;
    padding: 0.35em 0.65em;
}
/* uncomment below to enable effect */
/* [data-badge] span {
    overflow: hidden;
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
} */

:has(td) td:hover [data-badge] {
    grid-template-columns: min-content 1fr;
}

:has(td) td:hover [data-badge] span {
    opacity: 1; /* Fade in text on hover */
    width: fit-content;
}
</style>
