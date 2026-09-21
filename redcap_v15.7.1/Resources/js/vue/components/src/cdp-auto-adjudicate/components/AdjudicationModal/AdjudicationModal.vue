<template>
    <b-modal ref="modalRef" backdrop="static" size="xl">
        <template #title>
            <span class="fw-bold">Adjudication</span>
        </template>
        <template #header-close-button>
            <span></span>
        </template>
        <div>
            <span
                >REDCap is adjudicating the pending data stored in the database
                using the CDP mapping configuration.</span
            >
            <div class="mt-2 d-flex flex-column gap-2">
                <div class="border rounded p-2">
                    <div class="fw-bold text-left">Values</div>
                    <ValuesProgressBar />
                </div>
                <div class="border rounded p-2">
                    <div class="fw-bold text-left">Fields</div>
                    <FieldsProgressBar />
                </div>
                <div
                    class="border rounded p-2"
                    v-if="processing && currentField"
                >
                    <span class="fw-bold">Processing</span>
                    <div>
                        <span>Record ID: </span>
                        {{ currentField.record }}
                    </div>
                    <div>
                        <span>Event ID: </span>
                        {{ currentField.event_id }}
                    </div>
                    <div>
                        <span>Field name: </span>
                        {{ currentField.field_name }}
                    </div>
                </div>
                <div class="border rounded p-2" v-if="errors">
                    <span class="fw-bold">Errors</span>
                    <ErrorsTable />
                </div>
            </div>
        </div>

        <template #footer="{ hide }">
            <template v-if="processing">
                <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    @click="onStopClicked"
                >
                    <i class="fas fa-stop fa-fw me-1"></i>
                    <span>Stop</span>
                </button>
            </template>
            <template v-else>
                <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    @click="hide"
                >
                    <i class="fas fa-times-circle fa-fw me-1"></i>
                    <span>Close</span>
                </button>
            </template>
        </template>
    </b-modal>
</template>

<script setup>
import { computed, inject, ref, watchEffect } from 'vue'
import FieldsProgressBar from './FieldsProgressBar.vue'
import ValuesProgressBar from './ValuesProgressBar.vue'
import ErrorsTable from '../ErrorsTable.vue'

const modalRef = ref()
const adjudicationStore = inject('adjudication-store')

const processing = computed(() => adjudicationStore.processing)
const showProcessingModal = computed(
    () => adjudicationStore.showProcessingModal ?? false
)
const currentField = computed(() => adjudicationStore.currentField)
const errors = computed(() => adjudicationStore.errors)

function onStopClicked() {
    adjudicationStore.stopProcess()
}

watchEffect(() => {
    if (!modalRef.value) return
    if (showProcessingModal.value === true) {
        modalRef.value.show()
    } else {
        modalRef.value.hide()
    }
})
</script>

<style lang="scss" scoped></style>
