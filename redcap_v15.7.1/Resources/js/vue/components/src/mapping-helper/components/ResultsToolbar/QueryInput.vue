<template>
    <div class="d-flex align-items-center gap-2">
        <input
            class="form-control form-control-sm"
            type="search"
            name="query"
            placeholder="search..."
            v-model="query"
        />
        <span v-if="queryError" :title="queryError" class="text-danger">
            <i class="fas fa-info-circle fa-fw"></i>
        </span>
    </div>
</template>

<script setup>
import { useSearchStore } from '../../store'
import { debounce } from '../../../utils'
import { computed } from 'vue'

const searchStore = useSearchStore()
const debounceQuery = debounce((value) => {
    searchStore.setQuery(value)
}, 300)

const queryError = computed(() => searchStore.queryError)

const query = computed({
    get: () => searchStore.query,
    set: (value) => debounceQuery(value),
})
</script>

<style scoped></style>
