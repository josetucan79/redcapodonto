<template>
    <div class="d-flex gap-2">
        <div>
            <input
                class="form-control form-control-sm"
                type="text"
                placeholder="key..."
                v-model="name"
            />
            <ErrorList :errors="errors.name" />
        </div>
        <div>
            <input
                class="form-control form-control-sm"
                type="text"
                placeholder="value..."
                v-model="value"
            />
            <ErrorList :errors="errors.value" />
        </div>
        <div>
            <select class="form-select form-select-sm" v-model="context">
                <option value="">Always</option>
                <option value="ehr">ehr launch</option>
                <option value="standalone">standalone launch</option>
            </select>
        </div>
        <slot></slot>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useValidation, required } from '../../utils/useValidation'
import ErrorList from '../../shared/ErrorList.vue'

const validate = useValidation({
    name: [required()],
    value: [required()],
})

const props = defineProps({
    name: { type: String, default: '' },
    value: { type: String, default: '' },
    context: { type: String, default: '' },
})

const emit = defineEmits(['update:name', 'update:value', 'update:context'])

const validation = computed(() =>
    validate({ name: name.value, value: value.value, context: context.value })
)
const errors = computed(() => validation.value.errors())

const name = computed({
    get: () => props.name,
    set: (value) => emit('update:name', value),
})
const value = computed({
    get: () => props.value,
    set: (value) => emit('update:value', value),
})
const context = computed({
    get: () => props.context,
    set: (value) => emit('update:context', value),
})

defineExpose({ validation })
</script>

<style lang="scss" scoped></style>
