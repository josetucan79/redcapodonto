<template>
    <div>
        <div class="form-check">
            <input
                class="form-check-input"
                type="radio"
                id="manual-method"
                :value="ADJUDICATION_METHOD.MANUAL"
                v-model="method"
            />
            <label class="form-check-label" for="manual-method">
                <span>Manual</span>
                <small class="d-block text-muted fst-italic">
                    <span v-tt:adjudication_metod_manual_description />
                </small>
            </label>
        </div>
        <div class="form-check">
            <input
                class="form-check-input"
                type="radio"
                id="instant-method"
                :value="ADJUDICATION_METHOD.INSTANT"
                v-model="method"
                :disabled="!instantAdjudicationCanBeEnabled"
            />
            <label class="form-check-label" for="instant-method">
                <span>Instant</span>
                <small class="d-block text-muted fst-italic">
                    <span v-tt:adjudication_metod_instant_description />
                </small>
            </label>
        </div>
        <div class="form-check">
            <input
                class="form-check-input"
                type="radio"
                id="auto-method"
                :value="ADJUDICATION_METHOD.AUTO"
                v-model="method"
                :disabled="!instantAdjudicationCanBeEnabled"
            />
            <label class="form-check-label" for="auto-method">
                <span>Auto</span>
                <small class="d-block text-muted fst-italic">
                    <span v-tt:adjudication_metod_automatic_description />
                </small>
            </label>
        </div>
    </div>
</template>

<script setup>
import { computed, inject, toRefs, watch, watchEffect } from 'vue'
import { ADJUDICATION_METHOD } from '@/cdp-mapping/constants'

const settingsStore = inject('settings-store')
const mappingService = inject('mapping-service')

const { adjudication_method: method } = toRefs(settingsStore)
const {
    allTemporalFieldsSetForInstantAdjudication: instantAdjudicationCanBeEnabled,
} = toRefs(mappingService)

watchEffect(() => {
    if (!instantAdjudicationCanBeEnabled.value)
        method.value = ADJUDICATION_METHOD.MANUAL
})
</script>

<style scoped></style>
b
