<template>
    <div data-wrapper class="d-flex rounded">
        <div class="text-center m-auto">
            <div class="fs-1">
                <i class="fas fa-icons fa-fw"></i>
            </div>
            <div class="mt-2">
                <slot></slot>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
</script>

<style scoped>
[data-wrapper] {
    border-color: rgb(222 226 230);
    border-width: 4px;
    border-style: dashed !important;
    min-height: 200px;
    min-width: 200px;
}
</style>
