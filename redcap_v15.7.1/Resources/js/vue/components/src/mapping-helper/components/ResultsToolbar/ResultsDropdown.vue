<template>
    <b-dropdown size="sm">
        <template #button>
            <template v-if="!active">
                <span>Select a resource...</span>
            </template>
            <template v-else>
                {{ active.category }}
            </template>
        </template>

        <template v-for="(result, index) in results" :key="index">
            <b-dropdown-item
                :active="active == result"
                @click="onresultClicked(result)"
                :disabled="result.loading"
            >
                <div class="text-nowrap d-flex align-items-center">
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
                                result?.data?.data?.length
                            }}</span>
                        </template>
                    </span>
                </div>
            </b-dropdown-item>
        </template>
        <b-dropdown-divider />
        <b-dropdown-item>
            <span class="d-flex align-items-center">
                <span class="fw-bold">Total</span>
                <span class="ms-auto">
                    <span class="badge bg-primary rounded-pill">{{
                        overallTotal
                    }}</span>
                </span>
            </span>
        </b-dropdown-item>
    </b-dropdown>
    <b-modal :ref="modalInfo?.ref" size="xl" class="modal-error" ok-only>
        <template #title>Error</template>
        <div v-html="modalInfo?.message()"></div>
    </b-modal>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useSearchStore } from '../../store'

const searchStore = useSearchStore()
const active = computed(() => searchStore.active)
const emit = defineEmits(['result-selected'])

const results = computed(() => searchStore.results)
const overallTotal = computed(() => searchStore.total)

const useErrorModal = () => {
    const errorModal = ref('errorModal')
    const message = ref('')

    const show = async (error) => {
        message.value =
            error?.message || 'there was an error fetching this resource'
        if (typeof error === 'string' || error instanceof String) {
            if (error.trim() !== '') message.value = error
        }
        errorModal.value.show()
    }
    return { show, ref: errorModal, message: () => message.value }
}

const modalInfo = useErrorModal()

function onresultClicked(resource) {
    if (resource?.error) {
        modalInfo.show(resource.error)
        return
    }
    if (resource?.loading) return
    // emit('result-selected', resource)
    searchStore.setActive(resource)
}
</script>

<style scoped>
.modal-error :deep(.modal-body) {
    white-space: pre-wrap;
    word-break: break-all;
}
</style>
