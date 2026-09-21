<template>
    <b-modal ref="modalRef" ok-only>
        <template #header>
            <span>Error</span>
        </template>
        <template v-for="(error, index) in errors" :key="index">
            <div class="alert alert-danger" v-if="error">
                {{ useError(error) }}
            </div>
        </template>
    </b-modal>
</template>

<script setup>
import { ref, watch, watchEffect } from 'vue'
import { errors } from '@/cdp-mapping/store'
import { useError } from '@/utils/ApiClient'

const modalRef = ref()

watchEffect(async () => {
    const totalErrors = errors.value?.length ?? 0
    if (errors.value?.length === 0) return
    const modal = modalRef.value
    if (!modal) return
    await modal.show()
    errors.value = []
})
</script>

<style scoped></style>
