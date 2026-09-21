<template>
    <div data-element>
        <span @click="onElementSelected(field)" data-label>
            <span class="fw-bold">{{ field }}</span>
            <span class="ms-2 fst-italic">({{ description }})</span>
        </span>
        <template v-if="properties?.length > 0">
            <details>
                <summary class="small text-muted">Select property...</summary>
                <template v-for="property in properties" :key="property">
                    <MappingProperty :field="field" :property="property" class="d-block ms-2"/>
                </template>
            </details>
        </template>
    </div>
</template>

<script setup>
import { inject } from 'vue'
import MappingProperty from './MappingProperty.vue';

const props = defineProps({
    category: { type: String, default: '' },
    description: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    disabled_reason: { type: String, default: '' },
    field: { type: String, default: '' },
    identifier: { type: Boolean, default: false },
    label: { type: String, default: '' },
    properties: { type: Array, default: () => [] },
    subcategory: { type: String, default: '' },
    temporal: { type: Boolean, default: false },
})
const onElementSelected = inject('onElementSelected')
</script>

<style scoped>
[data-element] > [data-label] {
    cursor: pointer;
}
</style>
