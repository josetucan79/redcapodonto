<template>
    <div class="p-5">
        <FileExplorer :data="useData()" />
    </div>
</template>

<script setup>
import FileExplorer from '../components/test/FileExplorer.vue'
import { useData } from '../components/test/data'
</script>

<style scoped></style>
