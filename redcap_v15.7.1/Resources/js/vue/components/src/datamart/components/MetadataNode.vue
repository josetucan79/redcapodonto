<template>
    <template v-if="isNode(node)">
        <div class="node" :data-disabled="node?.metadata?.disabled">
            <span class="node-name fw-bold border-end pe-2 me-2">{{ node?.metadata?.field }}</span>
            <span class="node-label">{{ node?.metadata?.label }}</span>
            <template v-if="shouldShowDescription(node)">
                <small class="ms-2 text-muted d-block">{{node?.metadata?.description}}</small>
            </template>
            <template v-if="node?.metadata?.disabled">
                <DisabledReasonButton :node="node" />
            </template>
        </div>
    </template>
    <template v-else-if="isContainer(node)">
        <div
            class="node-container d-flex p-2"
            :class="{ 'border rounded': node.parent !== null }"
        >
            <div v-if="node.parent !== null" @click.stop="onContainerClicked">
                <i
                    class="node-open-status fas fa-circle-chevron-right fa-fw"
                    :class="{ 'fa-rotate-90': isOpen }"
                ></i>
            </div>

            <div class="w-100">
                <div>
                    <span class="container-name d-block fw-bold" @click.stop="onContainerClicked" >
                        {{ node.name }}
                    </span>
                    <small class="text-muted" v-if="node.parent !== null">
                        {{
                            `${node.total} field${
                                node.total === 1 ? '' : 's'
                            } `
                        }}
                    </small>
                </div>
                <template v-if="isOpen">
                    <template
                        v-for="(child, index) in node.children"
                        :key="`${index} ${child?.name}`"
                    >
                        <MetadataNode :node="child">
                            <template v-slot:container-end="{ node }">
                                <slot name="container-end" :node="node" />
                            </template>
                        </MetadataNode>
                    </template>
                </template>
            </div>
            <slot name="container-end" :node="node"></slot>
        </div>
    </template>
</template>

<script setup>
import MetadataNode from './MetadataNode.vue'
import DisabledReasonButton from './buttons/DisabledReasonButton.vue'
import { Container, Node } from '../models'
import { ref, toRefs, watchEffect } from 'vue'

const props = defineProps({
    node: { type: [Container, Node], default: null },
})

const { node } = toRefs(props)

const isOpen = ref(false)

watchEffect(() => {
    if (node.value?.parent === null) isOpen.value = true
})

function isNode(_node) {
    return _node instanceof Node
}
function isContainer(_node) {
    return _node instanceof Container
}

function onContainerClicked() {
    if (node.value?.parent === null) return
    isOpen.value = !isOpen.value
}
function shouldShowDescription(_node) {
    return (
        _node?.metadata?.description?.toUpperCase() !==
        _node?.metadata?.label?.toUpperCase()
    )
}
</script>

<style scoped>
.node-container + :deep(.node-container) {
    margin-top: 10px;
}
.node-container .container-name,
.node-container .node-open-status {
    cursor: pointer;
}
.node[data-disabled=true] .node-name,
.node[data-disabled=true] .node-label {
    text-decoration: line-through;
}
</style>
