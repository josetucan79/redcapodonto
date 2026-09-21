<template>
    <button
        class="btn btn-sm btn-outline-primary"
        :disabled="groupDisabled"
        @click="onGroupClicked"
        :class="classes"
    >
        <slot></slot>
        <span class="group-metadata">
            <span>{{ totalSelected }}</span
            >/
            <span>{{ total }}</span>
        </span>
    </button>
</template>

<script setup>
import { computed, toRefs } from 'vue'
import { useUsersStore } from '../store'

const usersStore = useUsersStore()

const activeColor = { r: 13, g: 110, b: 253 }

const getButtonColors = () => {
    const percentage = total.value != 0 ? totalSelected.value / total.value : 0
    const { r, g, b } = activeColor
    const backgroundColor = `rgb(${r} ${g} ${b} / ${percentage})`
    return {
        backgroundColor,
        color: percentage > 0.4 ? 'white' : 'black',
        borderColor: percentage > 0.9 ? backgroundColor : 'black',
    }
}

const props = defineProps({
    group: { type: String },
})

const { group } = toRefs(props)
const groupMetadata = computed(() => usersStore.metadata.groups?.[group.value])
const total = computed(() => {
    return groupMetadata.value?.total
})
const totalSelected = computed(() => {
    return groupMetadata.value?.selected
})
const groupDisabled = computed(() => {
    return (groupMetadata.value?.total ?? 0) <= 0
})
const groups = computed(() => usersStore.groups)
const classes = computed(() => {
    return {
        active: groups.value.includes(group.value),
        indeterminate:
            groupMetadata.value?.selected > 0 &&
            groupMetadata.value?.selected != groupMetadata.value?.total,
    }
})

function onGroupClicked() {
    usersStore.doAction('toggleGroup', [group.value])
}
</script>

<style scoped>
button .group-metadata {
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 0.5rem;
    color: rgba(0 0 0 / 0.5);
}
button.indeterminate {
    background-color: hsla(216, 98%, 90%, 1);
}
button.active {
    background-color: rgb(13 110 253);
}
button.active .group-metadata {
    color: rgba(255 255 255 / 1);
}
</style>
