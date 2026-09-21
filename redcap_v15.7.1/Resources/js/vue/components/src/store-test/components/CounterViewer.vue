<template>
    <div class="d-inline-block">{{ counter }}</div>
</template>

<script setup>
const props = defineProps({
    counter: { type: Number, default: 0 },
})

</script>

<style scoped></style>
