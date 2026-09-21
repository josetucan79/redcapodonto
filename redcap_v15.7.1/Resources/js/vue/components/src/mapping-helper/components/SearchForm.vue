<template>
    <div class="d-flex">
        <div class="d-flex border p-2 rounded flex-column gap-2">
            <div class="d-flex align-items-center gap-2">
                <div>
                    <label class="form-label" for="mrn">MRN</label>
                    <input
                        class="form-control form-control-sm"
                        type="text"
                        id="mrn"
                        placeholder="MRN"
                        v-model="mrn"
                    />
                </div>
                <div>
                    <label class="form-label" for="date-from">Date from</label>
                    <input
                        type="date"
                        class="form-control form-control-sm"
                        id="date-from"
                        placeholder="date-from"
                        v-model="dateFrom"
                    />
                </div>
                <div>
                    <label class="form-label" for="date-to">Date to</label>
                    <input
                        type="date"
                        class="form-control form-control-sm"
                        id="date-to"
                        placeholder="date-to"
                        v-model="dateTo"
                    />
                </div>
            </div>
            <div class="d-flex">
                <div class="ms-auto">
                    <div class="btn-group">
                        <button
                            class="btn btn-sm btn-success"
                            @click="onFetchClicked"
                            :disabled="!mrn || searchStore.loading || selected?.length===0"
                        >
                            <template v-if="searchStore.loading">
                                <i
                                    class="fas fa-spinner fa-spin fa-fw me-1"
                                ></i>
                            </template>
                            <template v-else>
                                <i
                                    class="fas fa-cloud-arrow-down fa-fw me-1"
                                ></i>
                            </template>
                            <span>Fetch</span>
                            <span class="mx-1 fw-bold">{{selected?.length ?? 0}}</span>
                            <span>resource{{selected?.length===1 ? '' : 's'}}</span>
                        </button>
                        <CategoriesDropdown :categories="categories" v-model:selected="selected" variant="success"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useSearchStore, useSettingsStore } from '../store'
import CategoriesDropdown from './CategoriesDropdown.vue'

const searchStore = useSearchStore()
const settingsStore = useSettingsStore()

const categories = computed(() => settingsStore.categories)
const selected = computed({
    get: () => settingsStore.selectedCategories,
    set: (value) => settingsStore.selectedCategories = value,
})

const mrn = computed({
    get: () => searchStore.mrn,
    set: (value) => (searchStore.mrn = value),
})
const dateFrom = computed({
    get: () => searchStore.dateFrom,
    set: (value) => (searchStore.dateFrom = value),
})
const dateTo = computed({
    get: () => searchStore.dateTo,
    set: (value) => (searchStore.dateTo = value),
})

// mrn.value = 'bb1bb963-a13f-4725-92a9-63e579d228c5'

const selectFirstValidResult = () => {
    const totalResults = searchStore?.results?.length ?? 0
    if (totalResults === 0) return
    for (const result of searchStore.results) {
        if (result.error || result.total === 0) continue
        searchStore.setActive(result)
        break
    }
}

async function onFetchClicked() {
    searchStore.setActive(null)
    await searchStore.fetchAll(selected.value)
    // after everything is fetched, if nothing was selected, then select the first valid result
    if (searchStore.active === null) selectFirstValidResult()
}
</script>

<style scoped></style>
