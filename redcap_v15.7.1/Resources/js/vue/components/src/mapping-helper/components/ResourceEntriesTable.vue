<template>
    <div data-table-wrapper>
        <template v-if="rotate">
            <div class="rotated-entries border p-2">
                <template
                    v-for="(entry, index) in entries"
                    :key="`index-${index}`"
                >
                    <div class="rotated-entry border mb-2">
                        <template v-for="key in keys" :key="`key-${key}`">
                            <div class="value-wrapper border-bottom p-2">
                                <span class="fw-bold me-2" :data-key="key">{{ key }}:</span>
                                <TextMarker :query="searchStore.query">
                                    <ValueViewer :value="entry[key]" />
                                </TextMarker>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>
        <template v-else>
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th>
                            <span>Mapping status</span>
                        </th>
                        <template
                            v-for="(key, index) in keys"
                            :key="`key-${index}`"
                        >
                            <th>
                                <span>{{ key }}</span>
                            </th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <template
                        v-for="(entry, index) in entries"
                        :key="`index-${index}`"
                    >
                        <tr>
                            <td :data-status="status = getMappingStatus(index)">
                                <span class="badge round-pill bg-primary">
                                    {{ status?.status?.toUpperCase() }}
                                </span>
                                <small v-if="status?.reason" class="text-muted fst-italic"> {{ status.reason }}</small>
                            </td>
                            <template v-for="(key, cellKey) in keys" :key="`key-${cellKey}-${key}`">
                                <td>
                                    <template v-if="status?.mapped_fields?.length">
                                        <MappingStatus :mapped="status?.mapped_fields?.includes(key)" />
                                    </template>
                                    <TextMarker :query="searchStore.query">
                                        <ValueViewer :value="entry[key]" />
                                    </TextMarker>
                                </td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useSearchStore } from '../store'
import ValueViewer from './ValueViewer.vue'
import TextMarker from '../../shared/TextMarker.vue'
import MappingStatus from './MappingStatus.vue'

function getUniqueKeysFromArrayObjects(arrayOfObjects) {
    const uniqueKeys = new Set()

    arrayOfObjects.forEach((obj) => {
        Object.keys(obj).forEach((key) => {
            uniqueKeys.add(key)
        })
    })

    return Array.from(uniqueKeys)
}
const searchStore = useSearchStore()
const results = computed(() => {
    return searchStore.pagination.items
})

const rotate = computed(() => searchStore.rotate)
const entries = computed(() => results.value.map(({ data }) => data))
const mappingStatuses = computed(() =>
    results.value.map(({ mapping_status }) => mapping_status ?? [])
)

function getMappingStatus(index) {
    return mappingStatuses.value?.[index]
}

// table header
const keys = computed(() => getUniqueKeysFromArrayObjects(entries.value))
</script>

<style scoped>
[data-table-wrapper] {
    font-size: 0.8rem;
    max-height: 400px;
    overflow: auto;
}

.rotated-entry:nth-child(2n + 1) {
    background-color: rgb(0 0 0 / 0.05);
}
/* identify LOINC codes */
:deep(.value-wrapper:has([data-key='system'] + [data-value*='loinc']) [data-key='code'] + [data-value]) {
    font-weight: bold;
}
/* :deep(.value-wrapper:has([data-key='system'] + [data-value*='loinc']) [data-key='code'] + [data-value]) {
    color: white !important;
    background-color: rgb(2 123 255);
    padding: 3px;
    border: solid 1px rgb(0 0 0 / 15%);
    border-radius: 5px;
    font-weight: bold;
} */
</style>
