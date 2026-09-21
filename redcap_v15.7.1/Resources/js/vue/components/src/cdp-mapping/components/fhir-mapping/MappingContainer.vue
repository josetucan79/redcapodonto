<template>
    <div class="mapping-container">
        <details :open="open" ref="detailsRef">
            <summary>
                <span>{{ instance.name }}</span>
            </summary>
            <template v-if="open">
                <template
                    v-for="(child, index) in instance.children"
                    :key="index"
                >
                    <template v-if="child instanceof Container">
                        <MappingContainer :instance="child" class="ms-2" />
                    </template>
                    <template v-else-if="child instanceof Element">
                        <MappingElement
                            v-bind="{ ...child.data }"
                            class="ms-2"
                        />
                    </template>
                </template>
            </template>
        </details>
    </div>
</template>

<script setup>
import { Container, Element } from '@/utils/metadataUtils'
import MappingElement from './MappingElement.vue'
import MappingContainer from './MappingContainer.vue'
import { onMounted, onUnmounted, ref } from 'vue'

const detailsRef = ref()

const props = defineProps({
    instance: { type: Container, default: new Container('') },
})
const open = defineModel('open', { type: Boolean, default: false })

// listen for open events
const controller = new AbortController()
onMounted(() => {
    detailsRef.value.addEventListener(
        'toggle',
        (event) => {
            open.value = event.target.open
        },
        { signal: controller.signal }
    )
})
onUnmounted(() => controller.abort())
</script>

<style scoped></style>
