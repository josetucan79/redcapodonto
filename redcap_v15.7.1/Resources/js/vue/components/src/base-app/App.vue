<template>
    <div>
        <button @click="onIncrementStoreClicked" :disabled="loading">
            <i class="fas fa-spinner fa-spin fa-fw" v-if="loading"></i>
            <i class="fas fa-plus" v-else></i>
            <span class="ms-2">add to store</span>
        </button>

        {{ sum }}
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { default as store } from './store'

const sum = computed(() => {
    return store.sum
})
const loading = computed(() => {
    return store.loading
})

function onIncrementStoreClicked() {
    store.sum = Math.random()
}
</script>

<style scoped></style>
