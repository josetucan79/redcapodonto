<template>
    <div>
        <div class="settings-panels">
            <div class="settings-row">
                <div>
                    <span class="fw-bold" v-tt:preview_fields_title></span>
                    <PreviewPanelDescription />
                </div>
                <PreviewPanel />
            </div>
            <div class="settings-row">
                <div>
                    <span class="fw-bold" v-tt:default_day_offset_title></span>
                    <OffsetDaysPanelDescription />
                </div>
                <OffsetDaysPanel />
            </div>
            <div class="settings-row">
                <div>
                    <span class="fw-bold" v-tt:adjudication_method_title></span>
                    <AdjudicationMethodPanelDescription />
                </div>
                <AdjudicationMethodPanel />
            </div>
        </div>
        <div class="d-flex gap-2 justify-content-end mt-2">
            <button
                class="btn btn-sm btn-outline-secondary"
                @click="onResetClicked"
                :disabled="loading || !isDirty"
            >
                <i v-if="loading" class="fas fa-spinner fa-spin fa-fw me-1"></i>
                <i v-else class="fas fa-refresh fa-fw me-1"></i>
                <span>Reset</span>
            </button>
            <button
                class="btn btn-sm btn-primary"
                type="button"
                :disabled="loading || !isDirty"
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
    </div>
</template>

<script setup>
import PreviewPanelDescription from '@/cdp-mapping/components/settings/preview/PreviewPanelDescription.vue'
import PreviewPanel from '@/cdp-mapping/components/settings/preview/PreviewPanel.vue'
import AdjudicationMethodPanelDescription from '@/cdp-mapping/components/settings/adjudication-method/AdjudicationMethodPanelDescription.vue'
import AdjudicationMethodPanel from '@/cdp-mapping/components/settings/adjudication-method/AdjudicationMethodPanel.vue'
import OffsetDaysPanelDescription from '@/cdp-mapping/components/settings/offset-days/OffsetDaysPanelDescription.vue'
import OffsetDaysPanel from '@/cdp-mapping/components/settings/offset-days/OffsetDaysPanel.vue'
import UnsavedChangesGuard from '@/cdp-mapping//components/common/UnsavedChangesGuard.vue'
import { computed, inject } from 'vue'

const settingsManagerService = inject('settings-manager-service')
const isDirty = computed(() => settingsManagerService.isDirty)
const loading = computed(() => settingsManagerService.loading)

const onResetClicked = () => {
    settingsManagerService.reset()
}

const save = async () => {
    const response = await settingsManagerService.save()
    if (response) await settingsManagerService.getSettings()
}

const onSaveClicked = save

const checkFn = () => isDirty.value
const saveFn = save
const discardFn = () => settingsManagerService.reset()
</script>

<style scoped>
:has(.settings-panels) {
    padding: 10px;
}
.settings-panels {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    /* gap: var(--gap-size); */
    gap: 0;
    border: 1px solid #ccc;
    border-radius: 5px;
}
.settings-row {
    display: contents; /* Allows borders to wrap the row */
}

.settings-row > * {
    padding: 10px;
}
.settings-row > :nth-child(2) {
    border-left: solid 1px #ccc;
}

.settings-row + .settings-row::before {
    content: '';
    display: block;
    border-top: 1px solid #ccc;
    grid-column: 1 / -1; /* Span all columns */
}
</style>
