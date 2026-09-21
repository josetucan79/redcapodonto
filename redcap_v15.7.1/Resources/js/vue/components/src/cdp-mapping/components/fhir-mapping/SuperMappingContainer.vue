<template>
    <div data-container>
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
                        <SuperMappingContainer :instance="child" class="px-2" />
                    </template>
                    <template v-else-if="child instanceof Element">
                        <MappingElement
                            v-bind="{ ...child.data }"
                            @click="onElementSelected(child)"
                        />
                    </template>
                </template>
            </template>
        </details>
    </div>
</template>

<script setup>
import { computed, inject, onMounted, onUnmounted, provide, ref } from 'vue'
import { Container, Element } from '@/utils/metadataUtils'
import MappingElement from './MappingElement.vue'
import SuperMappingContainer from './SuperMappingContainer.vue'
import { groupMetadata } from '@/utils/metadataUtils'

const detailsRef = ref()

const props = defineProps({
    // Accepting either a list to be grouped or an instance for recursive rendering
    list: { type: Object, default: null },
    instance: { type: Container, default: null },
})
const open = defineModel('open', { type: Boolean, default: false })

// Determine if the current container is root or a child
const instance = computed(() => {
    if (props.instance) {
        // If an instance is passed, it means we're rendering a nested container
        return props.instance
    }
    if (props.list) {
        // If a list is passed, we group it here and act as the root container
        return groupMetadata(props.list)
    }
    return new Container('') // Default empty container
})

const emit = defineEmits(['onSelected'])

// Inject the provided `onElementSelected` function if available
const injectedOnElementSelected = inject('onElementSelected', null)

const onElementSelected = (element) => {
    if (injectedOnElementSelected) {
        injectedOnElementSelected(element)
    } else {
        emit('onSelected', element)
    }
}

provide('onElementSelected', onElementSelected)

// listen for open events
const controller = new AbortController()
onMounted(() => {
    // open automatically if not an instance (root element)
    if (!props.instance) open.value = true
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

<style scoped>
*:has([data-container]):first-of-type
    > details:first-of-type
    > summary::marker {
    content: '';
}
</style>
