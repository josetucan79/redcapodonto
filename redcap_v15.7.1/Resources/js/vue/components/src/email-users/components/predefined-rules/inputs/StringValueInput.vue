<template>
    <input class="form-control form-control-sm" type="search" v-model="realValue">
</template>

<script setup>
import { computed } from 'vue'
const values = defineModel({ type: Array })

const props = defineProps({
    config: { type: Object }
})

const realValue = computed({
    get: () => String(values.value ?? ''),
    set: (_value) => values.value = [_value]
})
</script>

<style scoped>

</style>