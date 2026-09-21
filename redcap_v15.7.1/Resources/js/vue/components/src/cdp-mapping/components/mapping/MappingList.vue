<template>
    <MappingContainer :instance="container" open data-root-container />
</template>

<script setup>
import { computed, provide } from 'vue'
import MappingContainer from './MappingContainer.vue';
import { groupMetadata } from '@/utils/metadataUtils';

provide('onElementSelected', (element) => {
    console.log(element)
})

const props = defineProps({
    list: { type: Object, default: () => ({}) },
})

const container = computed(() => {
    const groups = groupMetadata(props.list)
    return groups
})

</script>

<style scoped>
/* hide the very first marker */
[data-root-container] > :deep(details:first-of-type > summary::marker) {
    content: '';
}
</style>
