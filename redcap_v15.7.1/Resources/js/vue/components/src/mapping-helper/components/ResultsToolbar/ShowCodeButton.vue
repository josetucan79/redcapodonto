<template>
    <div>
        <button
            class="btn btn-sm btn-outline-secondary"
            @click="jsonModalRef.show()"
            :disabled="!payload"
        >
            <i class="fas fa-code fa-fw me-1"></i>
            <span>Show payload</span>
        </button>
    </div>
    <b-modal ref="jsonModalRef" size="xl" ok-only title="Payload">
        <div class="tree-wrapper">
            <JsonTree :json="payload" />
        </div>
        <template #footer="{}">
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" @click="onCopyClicked">
                    <i class="fas fa-copy fa-fw me-1"></i>
                    <span>Copy</span>
                </button>
                <button
                    class="btn btn-sm btn-outline-primary"
                    @click="onDownloadClicked"
                >
                    <i class="fas fa-file-arrow-down fa-fw me-1"></i>
                    <span>Download</span>
                </button>
            </div>
            <div>
                <button
                    class="btn btn-sm btn-secondary"
                    @click="jsonModalRef.hide()"
                >
                    <span>Close</span>
                </button>
            </div>
        </template>
    </b-modal>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useSearchStore } from '../../store'
import JsonTree from '../../../shared/JsonTree.vue'
import { useClipboard } from '../../../utils/use'
import { useToaster } from 'bootstrap-vue'
import { download } from '../../../utils/files'

const searchStore = useSearchStore()
const toaster = useToaster()

const clipboard = useClipboard()

const jsonModalRef = ref()
const payload = computed(() => searchStore.active?.data?.metadata?.payload)

function onDownloadClicked() {
    if (!payload.value) return
    const text = JSON.stringify(payload.value, null, '\t')
    download(text, { filename: 'download.json' })
}

async function onCopyClicked() {
    try {
        const text = JSON.stringify(payload.value, null, 2)
        await clipboard.copy(text)
        toaster.toast({ title: 'Success', body: 'Payload copied to clipboard' })
    } catch (error) {
        toaster.toast({
            title: 'Error',
            body: `Payload NOT copied to clipboard - ${error}`,
        })
    }
}
</script>

<style scoped>
.tree-wrapper {
    max-height: 75vh;
    overflow: auto;
}
</style>
