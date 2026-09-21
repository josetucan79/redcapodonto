<template>
    <div>
        <input class="form-check-input" type="checkbox" v-model="enabled">
        <input class="form-control form-control-sm" type="text" placeholder="key">
        <input class="form-control form-control-sm" type="text" placeholder="value">
        <button class="btn btn-sm btn-danger">
            <i class="fas fa-trash fa-fw"></i>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    enabled: { type: Boolean },
    name: { type: String },
    value: { type: String },
})

const emit = defineEmits(['update:enabled', 'update:name', 'update:value'])

const enabled = computed({
    get: () => props.enabled,
    set: (value) => emit('update:enabled', value),
})
const name = computed({
    get: () => props.name,
    set: (value) => emit('update:name', value),
})
const value = computed({
    get: () => props.value,
    set: (value) => emit('update:value', value),
})
</script>

<style scoped>

</style>