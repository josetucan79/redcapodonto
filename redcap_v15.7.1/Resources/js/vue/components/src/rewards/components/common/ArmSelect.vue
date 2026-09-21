<template>
    <select v-model="arm" class="form-select form-select-sm">
        <template v-for="(arm, index) in appStore.arms" :key="index">
            <option :value="arm?.arm_num">{{ arm.name }}</option>
        </template>
    </select>
</template>

<script setup>
import { useAppStore } from '@/rewards/store'

const arm = defineModel()

const appStore = useAppStore()
</script>

<style scoped></style>
