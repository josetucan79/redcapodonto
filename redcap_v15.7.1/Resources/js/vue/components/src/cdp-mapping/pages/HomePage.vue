<template>
    <details open>
        <summary>
            <span class="fw-bold">Settings</span>
        </summary>
        <SettingsPanel class="my-2"/>
    </details>
    <details open>
        <summary>
            <span class="fw-bold">Mappings</span>
        </summary>
        <MappingPanel />
    </details>
    <div class="sticky-bottom bg-white p-2 border-top">
        <div class="d-flex gap-2 justify-content-end">
            <button
                class="btn btn-sm btn-outline-secondary"
                @click="onResetClicked"
                :disabled="!isDirty || isLoading"
            >
                <i class="fas fa-refresh fa-fw me-1"></i>
                <span>Reset</span>
            </button>
            <button
                class="btn btn-sm btn-primary"
                type="button"
                :disabled="!isDirty || isLoading"
                @click="onSaveClicked"
            >
                <i v-if="isLoading" class="fas fa-spinner fa-spin fa-fw me-1"></i>
                <i v-else class="fas fa-save fa-fw me-1"></i>
                <span>Save</span>
            </button>
        </div>
    </div>
    <UnsavedChangesGuard
        :checkFn="checkFn"
        :saveFn="saveFn"
        :discardFn="discardFn"
    />
</template>

<script setup>
import { computed, inject, toRefs } from 'vue'
import SettingsPanel from '../components/settings/SettingsPanel.vue'
import MappingPanel from '../components/mappings/MappingPanel.vue'
import UnsavedChangesGuard from '@/cdp-mapping//components/common/UnsavedChangesGuard.vue'
import { useToaster } from 'bootstrap-vue'

const toaster = useToaster()
const settingsManagerService = inject('settings-manager-service')
const mappingService = inject('mapping-service')
const { isDirty: isDirtyMapping, loading: loadingMapping } =
    toRefs(mappingService)
const { isDirty: isDirtySettings, loading: loadingSettings } = toRefs(
    settingsManagerService
)

const isLoading = computed(() => loadingMapping.value || loadingSettings.value)
const isDirty = computed(() => isDirtyMapping.value || isDirtySettings.value)

const save = async () => {
    const response1 = await settingsManagerService.save()
    const response2 = await mappingService.save()
    if (response1 && response2) {
        await settingsManagerService.getSettings()
        toaster.toast({title: 'Success', body: 'Data saved'})
    }
}

const reset = () => {
    mappingService.reset()
    settingsManagerService.reset()
}

const onSaveClicked = save

const checkFn = () => isDirty.value
const saveFn = () => save()
const discardFn = () => reset()

const onResetClicked = reset
</script>

<style scoped>
.modal-open .sticky-bottom {
    position: unset;
}
</style>
