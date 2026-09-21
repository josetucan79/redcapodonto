<template>
    <ActionButton :action="ACTION_EVENT.REVIEWER_RESTORE">
        <template #icon>
            <i class="fas fa-rotate-left fa-fw text-secondary"></i>
        </template>
        <template #text>Restore</template>
    </ActionButton>
</template>

<script setup>
import ActionButton from './ActionButton.vue'
import { ACTION_EVENT } from '@/Rewards/variables'
</script>

<style scoped></style>
