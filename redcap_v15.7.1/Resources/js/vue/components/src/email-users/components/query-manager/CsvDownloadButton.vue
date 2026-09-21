<template>
    <template v-if="loading">
        <button type="button" class="btn btn-sm btn-secondary " @click="onDownloadClicked">
            <i class="fas fa-spinner fa-spin fa-fw me-1"></i>
            <span>Loading...</span>
        </button>
    </template>
    <template v-else>
        <template v-if="downloadURL">
            <button type="button" class="btn btn-sm btn-success " @click="onDownloadClicked">
                <i class="fas fa-file-csv fa-fw me-1"></i>
                <span>Download</span>
            </button>
        </template>
        <template v-else>
            <button type="button" class="btn btn-sm btn-secondary " @click="onGenerateClicked">
                <i class="fas fa-gears fa-fw me-1"></i>
                <span>Generate CSV...</span>
            </button>
        </template>
    </template>
</template>

<script setup>
import { ref, toRefs } from 'vue'
import { generateCSV } from '../../API'
import { useToaster } from 'bootstrap-vue'

const props = defineProps({
    query: { type: Object }
})
const { query } = toRefs(props)
const loading = ref(false)
const downloadURL = ref()

const toaster = useToaster()

const onGenerateClicked = async () => {
    try {
        loading.value = true
        downloadURL.value = null
        const response = await generateCSV(query.value)
        const { data } = response
        downloadURL.value = data?.url
        toaster.toast({ title: 'Success', body: 'CSV file ready. Click download.'})
    } catch (error) {
        console.log(error)
    } finally {
        loading.value = false
    }
}
const onDownloadClicked = async () => {
    location.href = downloadURL.value
    downloadURL.value = null
}
</script>

<style scoped>

</style>