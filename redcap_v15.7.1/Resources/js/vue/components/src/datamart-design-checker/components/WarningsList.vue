<template>
    <div>
        <template v-for="(warning, index) in warnings" :key="index">
            <div class="alert" :class="`alert-${warning?.variant}`">
                <i class="fas fa-info-circle fa-fw me-1"></i>
                <span>{{ warning?.message }}</span>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAppStore } from '../store'

const appStore = useAppStore()

const warnings = computed(() => appStore.warnings)
</script>

<style scoped></style>
