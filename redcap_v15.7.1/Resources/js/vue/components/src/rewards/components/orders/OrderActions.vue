<template>
    <div class="d-flex gap-2 justify-content-center w-100 text-nowrap">
        <div class="btn-group mr-2" role="group" aria-label="First group">
            <a
                class="btn btn-sm btn-primary text-white text-start"
                :href="order.redeem_link"
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
        <button
            class="btn btn-sm btn-primary"
            @click="sendOrderEmail"
            :disabled="loading"
        >
            <template v-if="loading">
                <i class="fas fa-spinner fa-spin fa-fw"></i>
            </template>
            <template v-else>
                <i class="fas fa-envelope fa-fw"></i>
            </template>
            <span class="ms-1">Re-send Email</span>
        </button>
    </div>
</template>

<script setup>
import { inject, computed } from 'vue'
import { useAppStore } from '@/rewards/store'
import { useClipboard } from '@/utils/use'
import { useToaster } from 'bootstrap-vue'

const appStore = useAppStore()
const clipboard = useClipboard()
const toaster = useToaster()

const loading = computed(() => appStore.loading)
const record = inject('record')
const order = inject('order')

async function sendOrderEmail() {
    const record_id = record.value?.record_id
    const order_id = order.value?.order_id
    await appStore.sendOrderEmail(record_id, order_id)
    toaster.toast({ title: 'Success', body: 'Email sent' })
}

async function onCopyLinkClicked() {
    await clipboard.copy(order?.redeem_link)
    toaster.toast({ title: 'Success', body: 'Link copied to clipboard' })
}
</script>

<style scoped></style>
