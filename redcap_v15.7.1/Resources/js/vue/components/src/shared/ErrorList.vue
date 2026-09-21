<template>
    <div>
        <template v-for="(error, index) in errors" :key="index">
            <small class="d-block text-danger" v-html="error"></small>
        </template>
    </div>
</template>

<script setup>
const props = defineProps({
    errors: { type: Array, default: () => [] },
})
</script>

<style scoped></style>
