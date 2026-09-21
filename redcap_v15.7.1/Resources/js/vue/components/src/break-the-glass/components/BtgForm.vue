<template>
    <div>
        <div class="d-flex flex-column gap-2">
            <div>
                <label for="patients-list" class="form-label" v-tt:break_glass_field_patients />
                <PatientsList id="patients-list" />
            </div>
            <div>
                <label for="select-reason" class="form-label" v-tt:break_glass_field_reason />
                <select
                    class="form-select form-select-sm"
                    v-model="formData.Reason"
                    id="select-reason">
                    <option disabled>Please select a reason</option>
                    <template v-for="reason in Reasons" :key="reason">
                        <option :value="reason">{{ reason }}</option>
                    </template>
                </select>
            </div>
            <div>
                <label for="text-explanation" class="form-label" v-tt:break_glass_field_explanation />
                <textarea
                    class="form-control form-control-sm"
                    id="text-explanation"
                    rows="3"
                    v-model="formData.Explanation" />
            </div>
            <div>
                <label for="input-ehr-user" class="form-label" v-tt:break_glass_field_user />
                <input
                    type="text"
                    class="form-control form-control-sm"
                    id="input-ehr-user"
                    readonly
                    disabled
                    v-model="formData.UserID"
                />
            </div>
            <div>
                <label for="select-user-type" class="form-label" v-tt:break_glass_field_user_type />
                <select
                    class="form-select form-select-sm"
                    v-model="formData.UserIDType"
                    id="select-user-type"
                >
                    <option disabled>Please select a user type</option>
                    <template v-for="type in userTypes" :key="type">
                        <option :value="type">{{ type }}</option>
                    </template>
                </select>
            </div>
            <div>
                <label for="input-redcap-password" class="form-label" v-tt:break_glass_field_password />
                <input
                    type="password"
                    class="form-control form-control-sm"
                    id="input-redcap-password"
                    v-model="formData.password"
                />
            </div>
        </div>
        <!-- <div class="alert alert-warning my-2">
            <span>
                {{ LegalMessage }}
            </span>
        </div> -->
        <div class="d-flex justify-content-end mt-2">
            <button
                type="button"
                class="btn btn-sm btn-primary"
                @click="onBTGClicked"
                :disabled="loading || validation.hasErrors()"
            >
                <template v-if="loading">
                    <i class="fas fa-spinner fa-spin fa-fw"></i>
                </template>
                <template v-else>
                    <i class="fas fa-hammer fa-fw"></i>
                </template>
                <span class="ms-2">Break The Glass</span>
            </button>
        </div>
        <Teleport to="body">
            <b-modal ref="resultsRef" ok-only size="xl">
                <template #title>Response</template>
                <div>
                    <table class="table table-sm table-bordered table-hover table-striped">
                        <thead>
                            <tr>
                                <th>MRN</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(row, index) in results" :key="index">
                                <tr>
                                    <td>
                                        <span>{{ row.mrn }}</span>
                                    </td>
                                    <td>
                                        <span>{{ row.status }}</span>
                                    </td>
                                    <td>
                                        <span>{{ row.details }}</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </b-modal>
        </Teleport>
    </div>
</template>

<script setup>
import PatientsList from '@/break-the-glass/components/PatientsList.vue'
import { useSettingsStore, useFormStore, usePatientsStore } from '@/break-the-glass/store'
import { onMounted, ref, toRefs, watchEffect } from 'vue'

const settingsStore = useSettingsStore()
const patientsStore = usePatientsStore()
const formStore = useFormStore()
const resultsRef = ref()

const { LegalMessage, Reasons, userTypes, ehrUser, preferredUserType } = toRefs(settingsStore)
const { formData, loading, validation, results } = toRefs(formStore)
const { submit, updatePropertyWithoutValidation, runValidation } = formStore

async function onBTGClicked() {
    await submit()
    patientsStore.fetchProtectedPatients()
}

watchEffect(async () => {
    if (results.value.length === 0) return
    const modal = resultsRef.value
    await modal.show()
    results.value = [] // reset the results when closing
})

onMounted(() => {
    // set some form values using the settings
    updatePropertyWithoutValidation('UserID', ehrUser.value)
    updatePropertyWithoutValidation('UserIDType', preferredUserType.value)
    runValidation()
})
</script>

<style scoped>
label {
    font-weight: 700;
}
</style>
