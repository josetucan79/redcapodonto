<template>
    <div class="btn-group">
        <button
            type="button"
            class="btn btn-sm btn-primary"
            @click="onProcessClicked"
            :disabled="loading"
        >
            <i class="fas fa-circle-check fa-fw me-1"></i>
            <span>Process</span>
        </button>
        <b-dropdown size="sm" :disabled="loading">
            <template #button></template>
            <template #default>
                <div data-prevent-close class="px-2" style="width: 400px">
                    <span class="d-block fw-bold"
                        >Background Processing Options</span
                    >
                    <div class="small text-muted fw-lighter">
                        When a large number of values needs to be processed it
                        is possible to start the process in background and get a
                        message when completed.
                    </div>
                    <div class="my-2">
                        <button
                            type="button"
                            class="btn btn-sm btn-primary"
                            @click="onScheduleClicked"
                        >
                            <i class="fas fa-circle-check fa-fw me-1"></i>
                            <span>Process in background</span>
                        </button>
                    </div>
                    <div class="form-check form-switch">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            id="send-message-checkbox"
                            v-model="sendMessage"
                        />
                        <label
                            class="small form-check-label"
                            for="send-message-checkbox"
                        >
                            <i class="fas fa-envelope fa-fw me-1"></i>
                            <span>Send me a message when completed</span>
                        </label>
                    </div>
                </div>
            </template>
        </b-dropdown>
    </div>
</template>

<script setup>
import { computed, inject, ref } from 'vue'
import { useDialog, useToaster } from 'bootstrap-vue'

const recordsMetadataStore = inject('records-store')
const adjudicationMetadataStore = inject('adjudication-store')
const dialog = useDialog()
const toaster = useToaster()

const sendMessage = ref(false)
const loading = computed(
    () => recordsMetadataStore.loading || adjudicationMetadataStore.loading
)

const showConfirmationDialog = async () => {
    const confirmed = await dialog.confirm({
        title: 'Please confirm',
        body: `You are about to initiate the adjudication process. Please note that existing data may be overwritten based on the 'preselect strategy' defined in your mapping configuration.`,
    })
    return confirmed
}

async function onProcessClicked() {
    const confirmed = await showConfirmationDialog()
    if (!confirmed) return
    await adjudicationMetadataStore.process()
    recordsMetadataStore.getRecords(1)
}
async function onScheduleClicked() {
    const confirmed = await showConfirmationDialog()
    if (!confirmed) return
    const response = await adjudicationMetadataStore.schedule(sendMessage.value)
    if (response)
        toaster.toast({
            title: 'Success',
            body: 'Auto-adjudication has been added to the background queue.',
        })
}
</script>

<style scoped>
.btn-group :deep(.dropdown button.dropdown-toggle) {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}
</style>
