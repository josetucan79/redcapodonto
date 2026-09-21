<template>
    <div v-show="filter()">
        <slot></slot>
    </div>
</template>

<script setup>
const props = defineProps({
    filter: { type: Function, default: () => true },
})
</script>

<style scoped></style>
