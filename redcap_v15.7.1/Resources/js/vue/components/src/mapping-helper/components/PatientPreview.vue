<template>
    <div class="border rounded p-2">
        <template v-if="patient">
            <span class="fw-bold fs-3">{{
                `${patient?.['name-given']} ${patient?.['name-family']}`
            }}</span>
            <small class="d-block text-muted fst-italic total-entries">
                Total entries:
                <AnimatedCounter
                    :value="overallTotal"
                    v-slot="{ animating, text }"
                >
                    <span class="counter" :class="{ animating }">{{
                        parseInt(text)
                    }}</span></AnimatedCounter
                >
            </small>
            <div class="d-flex align-items-center gap-2">
                <small class="text-muted fst-italic">
                    <span class="fw-bold">FHIR ID</span>: {{ patient?.['fhir-id'] }}
                </small>
                <button
                    class="btn btn-sm btn-outline-primary"
                    @click="onCopyClicked(patient?.['fhir-id'])"
                    :disabled="!patient?.['fhir-id']"
                >
                    <i class="fas fa-copy fa-fw" />
                </button>
            </div>
            <div v-if="patient?.deceasedBoolean">
                <span class="badge bg-warning">
                    <span class="fw-bold">Deceased:</span>
                    <span>
                        {{
                            moment(patient?.deceasedDateTime).format(
                                'YYYY-MM-DD'
                            )
                        }}
                    </span>
                </span>
            </div>
            <div class="patient-details">
                <div>
                    <span class="fw-bold">Gender</span>: {{ patient?.gender }}
                </div>
                <!-- <div><span class="fw-bold">Email</span>: {{ patient?.email }}</div> -->
                <!-- <div><span class="fw-bold">Phone</span>: {{ patient['phone-home'] }}</div> -->
                <!-- <div>
                <span class="fw-bold">Address</span> :
                {{
                    `
                ${patient['address-line']}
                ${patient['address-district']}
                ${patient['address-city']}
                ${patient['address-state']}
                ${patient['address-postalCode']}
                ${patient['address-country']}
        `
                }}
            </div> -->
                <div>
                    <span class="fw-bold">DOB</span>: {{ patient?.birthDate }}
                </div>
                <div>
                    <span class="fw-bold">Age</span>:
                    {{ calculateTotalYears(patient?.birthDate) }}
                </div>
            </div>
        </template>
        <template v-else>
            <div style="max-width: 500px;">
                <span class="fw-bold fs-5 d-block mb-2">No preview available</span>
                <span class="fst-italic text-muted">To display a preview of the patient, select "Demographics" from the dropdown menu and click "Fetch resources."</span>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useSearchStore } from '../store'
import moment from 'moment'
import { useClipboard } from '../../utils/use'
import { useToaster } from 'bootstrap-vue'
import AnimatedCounter from '../../shared/AnimatedCounter.vue'

const searchStore = useSearchStore()
const toaster = useToaster()

const clipboard = useClipboard()

function calculateTotalYears(specificDate) {
    const currentDate = new Date()
    const startDate = new Date(specificDate)

    const yearsDiff = currentDate.getFullYear() - startDate.getFullYear()

    if (
        currentDate.getMonth() < startDate.getMonth() ||
        (currentDate.getMonth() === startDate.getMonth() &&
            currentDate.getDate() < startDate.getDate())
    ) {
        return yearsDiff - 1
    }

    return yearsDiff
}

const overallTotal = computed(() => searchStore.total)
const patient = computed(() => searchStore.patient)

async function onCopyClicked(fhirID) {
    try {
        await clipboard.copy(fhirID)
        toaster.toast({ title: 'Success', body: 'FHIR ID copied to clipboard' })
    } catch (error) {
        toaster.toast({
            title: 'Error',
            body: `FHIR ID NOT copied to clipboard - ${error}`,
        })
    }
}
</script>

<style scoped>
.patient-details {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(3, fit-content(250px));
}
/* .total-entries::before {
    content: '-';
    margin-left: 0.5rem;
} */
.counter {
    transition-property: transform font-weight;
    transition-timing-function: ease-in-out;
    transition-duration: 300ms;
    display: inline-block;
    transform-origin: center center;
}
.counter.animating {
    transform: scale(1.1);
    /* font-weight: 800; */
}
</style>
