<template>
    <input class="form-control form-control-sm" type="search" disabled>
</template>

<script setup>
import { computed } from 'vue'
const values = defineModel({ type: Array })

const props = defineProps({
    config: { type: Object }
})

const realValue = computed({
    get: () => null,
    set: (_value) => values.value = [] // always set to empty
})
</script>

<style scoped>

</style>