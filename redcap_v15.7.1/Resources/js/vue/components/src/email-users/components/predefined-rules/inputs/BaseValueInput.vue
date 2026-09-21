<template>
    <div>
        <slot v-bind="{...$attrs}"></slot>
    </div>
</template>

<script setup>
import { provide } from 'vue';
const value = defineModel({ })
const props = defineProps({
    condition: { type: String }
})

provide('test', 1234)

provide('value', value.value)
provide('props', {...props})
</script>

<style scoped>

</style>