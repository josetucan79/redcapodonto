<template>
    <div>test 2</div>
    <button @click="onClick">close</button>
</template>

<script setup>
import { useRouter } from 'vue-router'
const router = useRouter()

function onClick() {
    router.back()
}
</script>

<style scoped></style>
