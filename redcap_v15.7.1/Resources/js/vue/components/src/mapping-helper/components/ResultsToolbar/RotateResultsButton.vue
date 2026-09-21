<template>
    <div>
        <button class="btn btn-sm btn-secondary" @click="onRotateClicked">
            <i
                class="fas fa-sync fa-fw me-1"
                :class="{ 'fa-rotate-90': searchStore.rotate }"
            ></i>
            <span>Rotate results</span>
        </button>
    </div>
</template>

<script setup>
import { useSearchStore } from '../../store'

const searchStore = useSearchStore()
function onRotateClicked() {
    searchStore.rotate = !searchStore.rotate
}
</script>

<style scoped>
.fas {
    transition-property: transform;
    transition-duration: 300ms;
    transition-timing-function: cubic-bezier();
}
</style>
