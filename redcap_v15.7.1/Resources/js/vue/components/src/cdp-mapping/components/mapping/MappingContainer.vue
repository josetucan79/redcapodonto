<template>
    <div data-container>
        <details :open="open">
            <summary>
                <span>{{ instance.name }}</span>
            </summary>
            <template v-for="(child, index) in instance.children" :key="index">
                <template v-if="(child instanceof Container)">
                    <MappingContainer :instance="child" class="ms-2" />
                </template>
                <template v-else-if="(child instanceof Element)">
                    <MappingElement v-bind="{ ...child.data }" class="ms-2" />
                </template>
            </template>
    </details>
    </div>
</template>

<script setup>
import { Container, Element } from '@/utils/metadataUtils';
import MappingElement from './MappingElement.vue'
import MappingContainer from './MappingContainer.vue'

const props = defineProps({
    instance: { type: Container, default: new Container('') },
    open: { type: Boolean, default: false },
})
</script>

<style scoped></style>