<template>
    <div data-menu class="">
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-light" @click="onEditClicked">
                <i class="fas fa-pencil fa-fw"></i>
            </button>
            <button class="btn btn-sm btn-light" @click="onRemoveClicked">
                <i class="fas fa-trash fa-fw"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    visible: { type: Boolean, default: false },
})
const emit = defineEmits(['edit', 'remove'])

function onRemoveClicked() {
    emit('remove')
}

function onEditClicked(item) {
    emit('edit')
}
</script>

<style scoped>
[data-menu] {
    transition-property: opacity;
    transition-duration: 300ms;
    transition-timing-function: ease-in-out;
}
</style>
