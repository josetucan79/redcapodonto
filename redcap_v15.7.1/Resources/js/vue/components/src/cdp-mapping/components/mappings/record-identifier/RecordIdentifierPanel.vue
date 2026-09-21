<template>
    <div class="record-identifier-panel">
        <div class="p-2">
            <span class="fw-bold" v-tt:record_identifier_section_title>Record Identifier Field (Required):</span>
            <div>
                <span v-tt:record_identifier_description></span>
                <small class="d-block text-muted fst-italic mt-2">
                    <span v-tt:record_identifier_note>Adjudication note</span>
                </small>
            </div>
        </div>
        <div class="p-2 record-identifier-settings">
            <div>
                <label class="fw-bold" v-tt:record_identifier_redcap_event_label for="record-identifier-event"></label>
                <EventDropdown />
            </div>
            <div>
                <label class="fw-bold" v-tt:record_identifier_redcap_field_label for="record-identifier-field"></label>
                <REDCapField />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, inject, provide, watch } from 'vue'
import EventDropdown from '@/cdp-mapping/components/mappings/EventDropdown.vue'
import REDCapField from '@/cdp-mapping/components/mappings/REDCapField.vue'

const mappingService = inject('mapping-service')
const store = computed(() => mappingService.recordIdentifierStore)
// dynamically create the local approval service
provide('local-mapping-service', store)
watch(() => store.value.field,
() => {
    console.log(store.value.field)
}, {deep: true})
</script>

<style scoped>
.record-identifier-panel {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0;
}

.record-identifier-panel > :nth-child(2) {
    border-left: solid 1px #ccc;
}
.record-identifier-panel :deep(.dropdown > *),
.record-identifier-panel :deep(.dropdown .dropdown-toggle) {
    /* min-width: 60%; */
    /* margin: auto; */
}
.record-identifier-settings {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.record-identifier-settings label {
    display: block;
}
</style>