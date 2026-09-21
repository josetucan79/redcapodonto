<template>
    <div class="folder">
        <div class="header" @click="onHeaderClicked">
            <span>
                <i v-if="isOpen" class="fas fa-chevron-down fa-fw"></i>
                <i v-else class="fas fa-chevron-right fa-fw"></i>
            </span>
            <span>{{ data.name }} ({{ data.children.length }})</span>
        </div>
        <div class="children">
            <template v-if="isOpen">
                <slot></slot>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    data: { type: Object },
})

const isOpen = ref(true)

function onHeaderClicked() {
    isOpen.value = !isOpen.value
}

</script>

<style scoped>
:deep(.folder) {
    margin-left: 20px;
    position: relative;
}
:deep(.file) {
    margin-left: 20px;
}
</style>
