<template>
    <div class="fhir-fields">
        <ListFilter
            ref="filterRef"
            :list="nonIdentifierFields"
            :limit="-1"
            :filterCallback="filterCallback"
        >
            <template #header>
                <div
                    class="d-inline-block text-start"
                    v-if="externalSourceFieldData.mapping_id"
                >
                    <span class="d-block">{{
                        externalSourceFieldData.label
                    }}</span>
                    <span class="d-block fw-bold">{{
                        externalSourceFieldData.mapping_id
                    }}</span>
                    <span
                        class="d-block text-muted fst-italic"
                        v-if="externalSourceFieldData.description"
                        >({{ externalSourceFieldData.description }})</span
                    >
                </div>
            </template>
            <template #default="{ filteredList }">
                <FhirMappinglist
                    :list="filteredList"
                    @onSelected="onFhirElementSelected"
                />
            </template>
        </ListFilter>
        <template v-if="disabledReason">
            <span class="alert alert-warning d-block mt-2 small">
                <small>
                    {{ disabledReason }}
                </small>
            </span>
        </template>
    </div>
</template>

<script setup>
import { computed, inject, provide, ref, toRefs } from 'vue'
// import FhirMappinglist from '../fhir-mapping/MappingList.vue'
import FhirMappinglist from '../fhir-mapping/SuperMappingContainer.vue'
import ListFilter from './ListFilter.vue'

const filterRef = ref()
const localMappingService = inject('local-mapping-service')
const appStore = inject('app-store')

const { external_source_field_name } = toRefs(localMappingService.value.field)
const { externalSourceFieldData } = toRefs(localMappingService.value)
const { fhir_fields } = toRefs(appStore.settings)
appStore.settings

// get only non identifiers
const nonIdentifierFields = computed(() => {
    const list = Object.values(fhir_fields.value ?? {}).filter(
        (metadata) => !metadata.identifier
    )
    return list
})

const disabledReason = computed(() => {
    const {disabled, disabled_reason} = isDisabled(externalSourceFieldData.value?.mapping_id)
    if (!disabled) return
    return disabled_reason
})

const isDisabled = (field) => {
    let disabled, disabled_reason
    const metadata = fhir_fields.value?.[field] ?? {}
    disabled = metadata?.disabled ?? false
    if (disabled) disabled_reason = metadata?.disabled_reason ?? 'disabled'
    return { disabled, disabled_reason }
}

function onFhirElementSelected(fhir_mapping_id) {
    external_source_field_name.value = fhir_mapping_id
    filterRef.value.close()
}

const filterCallback = (metadata, re) => {
    return (
        metadata.field.match(re) ||
        metadata.category.match(re) ||
        metadata.subcategory.match(re) ||
        metadata.label.match(re) ||
        metadata.description.match(re)
    )
}

const registerComponent = (identifier, instance, props) => {
    const { disabled, disabled_reason } = isDisabled(props?.field)
    if (disabled) {
        const rootElement = instance?.proxy?.$el
        rootElement.childNodes.forEach((element) => {
            if (element?.style) element.style.textDecoration = 'line-through'
        })
        // Disable pointer events to prevent interaction
        // rootElement.style.pointerEvents = 'none'
        // Create a span element to display the disabled reason
        const span = document.createElement('span')
        span.classList.add('alert', 'alert-warning', 'mt-2')
        span.style.display = 'block'
        span.style.fontStyle = 'italic'
        span.innerHTML = disabled_reason
        // Append the span to the root element
        rootElement.appendChild(span)
    }
}
provide('registerComponent', registerComponent)
provide('isActive', (_props) => {
    return externalSourceFieldData.value.mapping_id === _props.field
})
</script>

<style scoped>
.fhir-fields {
    --padding-size: 5px;
}
.fhir-fields :deep([data-container]) {
    padding-top: var(--padding-size);
    padding-bottom: var(--padding-size);
}
.fhir-fields :deep([data-container] + [data-container]) {
    border-top: solid 1px rgb(0 0 0 / 0.2);
}
</style>
