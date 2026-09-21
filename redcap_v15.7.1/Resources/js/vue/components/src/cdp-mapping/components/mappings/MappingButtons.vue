<template>
    <div class="d-flex gap-2 align-items-center">
        <span v-tt:mapping_table_title class="fw-bold"></span>
        <div class="ms-auto d-flex gap-2">
            <div class="btn-group ms-auto" role="group" aria-label="Basic example">
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="onExportClicked">
                    <i class="fas fa-download fa-fw me-1"></i>
                    <span v-tt:export>export</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="onImportClicked">
                    <i class="fas fa-upload fa-fw me-1"></i>
                    <span v-tt:import>import</span>
                </button>
            </div>
            <button type="button" class="btn btn-sm btn-success" @click="onAddClicked">
                <i class="fas fa-list fa-fw me-1"></i>
                <span v-tt:find_more_sources_fields_to_map>Add new entry</span>
            </button>
        </div>
    </div>
    <div class="d-none">
        <input type="file" ref="uploadRef" @change="onFileChanged" />
    </div>
    <Teleport to="body">
        <b-dialog ref="modalRef" ok-only :text-ok="`Close`">
            <template #title>
                <span v-tt:mappings_export_title class="py-2 fw-medium">Mapping Export</span>
            </template>
            <div class="d-flex flex-column justify-content-center align-items-center text-center">
                <span v-tt:mappings_export_description>Your data is ready to be downloaded</span>
                <span class="text-muted fst-italic" v-tt:mappings_export_note>record ID and other mapped fields</span>
                <a :href="downloadLink" target="_blank" class="d-flex btn btn-outline-secondary flex-column justify-content-center align-items-center">
                    <i class="fas fa-file-csv fa-fw fa-5x"></i>
                    <span v-tt:mappings_export_download_link>download</span>
                </a>
            </div>
        </b-dialog>
    </Teleport>
</template>

<script setup>
import { inject, ref } from 'vue'
const mappingService = inject('mapping-service')

const modalRef = ref()
const uploadRef = ref()
const downloadLink = ref()

async function onFileChanged(event) {
    const files = event.target.files
    const file = files?.[0]
    if (!file) return
    await mappingService.importMappings(file)
    event.target.value = '' // reset the file input
}

const onAddClicked = () => mappingService.createEntry()
const onExportClicked = async () => {
    downloadLink.value = null
    const url = await mappingService.exportMappings()
    downloadLink.value = url
    if (downloadLink.value) modalRef.value.show()
}
const onImportClicked = () => uploadRef.value.click()
</script>

<style scoped></style>
