<template>
    <div class="d-flex flex-column gap-2">
        <b-pagination v-model="selection.page" :totalItems="selection.total" :per-page="selection.limit" size="sm" />
        <table class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th>MRN</th>
                </tr>
            </thead>
            <tbody>
                <template v-for="mrn in selection.items" :key="mrn">
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <span>{{ mrn }}</span>
                                <div class="ms-auto">
                                    <button class="btn btn-sm" @click="onRemoveMrnClicked(mrn)">
                                        <i class="fas fa-trash fa-fw"></i>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
                <template v-if="!mrns.length">
                    <tr><td>nothing to show</td></tr>
                </template>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { ref, toRefs, watch } from 'vue'
import { usePagination } from '../../../utils/use'

const emit = defineEmits(['remove'])
const props = defineProps({
    mrns: { type: Array, default: () => [] },
})

const { mrns } = toRefs(props)

const selection = ref({})
watch(mrns, () => {
    let currentPage = selection.value?.page ?? 1
    selection.value = usePagination(mrns.value, { perPageOptions: [10] })
    const { totalPages = 0 } = selection.value
    // go to previous page if the current one is too high
    while (totalPages < currentPage) {
        currentPage--
        selection.value.page = currentPage
    }
})

function onRemoveMrnClicked(mrn) {
    emit('remove', mrn)
}
</script>

<style scoped></style>
