<template>
    <div v-if="appStore?.canBeModified">
        <button
            class="btn btn-sm btn-success"
            @click="onFixClicked"
            :disabled="loading"
        >
            <template v-if="loading">
                <i class="fas fa-spinner fa-spin fa-fw me-1"></i>
            </template>
            <template v-else>
                <i class="fas fa-wrench fa-fw me-1"></i>
            </template>
            <span>Fix design</span>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAppStore, useFixDesignStore } from '../store'

const appStore = useAppStore()
const fixDesignStore = useFixDesignStore()
import { useError } from '../../utils/apiClient'

const emit = defineEmits(['fix-success', 'fix-error'])

const loading = computed(() => fixDesignStore?.loading ?? false)

async function onFixClicked() {
    let message = ''
    try {
        const response = await fixDesignStore.fixDesign()
        const success = response?.data?.success ?? false
        message = response?.data?.message ?? ''
        if (success === true) emit('fix-success', message)
        else emit('fix-error', message)
    } catch (error) {
        message = useError(error)
        emit('fix-error', message)
    }
}
</script>

<style scoped></style>
