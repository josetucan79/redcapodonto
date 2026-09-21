<template>
    <div>
        <slot name="before-table"></slot>
        <table class="table table-bordered table-hover table-striped">
            <thead>
                <tr>
                    <th>field</th>
                    <th>label</th>
                    <th>description</th>
                    <th>category</th>
                    <th>subcategory</th>
                    <th>temporal</th>
                    <th>identifier</th>
                    <th>disabled</th>
                    <th>disabled_reason</th>
                    <slot :name="`after-header-cell`" :items="items"></slot>
                </tr>
            </thead>
            <tbody>
                <template v-for="(item, index) in items" :key="item?.field">
                    <tr
                        class="position-relative"
                        @mouseenter="onMouseEnterRow($event, item, index)"
                        @mouseleave="onMouseLeaveRow($event, item, index)"
                        @mouseover="onMouseOverRow($event, item, index)"
                    >
                        <td>{{ item?.field }}</td>
                        <td>{{ item?.label }}</td>
                        <td>{{ item?.description }}</td>
                        <td>{{ item?.category }}</td>
                        <td>{{ item?.subcategory }}</td>
                        <td class="text-center">
                            <BooleanViewer :value="item?.temporal" />
                        </td>
                        <td class="text-center">
                            <BooleanViewer :value="item?.identifier" />
                        </td>
                        <td class="text-center">
                            <BooleanViewer :value="item?.disabled" />
                        </td>
                        <td>
                            <div class="ellipsis">
                                {{ item?.disabled_reason }}
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <slot></slot>
    </div>
</template>

<script setup>
import { h } from 'vue'

const props = defineProps({
    items: { type: Array, default: () => [] },
})

const emit = defineEmits(['mouseenter-row', 'mouseleave-row', 'mouseover-row'])

const BooleanViewer = {
    props: {
        value: { type: Boolean, default: false },
    },
    setup(props, { slots }) {
        return () =>
            h('i', {
                class: `fa-regular fa-fw ${
                    props?.value === true ? 'fa-square-check' : 'fa-square'
                }`,
                innerHTML: '',
            })
    },
}
function onMouseOverRow(event, item, index) {
    emit('mouseover-row', { event, item, index })
}
function onMouseEnterRow(event, item, index) {
    emit('mouseenter-row', { event, item, index })
}

function onMouseLeaveRow(event, item, index) {
    emit('mouseleave-row', { event, item, index })
}
</script>

<style scoped>
.ellipsis {
    width: 100px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
