<template>
    <div>
        <div class="d-flex gap-2 mb-2">
            <div>
                <button class="btn btn-sm btn-secondary" @click="onCancelClicked">
                    <i class="fas fa-chevron-left fa-fw me-1"></i>
                    <span>Back</span>
                </button>
            </div>
            <div class="ms-auto">
                <FetchButton :mrns="selected" />
            </div>
        </div>

        <div class="card">
            <div class="card-header border-bottom mb-2">
                <span class="fw-bold">Select fetchable MRNs available in the project and fetch their data</span>
            </div>
            <section class="card-body">

                <section class="mrn-results">
                    <MRNsResultsTable v-model:mrns="list" v-model:selected="selected" />
                </section>

                <section class="selected-mrns border-top pt-2">
                    <div class="d-flex">
                        <div class="mb-2 ms-auto">
                            <div class="d-flex align-items-center gap-2 ms-auto">
                                <span>Total MRNs selected: <strong>{{ selected.length }}</strong></span>
                                <button class="btn btn-sm btn-danger" @click="onClearQueueClicked">
                                    <i class="fas fa-trash fa-fw me-1"></i>
                                    <span>Clear selection</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <SelectedMRNsTable v-model:mrns="selected" @remove="onMrnRemoved"/>
                </section>
            </section>
        </div>
    </div>
    <ProcessingDetails />
</template>

<script setup>
import { useRouter } from 'vue-router'
import { ref } from 'vue'
import MRNsResultsTable from '../components/search/MRNsResultsTable.vue'
import SelectedMRNsTable from '../components/search/SelectedMRNsTable.vue'
import FetchButton from '../components/buttons/FetchButton.vue'
import ProcessingDetails from '../components/processing/ProcessingDetails.vue'

const router = useRouter()
const list = ref([]) // list of MRNs to display
const selected = ref([]) // list of selected MRNs

function resetSelection() {
    selected.value = []
}

function onCancelClicked() {
    router.push({ name: 'home' })
}

function onClearQueueClicked() {
    resetSelection()
}

function onMrnRemoved(mrn) {
    const _list = selected.value
    if (!Array.isArray(_list)) return
    const index = _list.indexOf(mrn)
    if (index < 0) return
    _list.splice(index, 1)
    selected.value = _list
}
</script>

<style scoped>
.tables {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.235rem;
}
</style>
