<template>
    <b-dropdown variant="outline-secondary" size="sm">
        <template #button>
            <span class="d-inline-block" style="width: 10px">{{ selected || PLUS_MINUS_SYMBOL }}</span>
            <!-- <i class="fas fa-plus-minus fa-fw"></i> -->
        </template>
        <b-dropdown-item @click="onSelect('+-')">
            <span>{{ PLUS_MINUS_SYMBOL }}</span>
            <!-- <i class="fas fa-plus-minus fa-fw"></i> -->
        </b-dropdown-item>
        <b-dropdown-item @click="onSelect('+')">
            <span>+</span>
            <!-- <i class="fas fa-plus fa-fw"></i> -->
        </b-dropdown-item>
        <b-dropdown-item @click="onSelect('-')">
            <span>-</span>
            <!-- <i class="fas fa-minus fa-fw"></i> -->
        </b-dropdown-item>
    </b-dropdown>
</template>

<script setup>
const PLUS_MINUS_SYMBOL = '±'
const selected = defineModel('selected', { default: null, required: true })

function onSelect(value = null) {
    selected.value = value
}
</script>

<style scoped></style>
b
