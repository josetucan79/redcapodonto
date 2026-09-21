<template>
    <template v-if="order">
        <div class="d-flex flex-column align-items-center">
            <span class="d-block"
                >Granted to
                <a :href="record?.link" target="_blank">{{
                    record.preview
                }}</a></span
            >
            <small class="d-block small text-muted fst-italic">
                <span
                    >created:
                    {{ getFormattedDate(order.created_at?.date) }}</span
                >
            </small>
            <ValueAmount :value="order.reward_value" />
            <small class="d-block small text-muted text-center">
                <details>
                    <summary>Logic at approval:</summary>
                    <code>{{ review?.eligibility_logic }}</code>
                </details>
                <details>
                    <summary>Order Details</summary>
                    <div>
                        <span>Order #{{ order.reference_order }}</span>
                    </div>
                    <div>
                        <span class="d-block text-muted"
                            >Internal code #{{ order.internal_reference }}</span
                        >
                    </div>
                </details>
            </small>
        </div>

    </template>
</template>

<script setup>
import moment from 'moment'
import { computed, ref, inject } from 'vue'
import { useAppStore } from '@/rewards/store'
import ValueAmount from '@/rewards/components/common/ValueAmount.vue'
import { useClipboard } from '@/utils/use'
import { useToaster } from 'bootstrap-vue'

const appStore = useAppStore()
const clipboard = useClipboard()
const toaster = useToaster()

// const DATE_FORMAT = 'YYYY-MM-DD HH:mm'
const DATE_FORMAT = 'lll'

/* const props = defineProps({
    order: { type: Object },
    record: { type: Object },
}) */

const order = inject('order')
const record = inject('record')
const review = inject('review')

const modalRef = ref()

function getFormattedDate(dateString) {
    const date = moment(dateString)
    const formatted = date.format(DATE_FORMAT)
    return formatted
}



</script>

<style scoped>
:deep(.modal-content) {
    width: min-content;
    margin: auto;
}
</style>
