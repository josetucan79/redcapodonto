<template>
    <select
        class="form-select form-select-sm"
        v-model="value"
    >
        <option value="" disabled>Select...</option>
        <option
            v-for="(label, value) in config?.options ?? []"
            :key="value"
            :value="value"
        >
            {{ label }}
        </option>
    </select>
</template>

<script setup>

import { computed } from 'vue';

const values = defineModel({ type: Array })
const props = defineProps({
    config: { type: Object }
})

const value = computed({
    get: () => values.value.at(0) ?? null,
    set: (_value) => values.value = [_value],
})

</script>

<style scoped>

</style>