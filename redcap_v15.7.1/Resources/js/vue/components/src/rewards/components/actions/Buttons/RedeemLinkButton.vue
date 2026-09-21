<template>
    <div class="btn-group mr-2" role="group" aria-label="First group">
        <a
            class="btn btn-sm btn-primary text-white text-start"
            :href="currentOrder.redeem_link"
            target="_blank"
        >
            <i class="fas fa-arrow-up-right-from-square fa-fw"></i>
            <span class="ms-1">Redeem link</span>
        </a>

        <button
            type="button"
            class="btn btn-sm btn-outline-primary ms-auto"
            @click="onCopyLinkClicked"
        >
            <i class="fas fa-copy fa-fw"></i>
        </button>
    </div>
</template>

<script setup>
import { inject, toRefs } from 'vue'
import { useClipboard } from '@/utils/use'
import { useToaster } from 'bootstrap-vue'

const clipboard = useClipboard()
const toaster = useToaster()

const approvalService = inject('approval-service')
const { currentOrder } = toRefs(approvalService)

async function onCopyLinkClicked() {
    await clipboard.copy(currentOrder.value?.redeem_link)
    toaster.toast({ title: 'Success', body: 'Link copied to clipboard' })
}
</script>

<style scoped></style>
