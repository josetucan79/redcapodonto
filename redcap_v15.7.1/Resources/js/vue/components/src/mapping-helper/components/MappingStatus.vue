<template>
    <div>
        <template v-if="mapped">
            <span class="badge rounded-pill text-bg-primary">Mapped</span>
        </template>
        <template v-else>
            <span class="badge rounded-pill text-bg-light">Not Mapped</span>
        </template>
    </div>
</template>
<script setup>
const props = defineProps({
    mapped: {
        type: Boolean,
    },
})
</script>

<style scoped></style>
