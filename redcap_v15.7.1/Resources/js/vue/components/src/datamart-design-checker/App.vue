<template>
    <div v-if="ready">
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <!-- there was an error -->
            <template v-if="error">
                <b-toast
                    visible
                    :autohide="false"
                    variant="danger"
                    title="Design Health check error"
                >
                    {{ error }}
                </b-toast>
            </template>

            <!-- everything is ok -->
            <template v-else-if="commands?.length === 0">
                <b-toast visible :delay="okCheckAutoCloseInterval" variant="success">
                    <template #title>
                        <div class="me-auto">
                            <span class="d-block">Design Health check</span>
                        </div>
                    </template>
                    <i class="fas fa-check-to-slot fa-fw me-1"></i>
                    <span>All tests successful</span>
                </b-toast>
            </template>

            <!-- there are problems to correct -->
            <template v-else>
                <b-toast visible :delay="warningCheckAutoCloseInterval" variant="warning">
                    <template #title>
                        <div class="me-auto">
                            <span class="d-block">Design mismatch</span>
                        </div>
                    </template>
                    <template #default="{ hide }">
                        <i class="fas fa-circle-exclamation fa-fw me-1"></i>
                        <span
                            >The design of this project could prevent the Data
                            Mart feature from working as intended.</span
                        >
                        <div class="mt-2 d-flex">
                            <button
                                class="ms-auto btn btn-sm btn-secondary"
                                @click="onLearnMoreClicked(hide())"
                            >
                                <i class="fas fa-circle-info fa-fw me-1"></i>
                                <span>Learn more</span>
                            </button>
                        </div>
                    </template>
                </b-toast>
            </template>
        </div>

        <!-- commands list in a modal -->
        <b-modal ref="commandsModalRef" size="lg" title="Design mismatch">
            <div>
                <ActionsList :commands="commands" />
                <WarningsList />
                <div class="d-flex gap-2">
                    <CriticalityLevelsLegend />
                    <ActionTypesLegend />
                </div>
            </div>
            <template #footer="{ hide }">
                <ActionButton
                    @fix-success="onFixSuccess"
                    @fix-error="onFixError"
                />
                <button class="btn btn-sm btn-secondary" @click="hide">
                    <i class="fas fa-times fa-fw me-1"></i>
                    <span>Close</span>
                </button>
            </template>
        </b-modal>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAppStore } from './store'
import { useModal } from 'bootstrap-vue'
import ActionsList from './components/ActionsList.vue'
import ActionButton from './components/ActionButton.vue'
import CriticalityLevelsLegend from './components/CriticalityLevelsLegend.vue'
import ActionTypesLegend from './components/ActionTypesLegend.vue'
import WarningsList from './components/WarningsList.vue'

const appStore = useAppStore()
const modal = useModal()

// set auto-delay interval for toasts
const okCheckAutoCloseInterval = ref(5000)
const warningCheckAutoCloseInterval = ref(20000)

const error = computed(() => appStore?.error)
const ready = computed(() => appStore?.ready ?? false)
const commands = computed(() => appStore?.commands ?? [])

const commandsModalRef = ref()

function onLearnMoreClicked() {
    commandsModalRef.value?.show()
}

async function onFixSuccess(message) {
    commandsModalRef.value?.hide()
    await modal.alert({ title: 'Success', body: message })
    appStore.init()
}
async function onFixError(message) {
    commandsModalRef.value?.hide()
    await modal.alert({ title: 'Error', body: message })
    appStore.init()
}

onMounted(() => {
    appStore.init()
})
</script>

<style scoped></style>
