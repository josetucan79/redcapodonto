<template>
    <ListViewer :total-items="orders?.length">
        <template #default="{ index, prev, next, totalItems }">
            <OrderCard v-if="order = orders?.[index.value]" :order="order" :record="record" />
            <div v-if="totalItems" class="d-flex align-items-center justify-content-center">
                <button class="btn btn-xs btn-outline-secondary" type="button" @click="prev">prev</button>
                <button class="btn btn-xs btn-outline-secondary" type="button" @click="next">next</button>
            </div>
        </template>
        <template #footer>
            <div></div>
        </template>
    </ListViewer>
</template>

<script setup>
import ListViewer from '@/rewards/components/common/ListViewer.vue'
import OrderCard from './OrderCard.vue'
import { ref, toRefs, computed } from 'vue'
const props = defineProps({
    orders: { type: Array, default: () => [] },
    record: { type: Object },
})

const { orders, record } = toRefs(props)
</script>

<style scoped></style>
