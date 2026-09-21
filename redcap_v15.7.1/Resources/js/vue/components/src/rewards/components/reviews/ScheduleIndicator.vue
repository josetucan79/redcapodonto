<template>
    <div v-if="order?.scheduled_action" class="d-flex align-items-center text-muted schedule-badge">
        <span class="whitespace-nowrap">
            <i class="fas fa-clock fa-fw"></i>
            <span v-text="order?.scheduled_action ?? ''"></span>
        </span>
    </div>
</template>

<script setup>
const props = defineProps({
    order: { type: Object, default: null },
})
</script>

<style scoped>
.schedule-badge {
    font-size: 0.75em;
    align-items: center;
    border-radius: 0.375rem;
    white-space: nowrap;
}
</style>
