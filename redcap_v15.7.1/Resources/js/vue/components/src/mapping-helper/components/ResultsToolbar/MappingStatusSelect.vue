<template>
    <b-dropdown
        size="sm"
        :disabled="uniqueStatuses?.length === 0"
        variant="outline-primary"
    >
        <template #button>Mapping status</template>
        <div data-prevent-close class="p-2">
            <template
                v-for="(uniqueStatus, index) in uniqueStatuses"
                :key="index"
            >
                <div class="form-check form-switch">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        :id="`status-${index}`"
                        :value="uniqueStatus"
                        v-model="selectedStatuses"
                    />
                    <label class="form-check-label" :for="`status-${index}`">{{
                        uniqueStatus
                    }}</label>
                </div>
            </template>
        </div>
    </b-dropdown>
</template>

<script setup>
import { computed } from 'vue'
import { useSearchStore } from '../../store'

const searchStore = useSearchStore()
const entries = computed(() => {
    return searchStore.active?.data?.data ?? []
})

const mappingStatuses = computed(() =>
    entries.value.map(({ mapping_status }) => mapping_status ?? [])
)

const uniqueStatuses = computed(() => {
    const statusesSet = new Set(
        mappingStatuses.value?.map(({ status }) => status)
    )
    return Array.from(statusesSet)
})

const selectedStatuses = computed({
    get: () => searchStore.visibleStatuses,
    set: (value) => {
        searchStore.setVisibleStatuses([...value])
    },
})

/* const selectedStatuses = ref([])
watchEffect(() => {
    search.visibleStatuses = Array.from(selectedStatuses.value ?? [])
}) */
</script>

<style scoped></style>
