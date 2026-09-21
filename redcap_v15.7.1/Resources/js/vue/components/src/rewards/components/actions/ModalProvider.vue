<!-- ModalProvider.vue -->
<template>
    <div>
        <slot />
    </div>
</template>

<script setup>
import { provide, toRef } from 'vue'
import { useCurrentCellService } from '@/rewards/services'

const currentCellService = useCurrentCellService()

provide('approval-service', currentCellService)
</script>
