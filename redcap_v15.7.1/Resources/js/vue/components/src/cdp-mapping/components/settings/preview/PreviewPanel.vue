<template>
    <div>
        <span>
            <span v-tt:preview_fields_select_label></span>
            <span class="fst-italic mx-1">{{ ehrSystemName }}</span>
            <span v-tt:preview_fields_select_up_to></span>
        </span>

        <div class="d-flex flex-column gap-2 my-2">
            <template v-for="(field, index) in fields" :key="index">
                <PreviewField
                    :fields="fhir_fields"
                    :selected="fields[index]"
                    @remove="onRemoveField(index, $event)"
                    @update:selected="(value) => onUpdateSelected(index, value)"
                />
            </template>
            <!-- add disabled placehodlers -->
            <template
                v-for="index in MAX_PREVIEW_FIELDS - fields.length"
                :key="index"
            >
                <PreviewField :fields="[]" :selected="null" disabled style="opacity: 0"/>
            </template>
        </div>
        <div>
            <button class="btn btn-sm btn-primary" @click="onAddClicked">
                Add field
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import PreviewField from './PreviewField.vue'

const MAX_PREVIEW_FIELDS = 5

const appStore = inject('app-store')
const settingsStore = inject('settings-store')

const fields = computed({
    get: () => {
        const _fields = settingsStore.preview_fields ?? []
        return _fields
    },
    set: (value) => (settingsStore.preview_fields = [...value]),
})

const ehrSystemName = computed(
    () => appStore?.settings?.fhir_source_name ?? 'unknown'
)
const fhir_fields = computed(() => appStore?.settings?.fhir_fields ?? [])

function onAddClicked() {
    if (fields.value?.length >= MAX_PREVIEW_FIELDS) return
    fields.value.push(undefined)
}

function onRemoveField(index) {
    const _fields = [...fields.value]
    if (index < 0) return
    _fields.splice(index, 1)
    fields.value = [..._fields]
    console.log('removeisdfasd')
}

function onUpdateSelected(index, field) {
    const _fields = [...fields.value]
    if (index < 0 || index >= _fields.length) return
    _fields[index] = field
    fields.value = [..._fields] // Sync with the store
}
</script>

<style scoped></style>
