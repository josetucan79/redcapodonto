<template>
    <form action="">
        <div class="mb-3">
            <label class="form-label" for="input-field">field</label>
            <input
                class="form-control form-control-sm"
                type="text"
                v-model="data.field"
                id="input-field"
                required
                placeholder="Unique identifier..."
            />
            <ErrorList :errors="errors?.field" />
        </div>
        <div class="mb-3">
            <label class="form-label" for="input-label">label</label>
            <input
                class="form-control form-control-sm"
                type="text"
                v-model="data.label"
                id="input-label"
                placeholder="Short name..."
            />
            <ErrorList :errors="errors?.label" />
        </div>
        <div class="mb-3">
            <label class="form-label" for="input-description"
                >description</label
            >
            <input
                class="form-control form-control-sm"
                type="text"
                v-model="data.description"
                id="input-description"
                placeholder="Fully-specified name..."
            />
            <ErrorList :errors="errors?.description" />
        </div>
        <div class="mb-3">
            <label class="form-label" for="select-category">category</label>
            <select
                id="select-category"
                class="form-select form-select-sm"
                v-model="data.category"
            >
                <option value="" disabled>Select a CDIS category...</option>
                <template
                    v-for="(category, index) in validCategories"
                    :key="index"
                >
                    <option :value="category">{{ category }}</option>
                </template>
            </select>
            <ErrorList :errors="errors?.category" />
        </div>
        <div class="mb-3">
            <label class="form-label" for="input-subcategory"
                >subcategory</label
            >
            <input
                class="form-control form-control-sm"
                type="text"
                v-model="data.subcategory"
                id="input-subcategory"
                placeholder="CDIS sub-category..."
            />
            <ErrorList :errors="errors?.subcategory" />
        </div>
        <div class="mb-3">
            <div class="d-flex gap-2">
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        v-model="data.temporal"
                        id="input-temporal"
                    />
                    <label class="form-check-label" for="input-temporal"
                        >temporal</label
                    >
                </div>
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        v-model="data.identifier"
                        id="input-identifier"
                    />
                    <label class="form-check-label" for="input-identifier"
                        >identifier</label
                    >
                </div>
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        v-model="data.disabled"
                        id="input-disabled"
                    />
                    <label class="form-check-label" for="input-disabled"
                        >disabled</label
                    >
                </div>
            </div>
            <ErrorList :errors="errors?.temporal" />
            <ErrorList :errors="errors?.identifier" />
            <ErrorList :errors="errors?.disabled" />
        </div>
        <div class="mb-3">
            <label class="form-label" for="input-disabled_reason"
                >disabled reason</label
            >
            <textarea
                class="form-control form-control-sm"
                rows="3"
                v-model="data.disabled_reason"
                id="input-disabled_reason"
                :disabled="!data.disabled"
                :placeholder="`${data.disabled ? 'Explain why this field is disabled...' : ''}`"
            />
        </div>
    </form>
</template>

<script setup>
import { h, computed } from 'vue'
import ErrorList from '../../../shared/ErrorList.vue'


const props = defineProps({
    data: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    validCategories: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:data'])

const data = computed({
    get: () => props.data,
    set: (value) => emit('update:data', value),
})
</script>

<style scoped>
label {
    font-weight: 700;
    text-transform: capitalize;
}
</style>
