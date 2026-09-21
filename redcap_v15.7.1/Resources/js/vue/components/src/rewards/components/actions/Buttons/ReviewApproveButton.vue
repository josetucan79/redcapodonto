<template>
    <ActionButton :action="ACTION_EVENT.REVIEWER_APPROVAL">
        <template #icon>
            <i class="fas fa-thumbs-up fa-fw text-success"></i>
        </template>
        <template #text>Approve</template>
    </ActionButton>
</template>

<script setup>
import ActionButton from './ActionButton.vue'
import { ACTION_EVENT } from '@/Rewards/variables'
</script>

<style scoped></style>
