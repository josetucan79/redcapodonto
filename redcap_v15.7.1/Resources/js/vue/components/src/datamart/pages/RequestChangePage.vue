<template>
    <div class="mb-2">
        <button class="btn btn-sm btn-secondary" @click="onBackClicked">
            <i class="fas fa-chevron-left fa-fw"></i>
            <span>Back</span>
        </button>
    </div>
    <RevisionEditor :revision="revision">
        <template v-slot:buttons>
            <button class="btn btn-sm btn-secondary" @click="onBackClicked">
                <i class="fas fa-times fa-fw"></i>
                <span>Cancel</span>
            </button>

            <button class="btn btn-sm btn-primary" @click="onSubmitClicked" :disabled="!revisionUpdated">
                <i class="fas fa-check-circle fa-fw"></i>
                <span>Submit</span>
            </button>
        </template>
    </RevisionEditor>
</template>

<script setup>
import { useRouter } from 'vue-router'
import RevisionEditor from '@/datamart/components/RevisionEditor.vue'
import { useRevisionEditorStore, useRevisionsStore, useAppStore } from '../store'
import { computed } from 'vue'
import { useToaster } from 'bootstrap-vue'

const revisionEditorStore = useRevisionEditorStore()
const revisionsStore = useRevisionsStore()
const appStore = useAppStore()
const router = useRouter()
const toaster = useToaster()

// either select the currently editable or the selected one
const revision = computed(() => revisionsStore.edited ?? revisionsStore.selected?.data)
const revisionUpdated = computed(() => revisionEditorStore.isUpdated)

function goBack() {
    router.push({ name: 'home' })
}

function onBackClicked() {
    goBack()
}

async function onSubmitClicked() {
    try {
        const errors = revisionEditorStore.validate()
        if (errors?.length > 0) throw Error(errors?.join("\n"))
        const response = await revisionEditorStore.submit()
        toaster.toast({ title: 'Success', body: 'Revision submitted' })
        appStore.init()
        goBack()
    } catch (error) {
        toaster.toast({
            title: 'Error',
            body: error,
            autohide: false,
        })
    }
}
</script>

<style scoped></style>
