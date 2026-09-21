<template>
    <div class="file">
        <small class="text-muted">{{ data.name }} - {{ data.size }}</small>
    </div>
</template>

<script setup>
const props = defineProps({
    data: { type: Object },
})
</script>

<style scoped></style>
