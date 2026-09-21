<template>
    <template v-if="value !== null && typeof value === 'object'">
        <template v-for="(_value, key) in value" :key="key">
            <div class="table-cell value-wrapper">
                <template v-if="!isNumber(key)">
                    <span class="cell-key text-muted me-2" :data-key="key">{{
                        `${key}:`
                    }}</span>
                </template>
                <ValueViewer :value="_value" />
            </div>
        </template>
    </template>
    <template v-else>
        <span class="cell-value" :data-value="value" v-html="value"></span>
    </template>
</template>

<script>
import { h } from 'vue'

const ValueViewerRender = {
    props: {
        value: {},
    },
    setup(props) {
        const isNumber = (str) => !isNaN(str)

        if (props.value !== null && typeof props.value === 'object') {
            const vnodes = []
            for (const [key, value] of Object.entries(props.value)) {
                const keyValue = []
                if (!isNumber(key))
                    keyValue.push(
                        h(
                            'span',
                            {
                                class: 'cell-key text-muted me-2',
                                'data-key': key,
                            },
                            `${key}:`
                        )
                    )
                keyValue.push(h(ValueViewerRender, { value }))
                vnodes.push(
                    h('div', { class: `table-cell value-wrapper` }, keyValue)
                )
            }
            return () => vnodes
        } else {
            return () =>
                h(
                    'span',
                    { class: 'cell-value', 'data-value': props?.value },
                    props?.value
                )
        }
    },
}
export { ValueViewerRender }
</script>
<script setup>
import ValueViewer from './ValueViewer.vue'

const props = defineProps({
    value: {},
})

const isNumber = (str) => !isNaN(str)
</script>

<style scoped>
:deep(.value-wrapper .value-wrapper) {
    padding-left: 1rem;
}
</style>
