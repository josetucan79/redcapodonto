<template>
    <FieldsDropdown v-model:field_name="temporal_field" :label="temporalFieldData.label" :form="temporalFieldData.form" :eventFields="eventFieldsTemporal"/>
</template>

<script setup>
import { inject, toRefs } from 'vue'
import FieldsDropdown from './FieldsDropdown.vue'

const localMappingService = inject('local-mapping-service')

const { temporalFieldData, eventFieldsTemporal } = toRefs(localMappingService.value)
const { temporal_field } = toRefs(localMappingService.value.field)
</script>

<style scoped></style>
