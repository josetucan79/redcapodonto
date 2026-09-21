<template>
    <b-modal ref="modalRef" ok-only>
        <template #title>
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
import { ref, watchEffect } from 'vue'
import { useError } from '@/utils/ApiClient'
import { getErrors, resetErrors } from '@/utils/store/plugins'

const modalRef = ref()

const errors = getErrors()

watchEffect(async () => {
    const totalErrors = errors.value?.length ?? 0
    if (errors.value?.length === 0) return
    const modal = modalRef.value
    if (!modal) return
    await modal.show()
    resetErrors()
})
</script>

<style scoped></style>
