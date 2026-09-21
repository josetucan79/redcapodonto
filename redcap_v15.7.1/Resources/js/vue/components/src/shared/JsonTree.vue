<template>
    <span :class="{ collapsed: collapsed }">
        <span
            v-if="type === 'object' || type === 'array'"
            class="indicator me-1"
            @click.stop="onClick"
        ></span>
        <span>{{ brackets.open }}</span>
        <span class="" v-show="!collapsed">
            <template v-for="(value, key) in json" :key="`${index}-el-${key}`">
                <div class="ms-4">
                    <span class="fw-bold">{{ key }}: </span>
                    <template v-if="Array.isArray(value)">
                        <JsonTree :index="index + 1" :json="value" />
                    </template>
                    <template
                        v-else-if="typeof value === 'object' && value !== null"
                    >
                        <JsonTree :index="index + 1" :json="value" />
                    </template>
                    <template v-else>
                        <template v-if="getType(value) === 'null'"
                            ><span class="fst-italic type-null"
                                >null</span
                            ></template
                        >
                        <template v-else-if="getType(value) === 'boolean'"
                            ><span class="type-boolean">{{
                                value
                            }}</span></template
                        >
                        <template v-else-if="getType(value) === 'number'"
                            ><span class="type-number">{{
                                value
                            }}</span></template
                        >
                        <template v-else-if="getType(value) === 'string'"
                            ><span class="type-string"
                                >"{{ value }}"</span
                            ></template
                        >
                        <span>,</span>
                    </template>
                </div>
            </template>
        </span>
        <span>{{ brackets.close }}</span>
    </span>
</template>

<script setup>
import { computed, ref } from 'vue'
import JsonTree from './JsonTree.vue'

const props = defineProps({
    json: { default: null },
    index: { type: Number, default: 0 },
})

const collapsed = ref(false)

function getType(value) {
    if (Array.isArray(value)) return 'array'
    else if (typeof value === 'object' && value !== null) return 'object'
    else if (typeof value === 'number') {
        return 'number'
    } else if (typeof value === 'string') {
        return 'string'
    } else if (typeof value === 'boolean') {
        return 'boolean'
    } else if (value === null) {
        return 'null'
    } else {
        return 'unknown'
    }
}

const type = computed(() => getType(props.json))

const brackets = computed(() => {
    const symbols = {
        open: '',
        close: '',
    }
    if (type.value === 'array') {
        symbols.open = '['
        symbols.close = ']'
    } else if (type.value === 'object') {
        symbols.open = '{'
        symbols.close = '}'
    }
    return symbols
})

function renderValue(value) {
    const type = getType(value)
    switch (type) {
        case 'number':
            return value
        case 'string':
            return `"${value}"`
        case 'boolean':
            return value
        case 'null':
            return 'null'
        default:
            break
    }
}
function onClick() {
    collapsed.value = !collapsed.value
}
</script>

<style>
:root {
    --json-tree-type-null-color: rgb(191, 21, 196);
    --json-tree-type-boolean-color: rgb(255 141 7);
    --json-tree-type-number-color: rgb(53 23 223);
    --json-tree-type-string-color: rgb(156 10 10);
}
</style>
<style scoped>
.indicator {
    cursor: pointer;
}
.indicator::before {
    display: inline-block;
    transition-property: transform;
    transition-duration: 300ms;
    transition-timing-function: ease-in-out;
    content: '▼';
}
.collapsed :deep(.indicator::before) {
    transform: rotate(-90deg);
}
.type-null {
    color: var(--json-tree-type-null-color);
}
.type-boolean {
    color: var(--json-tree-type-boolean-color);
}
.type-number {
    color: var(--json-tree-type-number-color);
}
.type-string {
    color: var(--json-tree-type-string-color);
}
</style>
