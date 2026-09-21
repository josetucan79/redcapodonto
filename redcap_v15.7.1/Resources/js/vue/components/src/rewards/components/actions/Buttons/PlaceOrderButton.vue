<template>
    <ActionButton :action="ACTION_EVENT.PLACE_ORDER">
        <template #icon>
            <i class="fas fa-gift fa-fw text-primary"></i>
        </template>
        <template #text>Place Order</template>
    </ActionButton>
</template>

<script setup>
import ActionButton from './ActionButton.vue'
import { ACTION_EVENT } from '@/Rewards/variables'
</script>

<style scoped></style>
