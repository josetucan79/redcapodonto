<template>
    <div class="d-flex flex-column border rounded p-2 gap-2">
        <div class="d-flex gap-2">
            <div class="d-flex gap-2">
                <RotateResultsButton />
                <MappingStatusSelect />
                <QueryInput />
            </div>
            <div class="ms-auto">
                <ShowCodeButton />
            </div>
        </div>
        <div class="d-flex">
            <ResultsDropdown />
            <div class="d-flex gap-2 ms-auto">
                <b-pagination
                    size="sm"
                    :perPage="limit"
                    :totalItems="total"
                    v-model="page"
                ></b-pagination>
                <b-pagination-dropdown
                    :options="perPageOptions"
                    v-model="limit"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useSearchStore } from '../../store'
import ResultsDropdown from './ResultsDropdown.vue'
import RotateResultsButton from './RotateResultsButton.vue'
import ShowCodeButton from './ShowCodeButton.vue'
import QueryInput from './QueryInput.vue'
import MappingStatusSelect from './MappingStatusSelect.vue'

const searchStore = useSearchStore()
const limit = computed({
    get: () => searchStore.pagination?.limit,
    set: (value) => (searchStore.pagination.limit = value),
})
const page = computed({
    get: () => searchStore.pagination?.page,
    set: (value) => (searchStore.pagination.page = value),
})
const total = computed({
    get: () => searchStore?.pagination?.total,
})
const perPageOptions = computed({
    get: () => searchStore.pagination?.perPageOptions,
})
</script>

<style scoped></style>
