<template>
    <template v-if="error">
        <div class="alert alert-danger">
            {{ error }}
        </div>
    </template>
    <template v-if="ready">
        <router-view></router-view>
    </template>
    <template v-if="loading">
        <LoadingIndicator />
    </template>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import LoadingIndicator from './components/LoadingIndicator.vue'
import { useMainStore } from './store'

const store = useMainStore()

const loading = computed(() => store.loading)
const ready = computed(() => store.ready)
const error = computed(() => store.error)

onMounted(() => {
    store.init()
})
</script>

<style scoped></style>
