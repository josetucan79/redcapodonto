<template>
    <table class="table table-bordered table-striped table-hover table-sm">
        <thead>
            <tr>
                <th>
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="all-patients"
                        :checked="isAllSelected"
                        :indeterminate="isIndeterminate"
                        :disabled="patients.length === 0"
                        @change="toggleAll"
                    />
                </th>
                <th>
                    <div class="d-flex">
                        <span>MRN</span>
                        <div class="ms-auto">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary"
                                @click="onRefreshClicked"
                                :disabled="loading"
                            >
                                <template v-if="loading">
                                    <i class="fas fa-spinner fa-spin fa-fw"></i>
                                </template>
                                <template v-else>
                                    <i class="fas fa-refresh fa-fw"></i>
                                </template>
                            </button>
                        </div>
                    </div>
                </th>
            </tr>
        </thead>
        <tbody>
            <template v-for="mrn in patients" :key="mrn">
                <tr>
                    <td>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                :id="`patient-${mrn}`"
                                :value="mrn"
                                v-model="selected"
                            />
                        </div>
                    </td>
                    <td style="width: 100%">
                        <div class="d-flex">
                            <label
                                class="form-check-label"
                                :for="`patient-${mrn}`"
                            >
                                {{ mrn }}
                            </label>
                            <div class="ms-auto">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    @click="onDeleteClicked(mrn)"
                                >
                                    <i class="fas fa-trash fa-fw"></i>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            </template>
            <template v-if="patients.length === 0">
                <tr>
                    <td colspan="2">No MRNs</td>
                </tr>
            </template>
        </tbody>
    </table>
</template>

<script setup>
import { computed, ref, toRefs, onMounted } from 'vue'
import { usePatientsStore } from '../store'
import { useModal } from 'bootstrap-vue'

const modal = useModal()

// Access patients store directly
const patientsStore = usePatientsStore()
const { patients, selected, loading } = toRefs(patientsStore)
const { fetchProtectedPatients, toggleSelectMrn, removeMrn } = patientsStore

// Determine if all patients are selected
const isAllSelected = computed(() => {
    return patients.value.length > 0 && selected.value.length === patients.value.length
})

// Determine if the "all" checkbox is indeterminate
const isIndeterminate = computed(() => {
    return selected.value.length > 0 && selected.value.length < patients.value.length
})

// Toggle all selection
const toggleAll = () => {
    if (isAllSelected.value) {
        selected.value = []
    } else {
        selected.value = [...patients.value]
    }
}

// Refresh the list of patients
async function onRefreshClicked() {
    await fetchProtectedPatients()
}

// Handle MRN deletion
async function onDeleteClicked(mrn) {
    const confirmed = await modal.confirm({
        title: 'Confirm Delete',
        body: `Are you sure you want to delete MRN: ${mrn}?`,
    })
    if (confirmed) {
        await removeMrn(mrn)
    }
}

onMounted(() => {
    fetchProtectedPatients()
})
</script>

<style scoped></style>
