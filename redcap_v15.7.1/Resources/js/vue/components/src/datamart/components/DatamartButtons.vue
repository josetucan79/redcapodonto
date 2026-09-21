<template>
    <div class="mb-2 d-flex gap-2">
        <FetchButton />

        <button class="btn btn-sm btn-info" @click="onSearchClicked">
            <i class="fa-solid fa-magnifying-glass fa-fw"></i>
            <span class="ms-1">Select and fetch records...</span>
        </button>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import FetchButton from './buttons/FetchButton.vue'

const router = useRouter()

function onSearchClicked() {
    router.push({ name: 'search' })
}
</script>

<style scoped></style>
