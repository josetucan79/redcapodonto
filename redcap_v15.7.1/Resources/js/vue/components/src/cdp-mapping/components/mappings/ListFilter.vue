<template>
    <b-dropdown ref="dropdownRef" variant="outline-secondary" size="sm">
        <template #button>
            <slot name="header"><NoSelection /></slot>
        </template>
        <template #default="{ show }">
            <RenderlessFilter
                v-model:query="searchQuery"
                ref="renderlessRef"
                :limit="limit"
                :list="list"
                :filterCallback="filterCallback"
                v-slot="{ filteredList, isEmpty, hasMore }"
            >
                <template v-if="show.value">
                    <div data-prevent-close class="px-2 results-wrapper">
                        <input
                            class="form-control form-control-sm"
                            v-model="searchQuery"
                            placeholder="Filter..."
                            type="search"
                        />
                        <div class="results">
                            <template v-if="isEmpty">
                                <span class="d-block p-2 fst-italic">No data</span>
                            </template>
                            <slot :filteredList="filteredList"></slot>
                        </div>
                        <template v-if="hasMore">
                            <span class="px-2 fst-italic text-muted">Refine your search for more results...</span>
                        </template>
                    </div>
                </template>
            </RenderlessFilter>

        </template>
    </b-dropdown>
</template>


<script setup>
import { ref } from 'vue'
import RenderlessFilter from '@/shared/RenderlessFilter/RenderlessFilter.vue'
import NoSelection from './NoSelection.vue'

const props = defineProps({
    limit: { type: Number, default: 50 },
    filterCallback: { type: Function, default: null },
    list: { type: [Object, Array], default: null },
})

const dropdownRef = ref()
const renderlessRef = ref()
const searchQuery = ref('')

const close = () => {
    dropdownRef.value.close()
}

defineExpose({ close })
</script>

<style scoped>
.results-wrapper {
    width: max-content;
    min-width: 300px;
    max-width: 500px;
}
.results {
    max-height: 400px;
    overflow-y: auto;
}
</style>
