<template>
    <div class="p-2 fhir-system-page">
        <p>
            This interface enables the connection of REDCap with multiple FHIR
            (Fast Healthcare Interoperability Resources) systems. FHIR is a
            standard for electronic healthcare information exchange, while SMART
            on FHIR provides specifications for integrating apps with Electronic
            Health Records using FHIR standards and OAuth2 security.
        </p>

        <h6>Using This Page:</h6>
        <ol>
            <li>
                <strong>Navigation Tabs:</strong> Each tab corresponds to a
                different FHIR system. Select a tab to view or edit its
                settings.
            </li>
            <li>
                <strong>FHIR System Settings:</strong> In each tab, fill in the
                necessary information for connecting to a FHIR system.
            </li>
            <li>
                <strong>Adding a New FHIR System:</strong> Click the button next
                to the navigation tabs to add a new system. A new tab will
                appear for entering the new system's settings.
            </li>
            <li>
                <strong>Reorder the FHIR Systems:</strong> Drag and drop a tab
                to change the order of the FHIR systems. After changing the
                order, click the save icon to persist the change. The first one
                will be used as the default for projects where a specific FHIR
                system is not selected.
            </li>
        </ol>
        <div class="d-flex gap-2 fhir-system-actions border-bottom py-2">
            <div class="dropdown">
                <button
                    class="btn btn-secondary dropdown-toggle"
                    type="button"
                    id="dropdownMenuButton1"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    <i class="fas fa-cog fa-fw"></i>
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a
                            class="dropdown-item"
                            href="#"
                            @click.prevent="onImportClicked"
                        >
                            <i class="fas fa-file-import fa-fw me-1"></i>
                            <span>Import</span>
                        </a>
                    </li>
                    <li>
                        <a
                            class="dropdown-item"
                            href="#"
                            @click.prevent="onExportClicked"
                        >
                            <i class="fas fa-file-export fa-fw me-1"></i>
                            <span>Export</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button
                    type="button"
                    class="btn btn-sm btn-danger"
                    @click="onRemoveClicked"
                >
                    <i class="fas fa-trash fa-fw me-1"></i>
                    <span>Delete</span>
                </button>
            </div>
            <button
                type="button"
                class="btn btn-sm btn-success"
                @click="onAddClicked"
            >
                <i class="fas fa-plus fa-fw me-1"></i>
                <span>Add</span>
            </button>
        </div>
        <div>
            <FhirSystemsTabs
                v-model:elements="fhirSystems"
                :active="currentSystem"
                :save-pending="savePending"
                :loading="loading"
                @system-selected="onSystemSelected"
            />
    
            <div class="p-2 border-top">
                <FhirSettingsForm
                    v-model:data="systemForm"
                    :redirect-url="redirectURL"
                />
    
                <div class="d-flex justify-content-end mt-2 footer py-2 border-top">
                    <template v-if="loading">
                        <button class="btn btn-sm btn-primary" disabled>
                            <i class="fas fa-spinner fa-spin fa-fw me-1"></i>
                            <span>Loading...</span>
                        </button>
                    </template>
                    <template v-else>
                        <button
                            class="btn btn-sm btn-primary"
                            @click="onSaveClicked"
                            :disabled="!savePending"
                        >
                            <i class="fas fa-save fa-fw me-1"></i>
                            <span>Save</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    
        <b-modal ref="newSystemModal" size="xl">
            <template #title>New FHIR System</template>
            <template #footer="{ hide }">
                <div class="d-flex justify-content-end gap-2">
                    <button
                        class="btn btn-sm btn-secondary"
                        type="button"
                        @click="hide"
                    >
                        <i class="fas fa-times fa-fw me-1"></i>
                        <span>Cancel</span>
                    </button>
                    <button
                        class="btn btn-sm btn-primary"
                        type="button"
                        @click="onNewSystemAccepted"
                    >
                        <i class="fas fa-save fa-fw me-1"></i>
                        <span>Save</span>
                    </button>
                </div>
            </template>
            <FhirSettingsForm
                v-model:data="newSystemData"
                :redirect-url="redirectURL"
            />
        </b-modal>
    </div>

</template>

<script setup>
import { computed, onBeforeUnmount, ref, toRefs } from 'vue'
import { useFhirSystemStore, useMainStore, useAppSettingsStore } from '../store'
import FhirSystemsTabs from '../components/FhirSystemsTabs.vue'
import FhirSettingsForm from '../components/FhirSettingsForm.vue'
import { useModal } from 'bootstrap-vue'
import { useToaster } from 'bootstrap-vue'
import { useError } from '../../utils/ApiClient'
import useFileReader from '../../utils/useFileReader'
import { download } from '../../utils/files'

const fhirSystemStore = useFhirSystemStore()
const mainStore = useMainStore()
const appSettingsStore = useAppSettingsStore()
const modal = useModal()
const toaster = useToaster()
const fileReader = useFileReader()

const { loading, form: systemForm, current: currentSystem } = toRefs(fhirSystemStore)
const redirectURL = computed(() => appSettingsStore.redirectURL)
const savePending = computed(() => mainStore.savePending)
const fhirSystems = computed({
    get: () => fhirSystemStore.list,
    set: (value) => (fhirSystemStore.list = value),
})

const newSystemModal = ref()
const newSystemData = ref({})

async function onSystemSelected(fhirSystem, index) {
    /* const saveConfirmed = await checkNeedsSaving()
    if (saveConfirmed) {
        await saveEhrSettings()
    } */
    fhirSystemStore.setCurrent(fhirSystem)
}

async function onSaveClicked() {
    try {
        const currentEhrId = currentSystem.value?.ehr_id
        const result = await fhirSystemStore.save()
        await mainStore.loadSettings()
        const previouslySelected = fhirSystemStore.findByEhrId(currentEhrId)
        if(!previouslySelected) return
        fhirSystemStore.setCurrent(previouslySelected)
        toaster.toast({ title: 'Success', body: 'Settings saved' })
    } catch (error) {
        const message = useError(error)
        toaster.toast({ title: 'Error', body: message })
    }
}

function onAddClicked() {
    newSystemData.value = fhirSystemStore.makeNewSystem()
    newSystemModal.value?.show()
}

async function onNewSystemAccepted() {
    try {
        const id = await fhirSystemStore.add(newSystemData.value)
        toaster.toast({ title: 'Success', body: 'New FHIR system created' })
        await mainStore.loadSettings()
    } catch (error) {
        const message = useError(error)
        toaster.toast({ title: 'Error', body: message })
    } finally {
        newSystemModal.value?.hide()
    }
}

async function onRemoveClicked() {
    const confirmed = await modal.confirm({
        title: 'Confirm delete',
        body: 'Are you sure you want to delete this element?',
    })
    if (!confirmed) return
    const currentElement = currentSystem.value
    const ehr_id = currentElement?.ehr_id

    if (ehr_id < 0) fhirSystemStore.remove(currentElement)
    else {
        await fhirSystemStore.delete(ehr_id)
        await mainStore.loadSettings()
    }
    toaster.toast({
        title: 'Success',
        body: 'The settings were removed successfully.',
    })
}

async function onImportClicked() {
    const contents = await fileReader.select()
    parseFilesContent(contents)
}
function parseFilesContent(filesContent) {
    if (filesContent?.length < 1) {
        console.error('no files selected')
        return
    }
    const fileContent = filesContent?.[0]
    try {
        const parsed = JSON.parse(fileContent)
        newSystemData.value = parsed
        newSystemModal.value?.show()
        toaster.toast({
            title: 'Import Successful',
            body: 'The file was imported. Please review the data before saving.',
        })
    } catch (error) {
        toaster.toast({
            title: 'Import Error',
            body: 'There was an error importing the file. Please make sure to select a valid JSON file.',
        })
        console.error(error)
    }
}
function onExportClicked() {
    const fhirSystem = fhirSystemStore.current
    if (!fhirSystem) return
    const fileContent = JSON.stringify(fhirSystem)
    const fileName = `${fhirSystem.ehr_name}.json`
    download(fileContent, {
        fileName: fileName,
    })
    toaster.toast({
        title: 'Export Successful',
        body: `The setttings have been exported as '${fileName}'.`,
    })
}

// watch(() => )

onBeforeUnmount(() => {
    // checkNeedsSaving()
})
</script>

<style scoped>
.ellipsis {
    overflow: hidden; /* Ensures the text is clipped */
    white-space: nowrap; /* Prevents the text from wrapping to the next line */
    text-overflow: ellipsis; /* Adds an ellipsis to the end of the text */
    max-width: 200px; /* Or set to the desired width */
    display: inline-block;
}
/* .fhir-system-page {}
.fhir-system-actions {
    position: sticky;
    top: 35px;
    background-color: white;
    z-index: 10;
} */
.footer {
    position: sticky;
    bottom: 0;
    background-color: white;
    z-index: 10;
}
</style>
