<template>
    <div>
        <button class="btn btn-sm btn-success" @click="onApproveClicked">
            <template v-if="loading">
                <i class="fas fa-spinner fa-spin fa-fw me-1"></i>
            </template>
            <template v-else>
                <i class="fas fa-circle-check fa-fw me-1"></i>
            </template>
            <span>Approve</span>
        </button>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { approveRevision } from '../../API'
import { useAppStore } from '../../store'
import { useToaster } from 'bootstrap-vue'

const appStore = useAppStore()

const props = defineProps({
    revision: { type: Object, default: null },
})

const toaster = useToaster()

const loading = ref(false)

const revision = computed(() => props.revision)

async function onApproveClicked() {
    const revision_id = revision.value?.metadata?.id
    if (!revision_id) return
    try {
        loading.value = true
        const response = await approveRevision(revision_id)
        toaster.toast({ title: 'success', body: 'the revision was approved' })
        await appStore.init()
    } catch (error) {
        toaster.toast({
            title: 'error',
            body: 'the revision was not approved - '.error,
        })
    } finally {
        loading.value = false
    }
}
</script>

<style scoped></style>
