<template>
    <b-dropdown class="fields-dropdown" variant="outline-secondary" size="sm" @close="onClosed">
        <template #button>
            <span v-if="field_name" class="d-inline-block text-start button-label">
                <span class="d-block">{{ label }}</span>
                <span class="d-block fw-bold">{{ field_name }}</span>
                <span class="d-block fst-italic">({{ form }})</span>
            </span>
            <NoSelection v-else />
        </template>
        <div>
            <div class="p-2" data-prevent-close>
                <input
                    class="form-control form-control-sm"
                    v-model="searchQuery"
                    placeholder="Filter..."
                    type="search"
                />
            </div>
            <div class="results">
                <b-dropdown-item
                    :active="!field_name"
                    @click="onFieldSelected(null)"
                    >-- none --</b-dropdown-item
                >
                <template
                    v-for="(formData, formKey, index) in eventFields"
                    :key="formKey"
                >
                    <RenderlessFilter
                        v-model:query="searchQuery"
                        :limit="50"
                        :list="formData.fields"
                        :filterCallback="filterCallback"
                        v-slot="{ filteredList, isEmpty, hasMore }"
                    >
                        <template>{{ updateIsEmpty(index, isEmpty) }}</template>
                        <template v-if="!isEmpty">
                            <b-dropdown-header class="bg-light">
                                <span class="fw-bold">{{ formKey }}</span>
                            </b-dropdown-header>
                            <template
                                v-for="(field, fieldKey) in filteredList"
                                :key="`field-${fieldKey}`"
                            >
                                <b-dropdown-item
                                    :active="fieldKey === field_name"
                                    @click="onFieldSelected(fieldKey)"
                                >
                                    <span class="d-block">{{ field }}</span>
                                    <span
                                        class="d-block text-muted fst-italic"
                                        >{{ fieldKey }}</span
                                    >
                                </b-dropdown-item>
                            </template>
                        </template>
                        <template v-if="hasMore">...</template>
                    </RenderlessFilter>
                </template>
                <template v-if="allEmpty">
                    <div data-prevent-close class="px-2">
                        <span class="fst-italic text-muted">No results...</span>
                    </div>
                </template>
            </div>
        </div>
    </b-dropdown>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import RenderlessFilter from '@/shared/RenderlessFilter/RenderlessFilter.vue'
import NoSelection from './NoSelection.vue'

const props = defineProps({
    label: { type: String, default: '' },
    form: { type: String, default: '' },
    eventFields: { type: Object, default: () => ({}) },
})

const isEmptyStates = ref([])
const updateIsEmpty = (index, isEmpty) => (isEmptyStates.value[index] = isEmpty)
const allEmpty = computed(() => isEmptyStates.value.every((isEmpty) => isEmpty))
watch(
    () => props.eventFields,
    (newFields) => {
        // Reset isEmptyStates array length to match eventFields count
        isEmptyStates.value = new Array(newFields.length).fill(false)
    },
    { immediate: true }
)

const field_name = defineModel('field_name', { type: String, default: '' })

function onFieldSelected(_field_name) {
    field_name.value = _field_name
    searchQuery.value = ''
}

function onClosed() {}

const searchQuery = ref('')

function filterCallback(value, re, key) {
    return value.match(re) || key.match(re)
}
</script>

<style scoped>
.fields-dropdown {
    --size: 400px;
}
.results {
    max-height: var(--size);
    overflow-y: auto;
}
.button-label,
.results li {
    display: block;
    max-width: var(--size);
    white-space: nowrap;
}
.button-label > *,
.results li :deep(.dropdown-item),
.results li :deep(.dropdown-item > *) {
    text-overflow: ellipsis;
    overflow: hidden;
}

</style>
