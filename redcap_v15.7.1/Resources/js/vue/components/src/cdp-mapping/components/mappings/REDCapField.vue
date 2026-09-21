<template>
    <FieldsDropdown v-model:field_name="field_name" :label="redcapFieldData.label" :form="redcapFieldData.form" :eventFields="eventFields"/>
</template>

<script setup>
import { inject, toRefs } from 'vue'
import FieldsDropdown from './FieldsDropdown.vue'

const localMappingService = inject('local-mapping-service')
const { field_name } = toRefs(localMappingService.value.field)
const { redcapFieldData, eventFields } = toRefs(localMappingService.value)
</script>

<style scoped></style>
