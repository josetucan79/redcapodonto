<template>
    <div>
        <div class="border rounded p-2" v-if="response">
            <h3>Response</h3>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" @click="onCopyClicked">
                    <i class="fas fa-copy fa-fw me-1"></i>
                    <span>Copy</span>
                </button>
                <button class="btn btn-sm btn-outline-primary" @click="onDownloadClicked">
                    <i class="fas fa-file-arrow-down fa-fw me-1"></i>
                    <span>Download</span>
                </button>
                <!-- <button class="btn btn-sm btn-outline-secondary" @click="onTogglePayloadClicked">
                    <template v-if="showPayload">
                        <i class="fas fa-eye-slash fa-fw me-1"></i>
                        <span>Hide</span>
                    </template>
                    <template v-else>
                        <i class="fas fa-eye fa-fw me-1"></i>
                        <span>Show</span>
                    </template>
                </button> -->
            </div>
            <JsonTree :json="response" v-if="showPayload"></JsonTree>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useCustomRequestStore } from '../../store'
import JsonTree from '../../../shared/JsonTree.vue'
import { useClipboard } from '../../../utils/use'
import { download } from '../../../utils/files'
import { useToaster } from 'bootstrap-vue'

const customRequestStore = useCustomRequestStore()
const toaster = useToaster()
const clipboard = useClipboard()

const showPayload = ref(true)

function onDownloadClicked() {
    const response = customRequestStore?.response
    if (!response) return
    const text = JSON.stringify(response, null, '\t')
    download(text, { fileName: 'download.json' })
}

async function onCopyClicked() {
    try {
        const text = JSON.stringify(customRequestStore?.response, null, 2)
        await clipboard.copy(text)
        toaster.toast({ title: 'Success', body: 'Payload copied to clipboard' })
    } catch (error) {
        toaster.toast({
            title: 'Error',
            body: `Payload NOT copied to clipboard - ${error}`,
        })
    }
}

function onTogglePayloadClicked() {
    showPayload.value = !showPayload.value
}

const response = computed(() => customRequestStore.response)
</script>

<style scoped></style>
