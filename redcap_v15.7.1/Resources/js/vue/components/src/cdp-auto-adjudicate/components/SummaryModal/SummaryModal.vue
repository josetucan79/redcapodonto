<template>
    <b-modal ref="modalRef" size="xl">
        <template #title>
            <span class="fw-bold">Summary</span>
        </template>
        <div>
            <table
                class="table table-bordered table-striped-table-hover table-sm"
            >
                <thead>
                    <tr>
                        <th>Total Fields</th>
                        <th>Processed</th>
                        <th>Successful</th>
                        <th>Errors</th>
                        <th>Adjudicated Values</th>
                        <th>Excluded Values <sup class="">1</sup></th>
                        <th>Unprocessed Values <sup class="">2</sup></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th>{{ stats.total_fields }}</th>
                        <th>{{ stats.processed_fields }}</th>
                        <th>{{ stats.successful_fields }}</th>
                        <th>{{ stats.errors }}</th>
                        <th>{{ stats.adjudicated_values }}</th>
                        <th>{{ stats.excluded_values }}</th>
                        <th>{{ stats.unprocessed_values }}</th>
                    </tr>
                </tbody>
            </table>
            <div class="border rounded bg-light p-2">
                <sup class="">1</sup>
                <span
                    >Values are excluded (not saved) in the adjudication process
                    if:</span
                >
                <ul>
                    <li>empty</li>
                    <li>matching existing values</li>
                    <li>
                        not the best option based on the 'preselect' mapping
                        rule
                    </li>
                </ul>
                <sup class="">2</sup>
                <span
                    >Unprocessed values have been skipped due to an error during
                    the adjudication process</span
                >
            </div>
            <div class="mt-2 d-flex flex-column gap-2">
                <div class="" v-if="errors">
                    <span class="fw-bold">Errors</span>
                    <ErrorsTable>
                        <template #footer>
                            <ExportErrorsButton :errors="errors" />
                        </template>
                    </ErrorsTable>
                </div>
            </div>
        </div>

        <template #footer="{ hide }">
            <button type="button" class="btn btn-sm btn-primary" @click="hide">
                <i class="fas fa-times-circle fa-fw me-1"></i>
                <span>Close</span>
            </button>
        </template>
    </b-modal>
</template>

<script setup>
import {
    computed,
    getCurrentInstance,
    inject,
    ref,
    toRaw,
    watchEffect,
} from 'vue'
import ErrorsTable from '../ErrorsTable.vue'
import ExportErrorsButton from '../ExportErrorsButton.vue'

const modalRef = ref()
const adjudicationStore = inject('adjudication-store')

const showSummaryModal = computed(
    () => adjudicationStore.showSummaryModal ?? false
)
const stats = computed(() => adjudicationStore.stats)
const errors = computed(() => adjudicationStore.errors)

const app = getCurrentInstance()
const sendSummaryClosedEvent = () => {
    const summaryDetail = {
        stats: toRaw(stats.value),
        errors: toRaw(errors.value),
        timestamp: Date.now(),
    }
    app.proxy.$eventBus.emit('summary-closed', summaryDetail)
}

watchEffect(async () => {
    if (!modalRef.value) return
    if (showSummaryModal.value === true) {
        await modalRef.value.show()
        sendSummaryClosedEvent()
    } else {
        modalRef.value.hide()
    }
})
</script>

<style scoped></style>
