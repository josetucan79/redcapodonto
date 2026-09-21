<template>
    <div>
        <template v-if="loading">
            <div>
                <i class="fas fa-spinner fa-spin fa-fw me-2"></i>
                <span>Loading...</span>
            </div>
        </template>
        <template v-else>
            <div>
                <InitializeCheck>
                    <BtgForm />
                </InitializeCheck>
            </div>
        </template>
        <Teleport to="body">
            <ErrorsVisualizer />
        </Teleport>
    </div>
</template>

<script setup>
import { inject, onMounted, ref, toRaw, watch } from 'vue'
import { useSettingsStore, usePatientsStore } from './store'
import BtgForm from './components/BtgForm.vue'
import InitializeCheck from './components/InitializeCheck.vue'
import ErrorsVisualizer from './components/ErrorsVisualizer.vue'

const loading = ref(false)
const settingsStore = useSettingsStore()
const patientsStore = usePatientsStore()

const eventBus = inject('eventBus')

watch(
    () => patientsStore.patients,
    () => {
        const details = {
            patients: toRaw(patientsStore.patients),
        }
        eventBus.emit('patients-loaded', details)
    },
    { immediate: true }
)

onMounted(async () => {
    loading.value = true
    await settingsStore.getSettings()
    // await patientsStore.fetchProtectedPatients()
    loading.value = false
})
</script>

<style scoped></style>
