<template>
    <div>
        <div class="card mt-2">
            <section class="card-header d-flex">
                <template v-if="revision">
                    <RevisionMetadata class="fs-6" :revision="revision" />
                </template>
                <template v-else>
                    <span>No revision selected</span>
                </template>
            </section>
            <section class="card-body">
                <slot name="body-start"></slot>
                <div>
                    <span class="fw-bold">Date range: </span>
                    <span>{{ dateText }}</span>
                </div>
                <span class="fw-bold">Fields in EHR for which to pull data:</span>
                <MetadataNode :node="node" class="revision-fields border rounded">
                    <template #container-end="{ node }">
                        <template v-if="categoryHasDateRange(node.name)">
                            <div class="text-nowrap">
                                <i class="fas fa-clock fa-fw"></i>
                                <span>date range is applied</span>
                            </div>
                        </template>
                    </template>
                </MetadataNode>
                <slot name="body-end"></slot>
            </section>
            <section class="card-footer d-flex justify-content-end gap-2">
                <slot name="footer"></slot>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, toRefs } from 'vue'
import { useSettingsStore } from '../store'
import moment from 'moment'
import { Container } from '../models'
import MetadataNode from './MetadataNode.vue'
import RevisionMetadata from './RevisionMetadata.vue'

const settingsStore = useSettingsStore()

const props = defineProps({
    revision: { type: Object },
})
const { revision } = toRefs(props)
const metadata = computed(() => settingsStore?.fhirMetadata)

const dateRange = computed(() => {
    const _range = { min: null, max: null }
    const selectedRevision = revision.value
    if (selectedRevision) {
        let dateFormat = 'MM-DD-YYYY'
        let dateMin = moment(selectedRevision?.data?.dateMin)
        let dateMax = moment(selectedRevision?.data?.dateMax)
        if (dateMin.isValid()) _range.min = dateMin.format(dateFormat)
        if (dateMax.isValid()) _range.max = dateMax.format(dateFormat)
    }
    return _range
})

const dateText = computed(() => {
    const { min, max } = dateRange.value
    if (!min && !max) return 'no date range specified (get all available data)'
    let text = ''
    text += `from ${min ? min : '----'}`
    text += ` to ${max ? max : '----'}`
    return text
})

const node = computed(() => {
    const fields = revision.value?.data?.fields
    if (!fields) return
    if (Object.keys(metadata.value).length === 0) return
    return Container.fromList(fields, metadata.value)
})

function categoryHasDateRange(category) {
    const { min, max } = dateRange.value
    if (!min && !max) return false // no date
    const selectedRevision = revision.value
    const date_range_categories = selectedRevision?.data?.date_range_categories ?? []
    if (!Array.isArray(date_range_categories)) return false
    return date_range_categories.includes(category)
}
</script>

<style scoped>
/* .revision-fields {
    max-height: 500px;
    overflow-y: auto;
} */
</style>
