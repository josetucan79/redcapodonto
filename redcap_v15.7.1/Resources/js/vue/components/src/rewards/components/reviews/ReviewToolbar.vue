<template>
    <div class="d-flex gap-2">
        <PaginationButtons
            size="sm"
            v-model="pagination.page"
            :perPage="pagination.perPage"
            :totalItems="pagination.total"
        />
        <div>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                :disabled="loading"
                @click="onRefreshClicked"
            >
                <template v-if="loading">
                    <i class="fas fa-spinner fa-spin fa-fw"></i>
                </template>
                <template v-else>
                    <i class="fas fa-refresh fa-fw"></i>
                </template>
            </button>
        </div>
        <b-pagination-dropdown
            :options="[25, 50, 100, 500]"
            v-model="pagination.perPage"
        />
        <FilterMenu />
        <BulkActionsMenu />
    </div>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { useRecordsStore } from '@/rewards/store'
import PaginationButtons from '@/shared/PaginationButtons.vue'
import BulkActionsMenu from './BulkActionsMenu.vue'
import FilterMenu from './FilterMenu.vue'

const recordsStore = useRecordsStore()
const loading = computed(() => recordsStore.loading)
const pagination = reactive(recordsStore.pagination)

function loadData() {
    recordsStore.loadRecords()
}

function onRefreshClicked() {
    loadData()
}
</script>

<style scoped></style>
