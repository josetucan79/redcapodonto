<template>
    <button
        type="button"
        class="btn btn-sm btn-outline-primary ms-auto"
        @click="onDownloadCsvClicked"
    >
        <i class="fas fa-file-csv fa-fw"></i>
        <span>Download CSV</span>
    </button>
</template>

<script setup>
import { generateCSVDownloadURL, forceFileDownload } from '@/utils/files'

const props = defineProps({
    errors: { type: Array, default: () => [] },
})

function onDownloadCsvClicked() {
    const rows = []
    props.errors.forEach((element) => {
        const row = {
            record: element?.field?.record ?? '',
            event_id: element?.field?.event_id ?? '',
            field_name: element?.field?.field_name ?? '',
            error: element?.error ?? '',
        }
        rows.push(row)
    })
    const csvData = generateCSVDownloadURL(rows)
    forceFileDownload(csvData, 'cdp-adjudication-errors')
}
</script>

<style scoped></style>
