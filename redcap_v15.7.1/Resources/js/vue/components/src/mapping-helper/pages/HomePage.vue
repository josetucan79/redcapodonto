<template>
    <div>
        <div class="d-flex gap-2">
            <SearchForm />
            <PatientPreview class="flex-grow-1" />
        </div>
        <ResultsToolbar class="my-2" v-if="searchStore?.results?.length" />
        <div data-results class="my-2">
            <div class="alert alert-info" v-if="searchStore.hasFilters() > 0">
                <small>A filter has been applied to the entries</small>
                <button class="btn btn-sm btn-outline-secondary ms-2" @click="searchStore.removeFilters()">
                    <i class="fas fa-times fa-fw me-1"></i>
                    <span>Remove filter</span>
                </button>
            </div>
            <template v-if="results.length === 0">
                <div class="border rounded p-2">
                    <span class="fst-italic">no entries</span>
                </div>
            </template>
            <template v-else>
                <ResourceEntriesTable />
            </template>
        </div>

        <router-view></router-view>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import ResourceEntriesTable from '../components/ResourceEntriesTable.vue'
import ResultsToolbar from '../components/ResultsToolbar/ResultsToolbar.vue'
import SearchForm from '../components/SearchForm.vue'
import PatientPreview from '../components/PatientPreview.vue'
import { useSearchStore } from '../store'

const searchStore = useSearchStore()

// const results = ref(useResults())
const results = computed(() => {
    return searchStore.pagination?.items ?? []
})
</script>

<style scoped>
</style>
