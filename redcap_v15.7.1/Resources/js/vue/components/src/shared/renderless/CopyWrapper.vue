<template>
    <slot :copy="copy"></slot>
</template>

<script setup>
import { useClipboard } from '../../utils/use'
import { useToaster } from 'bootstrap-vue'

const clipboard = useClipboard()
const toaster = useToaster()

function copy(text, notify = true, toastConfig = {}) {
    clipboard.copy(text)
    if (!notify) return

    toaster.toast({
        title: 'Success',
        body: `Text was copied to the clipboard`,
        ...toastConfig,
    })
}
</script>

<style scoped></style>
