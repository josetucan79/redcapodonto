<template>
    <b-dropdown menu-end variant="success" size="sm" v-if="!processing" :disabled="_disabled">
        <template #button>
            <i class="fa-solid fa-cloud-arrow-down fa-fw"></i>
            <span class="mx-1">Fetch data</span>
            <span class="badge bg-primary">{{ totalMRNs }} records</span>
        </template>
        <b-dropdown-item data-prevent-close>
            <div class="form-check">
                <input
                    class="form-check-input"
                    type="checkbox"
                    v-model="backgroundFetch"
                    id="fetch-background-checkbox"
                />
                <label class="form-check-label" for="fetch-background-checkbox">
                    <i class="fas fa-clock fa-fw me-1"></i>
                    <span>Fetch in a background process</span>
                </label>
            </div>
        </b-dropdown-item>
        <b-dropdown-item data-prevent-close>
            <div class="form-check">
                <input
                    class="form-check-input"
                    type="checkbox"
                    v-model="sendMessage"
                    id="send-message-checkbox"
                    :disabled="!backgroundFetch"
                />
                <label class="form-check-label" for="send-message-checkbox">
                    <i class="fas fa-envelope fa-fw me-1"></i>
                    <span>Send me a message when completed</span>
                </label>
            </div>
        </b-dropdown-item>
        <b-dropdown-divider></b-dropdown-divider>
        <b-dropdown-item>
            <div @click="onConfirmClicked">
                <i class="fas fa-check fa-fw me-1"></i>
                <span>Confirm</span>
            </div>
        </b-dropdown-item>
    </b-dropdown>
    <button v-else class="btn btn-sm btn-outline-primary" disabled>
        <i class="fas fa-spinner fa-spin fa-fw me-1"></i>
        <span>Processing</span>
    </button>
</template>

<script setup>
import { computed, ref, toRefs, watchEffect } from 'vue'
import { useAppStore, useProcessStore, useRevisionsStore, useUserStore } from '../../store/'
import { AxiosError } from 'axios'
import { useModal, useToaster } from 'bootstrap-vue'

const appStore = useAppStore()
const processStore = useProcessStore()
const revisionsStore = useRevisionsStore()
const userStore = useUserStore()
const modal = useModal()
const toaster = useToaster()

const emit = defineEmits(['fetch-confirmed'])
const props = defineProps({
    disabled: { type: Boolean, default: false },
    mrns: { type: Array, default: null },
})

const { mrns, disabled } = toRefs(props)

const selectedRevisionIsActive = computed(() => revisionsStore.selected === revisionsStore.active)
const selectedRevisionIsApproved = computed(() => revisionsStore?.selected?.metadata?.approved)
const backgroundFetch = ref(false)
const sendMessage = ref(false)
const processing = computed(() => processStore.processing)
const totalFetchableMRNs = computed(() => revisionsStore?.selected?.metadata?.total_fetchable_mrns ?? 0 )
const totalMRNs = computed(() => {
    // the number of MRNs provided as prop has priority to the ones in the selected revision
    if (Array.isArray(mrns.value)) return mrns.value.length
    return totalFetchableMRNs.value
})
const _disabled = computed(() => {
    // disabled prop has priority
    if (disabled.value) return disabled.value
    // disable if the selected revision is not the active one
    if (!selectedRevisionIsActive.value) return true
    // check if the revision is approved
    if (!selectedRevisionIsApproved.value) return true
    // disable if number of MRNs is 0
    if (totalMRNs.value === 0) return true
    // disable if user cannot run this revision
    if (!userStore.canRunRevision(revisionsStore.selected)) return true
    return false
})

watchEffect(() => {
    // turn off sendMessage if background is false
    if (backgroundFetch.value === false) sendMessage.value = false
})

async function onConfirmClicked() {
    emit('fetch-confirmed', {
        backgroundFetch: backgroundFetch.value,
        sendMessage: sendMessage.value,
    })
    try {
        if (backgroundFetch.value) {
            const response = await processStore.schedule(revisionsStore.selected, mrns.value, sendMessage.value)
            const message = response?.data?.message ?? 'the process was successfully scheduled'
            toaster.toast({ title: 'Success', body: message })
        } else {
            await processStore.run(revisionsStore.selected, mrns.value)
        }
    } catch (error) {
        let message = error
        if (error instanceof AxiosError) {
            message = error?.response?.data?.message ?? 'unexpected error'
        }
        modal.alert({ title: 'Error', body: message })
    } finally {
        // always reload after a confirmation check
        appStore.init()
    }
}
</script>

<style scoped></style>
