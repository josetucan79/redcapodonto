<template>
    <template v-if="orders">
        <div data-actions>
            <button
                class="btn btn-xs btn-outline-secondary"
                @click="showModal"
                :disabled="Gate.denies('view_orders')"
            >
                <i class="fas fa-gift fa-fw"></i>
                <!-- <span>View order</span> -->
            </button>
        </div>
        <b-modal ref="modalRef" size="md">
            <template #header>
                <div class="flex-fill text-center">
                    <span class="fs-4 fw-bold text-muted">
                        {{ order.reward_name }}
                    </span>
                </div>
            </template>
            <div v-if="order && Gate.allows('view_orders')">
                <OrderCard />
            </div>
            <template #footer>
                <OrderActions class="my-2" />
            </template>
        </b-modal>
    </template>
</template>

<script setup>
import { computed, ref, toRefs, provide } from 'vue'
import Gate from '@/rewards/utils/Gate'
import OrderCard from './OrderCard.vue'
import OrderActions from './OrderActions.vue'

const props = defineProps({
    orders: { type: Array },
    review: { type: Object },
    record: { type: Object },
})

const index = ref(0)

const { orders, record, review } = toRefs(props)
const order = computed(() => orders.value?.[index.value])

provide('record', record)
provide('orders', orders)
provide('order', order)
provide('review', review)

const modalRef = ref()

function showModal() {
    if (!modalRef.value) return
    modalRef.value.show()
}
</script>

<style scoped>
:deep(.modal-body) {
    padding: 0;
}
:has(td) [data-actions] {
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}
:has(td) td:hover [data-actions] {
    opacity: 1;
}
</style>
