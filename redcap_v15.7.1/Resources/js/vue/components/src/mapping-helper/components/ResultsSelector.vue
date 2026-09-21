<template>
    <div class="list-group">
        <button
            class="list-group-item list-group-item-action text-nowrap d-flex align-items-center"
            v-for="(result, index) in results"
            :class="{
                // 'list-group-item-light': index % 2 === 0,
                active: active == result,
            }"
            :key="index"
            @click="onresultClicked(result)"
            :disabled="result.loading"
        >
            <span class="me-2">{{ result.category }}</span>
            <span class="ms-auto">
                <template v-if="result.loading">
                    <span>
                        <i class="fas fa-spinner fa-spin fa-fw"></i>
                    </span>
                </template>
                <template v-else-if="result?.error">
                    <span class="badge bg-danger rounded-pill">error</span>
                </template>
                <template v-else>
                    <span class="badge bg-primary rounded-pill">{{
                        result?.data?.data.length
                    }}</span>
                </template>
            </span>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useSearchStore } from '../store'
import { useModal } from 'bootstrap-vue'

const searchStore = useSearchStore()
const modal = useModal()

const props = defineProps({
    active: { type: Object },
    // results: { type: Array, default: () => [] },
})
const emit = defineEmits(['result-selected'])

const results = computed(() => {
    return searchStore.results
})

function showError(error) {
    const message =
        error?.message || 'there was an error fetching this resource'
    modal.alert({ title: 'Error', body: message })
}

function onresultClicked(resource) {
    if (resource?.error) showError(resource.error)
    if (resource?.loading) return
    emit('result-selected', resource)
}
</script>

<style scoped></style>
