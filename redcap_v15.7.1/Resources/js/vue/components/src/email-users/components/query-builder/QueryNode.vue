<template>
    <component :is="type" v-model="node"></component>
</template>

<script setup>
import { computed, inject } from 'vue';
import { Rule, Group } from '../../models/query-builder';

// Inject the components registry.
const componentsRegistry = inject('componentsRegistry');

const node = defineModel({
    type: Object,
    required: true,
})

const type = computed(() => {
    if(node.value instanceof Rule) return componentsRegistry.rule
    if(node.value instanceof Group) return componentsRegistry.group
    return null
})
</script>
