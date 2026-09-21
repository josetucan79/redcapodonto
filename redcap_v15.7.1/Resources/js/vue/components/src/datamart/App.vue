<template>
    <template v-if="errorMessage">
        <div class="alert alert-danger">
            <span>{{ errorMessage }}</span>
        </div>
    </template>
    <template v-if="ready">
        <router-view />
    </template>
    <template v-if="loading">
        <div class="alert alert-info my-2">
            <i class="fas fa-spinner fa-spin fa-fw me-1"></i>
            <span>Loading...</span>
        </div>
    </template>

    <b-modal ref="loadingModalRef" title="Loading">
        <i class="fas fa-spinner fa-spin fa-fw me-1"></i>
        <span>Please wait...</span>
        <template #footer></template>
    </b-modal>
</template>

<script setup>
import { computed, onMounted, ref, watchEffect } from 'vue'
import { useAppStore } from './store'

const appStore = useAppStore()

const loadingModalRef = ref()
const ready = computed(() => appStore?.ready)
const loading = computed(() => appStore?.loading)
const errorMessage = computed(() => appStore?.error)
const watchLoading = () => {
    watchEffect(() => {
        if (loading.value === true) loadingModalRef.value?.show()
        else loadingModalRef.value?.hide()
    })
}

onMounted(() => {
    appStore.init()
    // watchLoading()
})
</script>



<style scoped></style>
