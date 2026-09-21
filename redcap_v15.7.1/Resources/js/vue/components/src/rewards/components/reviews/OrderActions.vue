<template>
    <div data-actions class="d-flex align-items-start">
        <button
            type="button"
            class="btn btn-xs btn-outline-secondary"
            @click="
                emit('place-order', record_id, reward_option.reward_option_id)
            "
            :disabled="Gate.denies('place_orders')"
        >
            <i class="fas fa-cart-shopping fa-fw text-primary"></i>
            <!-- <span>Approve</span> -->
        </button>
    </div>
</template>

<script setup>
import Gate from '@/rewards/utils/Gate'

const props = defineProps({
    reward_option: { type: Object },
    record_id: { type: [Number, String] },
})

const emit = defineEmits(['place-order'])
</script>

<style scoped></style>
