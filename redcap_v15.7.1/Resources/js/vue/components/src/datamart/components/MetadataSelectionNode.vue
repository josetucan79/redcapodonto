<template>
    <template v-if="isNode()">
        <div
            class="node d-flex gap-2"
            :data-disabled="node?.metadata?.disabled"
        >
            <div class="form-check form-switch">
                <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    :id="node.name"
                    v-model="fields"
                    :value="node?.name"
                    :disabled="node?.name === 'id'"
                    :key="`node-${node.name}`"
                />
                <label
                    class="form-check-label"
                    :for="node.name"
                    :title="node?.metadata?.description"
                >
                    <span class="node-name fw-bold border-end me-2 pe-2">{{
                        node?.metadata?.field
                    }}</span>
                    <span class="node-label">{{ node?.metadata?.label }}</span>
                    <template v-if="shouldShowDescription()">
                        <small class="text-muted d-block">{{
                            node?.metadata?.description
                        }}</small>
                    </template>
                </label>
                <template v-if="node?.metadata?.disabled">
                    <DisabledReasonButton :node="node" />
                </template>
            </div>
        </div>
    </template>
    <template v-else-if="isContainer()">
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
                    <div class="d-flex align-items-start">
                        <div>
                            <span
                                class="container-name fw-bold d-block"
                                @click.stop="onContainerClicked"
                            >
                                {{ node.name }}
                            </span>
                            <small
                                class="text-muted"
                                v-if="node.parent !== null"
                            >
                                <template v-if="node.query">
                                    <span class="border-end pe-1 me-1"
                                        >showing {{ node.totalFiltered }}/{{
                                            node.total
                                        }}</span
                                    >
                                </template>
                                <span
                                    >{{ node.totalFilteredSelected }}/{{
                                        node.totalFiltered
                                    }}
                                    {{
                                        `field${
                                            node.totalSelected === 1 ? '' : 's'
                                        } selected`
                                    }}</span
                                >
                            </small>
                        </div>
                        <div class="ms-auto d-flex flex-column gap-2">
                            <div class="d-flex">
                                <button
                                    type="button"
                                    :key="`toggle-${node.name}`"
                                    class="btn btn-sm ms-auto"
                                    :class="{
                                        'btn-success': isAllSelected(),
                                        'btn-outline-secondary':
                                            !isAllSelected(),
                                    }"
                                    @click="onToggleGroup"
                                    v-if="node.parent !== null"
                                >
                                    <span
                                        v-text="
                                            node.totalFilteredSelected ===
                                            node.totalFiltered
                                                ? 'deselect all'
                                                : 'select all'
                                        "
                                    ></span>
                                </button>
                            </div>
                            <slot
                                name="container-header-end"
                                :node="node"
                            ></slot>
                            <div
                                class="form-check form-switch"
                                v-if="shouldShowDateRangeOption()"
                            >
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    :id="`${node.name}-apply-date-range`"
                                    :checked="node.applyDateRange"
                                    @change="onApplyDateRangeChanged"
                                    :disabled="!Object.keys(dateRange).length || node.totalSelected === 0"
                                />
                                <label
                                    class="form-check-label"
                                    :for="`${node.name}-apply-date-range`"
                                >
                                    <span>apply date range</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <template v-if="isOpen">
                    <template
                        v-for="child in node.filtered"
                        :key="`${child?.name}`"
                    >
                        <MetadataSelectionNode
                            :node="child"
                            :date-range="dateRange"
                        />
                    </template>
                </template>
            </div>
        </div>
    </template>
</template>

<script setup>
import { ref, computed, toRefs, watchEffect } from 'vue'
import MetadataSelectionNode from './MetadataSelectionNode.vue'
import DisabledReasonButton from './buttons/DisabledReasonButton.vue'
import { Container, Node } from '../models'
import { useSettingsStore } from '../store'

const settingsStore = useSettingsStore()

const props = defineProps({
    node: { type: [Container, Node], default: null },
    dateRange: { type: Object, default: null },
})

const { node, dateRange } = toRefs(props)

const fields = computed({
    get: () => {
        const container = node.value?.parent
        if (!container) return []
        return (
            container?.nodes
                ?.filter((node) => node.selected)
                ?.map((node) => node.name) ?? []
        )
    },
    set: (value) => {
        node.value.selected = !node.value.selected
    },
})

function onApplyDateRangeChanged(event) {
    const checked = event?.target?.checked
    node.value.applyDateRange = checked
}

const isOpen = ref(false)

watchEffect(() => {
    if (node.value?.parent === null) isOpen.value = true
})

function isAllSelected() {
    return node.value.totalFilteredSelected === node.value.totalFiltered
}

function isNode() {
    return node.value instanceof Node
}
function isContainer() {
    return node.value instanceof Container
}

function onContainerClicked() {
    if (node.value?.parent === null) return
    isOpen.value = !isOpen.value
}

function onToggleGroup() {
    const nodes = node.value.filteredNodes
    const newSelectedStatus = isAllSelected() ? false : true
    for (const _node of nodes) {
        _node.selected = newSelectedStatus
    }
}
function shouldShowDescription() {
    const _node = node.value
    return (
        _node?.metadata?.description?.toUpperCase() !==
        _node?.metadata?.label?.toUpperCase()
    )
}

function shouldShowDateRangeOption() {
    const _node = node.value
    const found = settingsStore?.date_range_categories?.find(
        (category) => category.name === _node.name
    )
    return found !== undefined
}
</script>

<style scoped>
.node-container + .node-container {
    margin-top: 10px;
}
.node-container .container-name,
.node-container .node-open-status {
    cursor: pointer;
}
.node[data-disabled='true'] .node-name,
.node[data-disabled='true'] .node-label {
    text-decoration: line-through;
}
</style>
