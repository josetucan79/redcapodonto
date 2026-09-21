<template>
    <ActionButton :action="ACTION_EVENT.REVIEWER_REJECTION">
        <template #icon>
            <i class="fas fa-thumbs-down fa-fw text-danger"></i>
        </template>
        <template #text>Reject</template>
    </ActionButton>
</template>

<script setup>
import ActionButton from './ActionButton.vue'
import { ACTION_EVENT } from '@/Rewards/variables'
</script>

<style scoped></style>
