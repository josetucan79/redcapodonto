<template>
    <slot
        :filteredList="filteredList"
        :query="query"
        :isEmpty="isEmpty"
        :hasMore="hasMore"
    />
</template>

<script setup>
import { useFilter } from './useFilter.js'

const props = defineProps({
    limit: { type: Number, default: -1 },
    filterCallback: { type: Function, default: null },
    list: { type: [Object, Array], default: () => [] },
})

const query = defineModel('query', { type: String, default: '' })
const { filteredList, isEmpty, hasMore } = useFilter(
    props.list,
    props.filterCallback,
    query,
    { limit: props.limit }
)

defineExpose({ query, filteredList, isEmpty, hasMore })
</script>
