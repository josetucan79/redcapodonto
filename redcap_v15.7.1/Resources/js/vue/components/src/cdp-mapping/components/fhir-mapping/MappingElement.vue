<template>
    <div class="mapping-element">
        <span @click="onElementSelected(field)" :class="{active: active}" class="px-2 d-block mapping-label">
            <span class="fw-regular">• {{ field }}</span>
            <span class="ms-2 fst-italic">({{ description }})</span>
        </span>
        <template v-if="properties?.length > 0">
            <details>
                <summary class="small text-muted">Select property...</summary>
                <template v-for="property in properties" :key="property">
                    <MappingProperty
                        :field="field"
                        :property="property"
                        class="d-block ms-2"
                    />
                </template>
            </details>
        </template>
    </div>
</template>

<script setup>
import { computed, getCurrentInstance, inject, onMounted } from 'vue'
import MappingProperty from './MappingProperty.vue'

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
const isActive = inject('isActive')

const active = computed(() => {
    if (!isActive) return false
    return isActive(props)
})

// Inject the register function from Wrapper
const registerComponent = inject('registerComponent')
onMounted(() => {
    // Register this component with the Wrapper
    if (registerComponent) {
        const instance = getCurrentInstance()
        registerComponent(`Element-${props.field}`, instance, props)
    }
})
</script>

<style scoped>
.mapping-element > .mapping-label {
    cursor: pointer;
    word-break: break-word;
}
.active {
    background-color: #0d6efd;
    color: rgb(255 255 255);
}
</style>
