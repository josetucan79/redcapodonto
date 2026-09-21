<template>
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <span class="fs-5">Source list</span>
            <div class="ms-auto">
                <input class="form-control" type="search" v-model="query" placeholder="type to search..."/>
            </div>
        </div>
        <div class="card-body">
            <div class="border rounded p-2">
                <span class="fw-bold d-block mb-2">If pulling time-based data, select the range of time from which to pull data (optional)</span>
                <DateRange v-model:min="dateMin" v-model:max="dateMax" />
            </div>
            <div class="border rounded p-2 mt-2">
                <MetadataSelectionNode :node="node" :date-range="dateRange" />
            </div>
        </div>

        <div class="card-footer">
            <div class="d-flex gap-2 justify-content-end">
                <slot name="buttons"></slot>
            </div>
        </div>
    </div>
</template>

<script setup>
import MetadataSelectionNode from '../components/MetadataSelectionNode.vue'
import DateRange from '../components/DateRange.vue'
import { useRevisionEditorStore } from '../store'
import { DATE_FORMAT } from '../store/revisionEditor'
import { computed, toRefs, watchEffect } from 'vue'
import { debounce } from '../../utils'
import moment from 'moment'

const revisionEditorStore = useRevisionEditorStore()

const props = defineProps({
    revision: { type: Object, default: null },
})

const { revision } = toRefs(props)
const dateMin = computed({
    get: () => revisionEditorStore?.dateMin || '',
    set: (value) => (revisionEditorStore.dateMin = value),
})
const dateMax = computed({
    get: () => revisionEditorStore?.dateMax || '',
    set: (value) => (revisionEditorStore.dateMax = value),
})
const node = computed(() => revisionEditorStore?.node)

const debounceFilter = debounce((value) => {
    node.value.filter(value)
}, 300)

const dateRange = computed(() => {
    const range = {}
    const _min = moment(dateMin.value)
    const _max = moment(dateMax.value)
    if (_min.isValid()) range.min = _min.format(DATE_FORMAT)
    if (_max.isValid()) range.max = _max.format(DATE_FORMAT)
    return range
})

const query = computed({
    get: () => node.value.query,
    set: (value) => debounceFilter(value),
})

watchEffect(() => {
    revisionEditorStore.setRevision(revision.value)
})
</script>

<style scoped></style>
