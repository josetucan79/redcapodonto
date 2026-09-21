<template>
    <button class="btn btn-sm btn-primary" @click="onSearchClicked">
        <i class="fa-solid fa-magnifying-glass fa-fw me-1"></i>
        <span>Select and fetch records...</span>
    </button>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

function onSearchClicked() {
    router.push({ name: 'search' })
}
</script>

<style scoped></style>
