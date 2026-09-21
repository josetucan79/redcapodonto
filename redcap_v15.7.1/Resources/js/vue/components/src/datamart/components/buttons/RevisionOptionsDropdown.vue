<template>
    <div>
        <DropDown
            variant="outline-secondary"
        >
            <template #button>
                <span>
                    <i class="fas fa-cog fa-fw"></i>
                </span>
            </template>

            <template #default>
                <DropDownItem @click="onExportSelected">
                    <i class="fas fa-file-export fa-fw me-1"></i>
                    <span>Export</span>
                </DropDownItem>
                <DropDownItem @click="onImportSelected">
                    <i class="fas fa-file-import fa-fw me-1"></i>
                    <span>Import</span>
                </DropDownItem>
            </template>
        </DropDown>

        <input
            type="file"
            class="d-none"
            ref="fileRef"
            @change="onFileChanged"
        />
    </div>
</template>

<script setup>
import { ref } from 'vue'
import DropDown from '../../../shared/DropDown/DropDown.vue'
import DropDownItem from '../../../shared/DropDown/DropDownItem.vue'
import { importRevision } from '../../API'
import { useRouter } from 'vue-router'
import { downloadCSV } from '../../../utils/files'
import { useRevisionsStore } from '../../store'
import { computed } from 'vue'

const revisionsStore = useRevisionsStore()
const router = useRouter()
const fileRef = ref()
const revision = computed(() => revisionsStore.selected)

function onExportSelected() {
    const id = revision.value?.metadata?.id
    downloadCSV([revision.value?.data], `revision-${id}.csv`)
}
function onImportSelected() {
    fileRef.value.click()
}

async function onFileChanged() {
    const { files } = fileRef.value
    if (files.length === 0) return

    const response = await importRevision(files[0])
    const revision = response.data
    revisionsStore.edited = revision
    router.push({ name: 'request-change' })
}
</script>

<style scoped>
.revision-entry {
    display: grid;
    grid-template-columns: min-content auto auto;
    gap: 0.5rem;
}
</style>
