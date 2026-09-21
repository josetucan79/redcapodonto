<template>
    <div class="d-flex flex-column justify-content-center align-items-center" v-if="currentOrder">
        <ValueAmount :value="currentOrder?.reward_value" />
        <span class="fw-bold fs-6 text-muted">
            {{ currentOrder?.reward_name }}
        </span>
    </div>
</template>

<script setup>
import { inject, toRefs } from 'vue'
import ValueAmount from '../common/ValueAmount.vue'

const approvalService = inject('approval-service')
const { currentOrder = {} } = toRefs(approvalService)
</script>

<style scoped></style>
