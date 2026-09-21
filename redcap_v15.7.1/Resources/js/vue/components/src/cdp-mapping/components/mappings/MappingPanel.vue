<template>
    <div class="my-2 rounded border">
        <RecordIdentifierPanel />
    </div>
    <MappingTable />
</template>

<script setup>
import MappingTable from '@/cdp-mapping/components/mappings/MappingTable.vue'
import RecordIdentifierPanel from '@/cdp-mapping/components/mappings/record-identifier/RecordIdentifierPanel.vue'
</script>

<style scoped></style>
