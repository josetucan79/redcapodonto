<template>
    <div class="card">
        <div class="card-header text-center">
            <span class="fst-italic d-block">No reward options</span>
        </div>
        <div class="card-body text-center">
            <span>Please add a reward option.</span>
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

<style scoped></style>
