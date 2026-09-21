<template>
    <span @click="onElementSelected(`${field}:${property}`)" data-property>
        <span class="fw-bold small fst-italic">{{ property }}</span>
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
[data-property] {
    cursor: pointer;
}
</style>