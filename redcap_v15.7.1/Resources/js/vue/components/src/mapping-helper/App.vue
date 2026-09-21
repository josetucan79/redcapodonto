<template>
    <template v-if="errorMessage">
        <div class="alert alert-danger m-2">
            <span style="white-space: pre">{{ errorMessage }}</span>
        </div>
    </template>
    <template v-if="loading">
        <i class="fas fa-spinner fa-spin fa-fw me-1"></i>
        <span>Loading...</span>
    </template>
    <template v-else-if="!errorMessage">
        <router-view />
    </template>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useSettingsStore } from './store'
import { useError } from '../utils/ApiClient'

const settingsStore = useSettingsStore()
const loading = ref(false)
const errorMessage = ref()

onMounted(async () => {
    try {
        errorMessage.value = null
        loading.value = true
        await settingsStore.fetchCategories()
    } catch (error) {
        errorMessage.value = useError(error)
        console.log('There was an error loading the settings.', error)
    } finally {
        loading.value = false
    }
})
</script>

<style scoped></style>
