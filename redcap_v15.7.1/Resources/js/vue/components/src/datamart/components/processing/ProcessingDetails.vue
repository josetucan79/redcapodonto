<template>
    <b-modal ref="modalRef" backdrop="static" size="lg">
        <template v-slot:title>
            <span>Fetching Data</span>
        </template>
        <div>
            <div class="my-2">
                <ProcessBar
                    :total="totalMRNs"
                    :errors="totalErrors"
                    :success="totalSuccess"
                />
            </div>
            <div class="d-flex gap-2 justify-content-between">
                <template v-if="processing">
                    <div v-if="currentMRN">
                        <span>
                            <i class="fas fa-spinner fa-spin fa-fw me-1"></i>
                        </span>
                        <span
                            >Processing <strong>{{ currentMRN }}</strong></span
                        >
                        <span class="text-muted">
                            - {{ totalProcessed }}/{{ totalMRNs }}</span
                        >
                    </div>
                    <div v-else>
                        <span>
                            <i class="fas fa-spinner fa-spin fa-fw me-1"></i>
                        </span>
                    </div>
                </template>
                <template v-else>
                    <div v-if="aborted">
                        <i class="fas fa-ban me-1 text-danger"></i>
                        <span>Cancelled</span>
                    </div>
                    <div v-else>
                        <i class="fas fa-check-circle me-1 text-success"></i>
                        <span>Completed</span>
                    </div>
                </template>
                <StopWatchDisplay
                    :time="elapsedTime"
                    class="text-muted fw-lighter fst-italic"
                />
            </div>
            <hr />
            <div>
                <span class="d-block fs-4 mb-2">Stats:</span>
                <StatsTable :stats="stats" />
                <span class="d-block fs-4 mb-2">Errors:</span>
                <ErrorsTable :errors="errors" />
            </div>
        </div>
        <template v-slot:footer>
            <div class="ms-auto">
                <template v-if="processing">
                    <button
                        class="btn btn-sm btn-secondary"
                        @click="onCancelClicked"
                    >
                        <i class="fas fa-ban me-1"></i>
                        <span>Cancel</span>
                    </button>
                </template>
                <template v-else>
                    <button
                        class="btn btn-sm btn-secondary"
                        @click="onCloseClicked"
                    >
                        <i class="fas fa-times me-1"></i>
                        <span>Close</span>
                    </button>
                </template>
            </div>
        </template>
    </b-modal>
</template>

<script setup>
import { computed, ref, watchEffect } from 'vue'
import { useProcessStore } from '@/datamart/store/'
import ProcessBar from './ProcessBar.vue'
import StatsTable from './StatsTable.vue'
import ErrorsTable from './ErrorsTable.vue'
import StopWatch from '@/utils/StopWatch'
import StopWatchDisplay from '../StopWatchDisplay.vue'

const processStore = useProcessStore()
const modalRef = ref()
const processing = computed(() => processStore.processing)
const aborted = computed(() => processStore.aborted)
const stats = computed(() => processStore.stats)
// list of all errors, not grouped by MRN
const errors = computed(() => {
    const _errors = []
    for (const [mrn, mrnErrors] of Object.entries(processStore.errors)) {
        for (const error of mrnErrors) {
            _errors.push({ mrn, error })
        }
    }
    return _errors
})
const totalErrors = computed(() => Object.keys(processStore.errors).length)
const totalSuccess = computed(() => processStore.success?.length)
const currentMRN = computed(() => processStore.currentMRN)
const totalMRNs = computed(() => processStore.totalMRNs)
const totalProcessed = computed(() => processStore.totalProcessed)
const elapsedTime = ref(0)
const stopWatch = new StopWatch((time) => (elapsedTime.value = time))

watchEffect(() => {
    if (processing.value === true) {
        stopWatch.reset()
        stopWatch.start()
        modalRef.value.show()
    } else {
        stopWatch.stop()
    }
})

function onCancelClicked() {
    processStore.stop()
}
function onCloseClicked() {
    modalRef.value.hide()
}
</script>

<style scoped>
/* hide the close button */
:deep(.modal-dialog .modal-header .btn-close) {
    pointer-events: none;
    display: none;
}
</style>
