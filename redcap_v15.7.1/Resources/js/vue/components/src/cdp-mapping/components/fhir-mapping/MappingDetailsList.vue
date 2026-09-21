<template>
    <MappingList :list="list" v-slot="{ list }">
        <MappingContainer :instance="list" open data-root-container />
    </MappingList>
</template>

<script setup>
import MappingContainer from './MappingContainer.vue';
import MappingList from '@/cdp-mapping/components/mappings/Mappinglist.vue'

const props = defineProps({
    list: { type: Object, default: () => ({}) },
})
</script>

<style scoped>
/* hide the very first marker */
[data-root-container] > :deep(details:first-of-type > summary::marker) {
    content: '';
}
</style>
