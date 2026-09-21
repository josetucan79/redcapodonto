<template>
    <div class="my-2 rounded border">
        <RecordIdentifierPanel />
    </div>
    <MappingTable />
    <hr>
    <div class="d-flex gap-2 justify-content-end mt-2">
        <button
            class="btn btn-sm btn-outline-secondary"
            @click="onResetClicked"
            :disabled="!isDirty || loading"
        >
            <i v-if="loading" class="fas fa-spinner fa-spin fa-fw me-1"></i>
            <i v-else class="fas fa-refresh fa-fw me-1"></i>
            <span>Reset</span>
        </button>
        <button
            class="btn btn-sm btn-primary"
            type="button"
            :disabled="!isDirty || loading"
            @click="onSaveClicked"
        >
            <i v-if="loading" class="fas fa-spinner fa-spin fa-fw me-1"></i>
            <i v-else class="fas fa-save fa-fw me-1"></i>
            <span>Save</span>
        </button>
    </div>
    <UnsavedChangesGuard
        :checkFn="checkFn"
        :saveFn="saveFn"
        :discardFn="discardFn"
    />
</template>

<script setup>
import { inject, toRefs } from 'vue'
import MappingTable from '@/cdp-mapping/components/mappings/MappingTable.vue'
import RecordIdentifierPanel from '@/cdp-mapping/components/mappings/record-identifier/RecordIdentifierPanel.vue'
import UnsavedChangesGuard from '@/cdp-mapping/components/common/UnsavedChangesGuard.vue'

const settingsManagerService = inject('settings-manager-service')
const mappingService = inject('mapping-service')
const { isDirty, loading } = toRefs(mappingService)

const save = async () => {
    const response = await mappingService.save()
    if (response) await settingsManagerService.getSettings()
}

const onSaveClicked = save

const checkFn = () => isDirty.value
const saveFn = () => save
const discardFn = () => mappingService.reset()
</script>

<style scoped></style>
