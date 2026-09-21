<template>
    <span @click="onElementSelected(`${field}:${property}`)" class="mapping-property">
        <span class="fw-regular small fst-italic">• {{ property }}</span>
    </span>
</template>

<script setup>
import { inject } from 'vue';

const props = defineProps({
    field: { type: String, default: '' },
    property: { type: String, default: '' },
})

const onElementSelected = inject('onElementSelected')
</script>

<style scoped>
.mapping-property {
    cursor: pointer;
}
</style>