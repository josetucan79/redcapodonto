<template>
    <div class="d-flex">
        <div>
            <button class="btn btn-sm btn-outline-success" @click="onAddParameterClicked">
                <i class="fas fa-circle-plus fa-fw me-1"></i>
                <span>Add query parameter</span>
            </button>
        </div>
        <div class="d-flex gap-2 align-items-center ms-auto">
            <SavedRequestsDropdown />
            <button class="btn btn-sm btn-outline-primary" @click="onFetchClicked" :disabled="fetchDisabled">
                <i v-if="loading" class="fas fa-spinner fa-spin fa-fw me-1"></i>
                <i v-else class="fas fa-cloud-arrow-down fa-fw me-1"></i>
                <span>Fetch</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useCustomRequestStore } from '../../store'
import SavedRequestsDropdown from './SavedRequestsDropdown.vue'

const customRequestStore = useCustomRequestStore()
const loading = computed(() => customRequestStore.loading)

const fetchDisabled = computed(() => {
    return customRequestStore.relativeURL.trim() === ''
})

function onFetchClicked() {
    customRequestStore.fetch()
}
function onAddParameterClicked() {
    customRequestStore.addParameter()
}
</script>

<style scoped></style>
