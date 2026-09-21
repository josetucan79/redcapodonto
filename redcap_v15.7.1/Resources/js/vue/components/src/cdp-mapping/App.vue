<template>
    <div data-cdp-mapping>
        <template v-if="ready">
            <router-view></router-view>
        </template>
        <template v-else>
            <LoadingIndicator />
        </template>
        <ErrorsVisualizer />
    </div>
</template>

<script setup>
import { onMounted, provide, ref } from 'vue'
import { useSettingsManagerService, useMappingService } from './services'
import { useAppStore, useSettingsStore } from '@/cdp-mapping/store'

const appStore = useAppStore()
const settingsStore = useSettingsStore()
const mappingService = useMappingService()
const settingsManagerService = useSettingsManagerService()

provide('app-store', appStore)
provide('settings-store', settingsStore)
provide('mapping-service', mappingService)
provide('settings-manager-service', settingsManagerService)

import ErrorsVisualizer from './components/common/ErrorsVisualizer.vue'
import LoadingIndicator from './components/common/LoadingIndicator.vue'

const ready = ref(false)

onMounted(async () => {
    await settingsManagerService.getSettings()
    ready.value = true
})
</script>
<style>
/* define variables */
[data-cdp-mapping] {
    --gap-size: 10px;
}
.btn-xs {
    padding: 1px 5px;
    font-size: 12px;
    line-height: 1.5;
    border-radius: 3px;
}
</style>
<style scoped></style>
