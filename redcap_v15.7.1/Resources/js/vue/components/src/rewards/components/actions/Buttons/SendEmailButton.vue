<template>
    <ActionButton :action="ACTION_EVENT.SEND_EMAIL">
        <template #icon>
            <i class="fas fa-envelope fa-fw"></i>
        </template>
        <template #text>Send Email</template>
    </ActionButton>
</template>

<script setup>
import ActionButton from './ActionButton.vue'
import { ACTION_EVENT } from '@/Rewards/variables'
</script>

<style scoped></style>
