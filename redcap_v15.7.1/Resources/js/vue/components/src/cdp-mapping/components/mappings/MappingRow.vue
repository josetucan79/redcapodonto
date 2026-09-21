<template>
    <tr
        ref="rowRef"
        class="mapping-row"
        :class="{ dirty: isDirty, deleted: isDeleted }"
    >
        <td>
            <div class="status-indicator d-flex gap-2">
                <template v-if="isDeleted">
                    <div v-mytooltip:left="'deleted entry'">
                        <i class="fas fa-trash fa-fw"></i>
                    </div>
                </template>
                <template v-else>
                    <template v-if="isDuplicate">
                        <div v-mytooltip:left="'this is a duplicate entry'">
                            <i class="fas fa-copy fa-fw text-danger"></i>
                        </div>
                    </template>
                    <template v-if="isDirty">
                        <div v-mytooltip:left="'modified entry'">
                            <i class="fas fa-edit fa-fw"></i>
                        </div>
                    </template>
                    <template v-if="isNew">
                        <div v-mytooltip:left="'new entry'">
                            <i class="fas fa-circle-plus fa-fw"></i>
                        </div>
                    </template>
                </template>
            </div>
            <FhirField />
        </td>
        <!-- <td><EventDropdown /></td> -->
        <td>
            <div class="d-flex flex-column gap-2">
                <EventDropdown />
                <REDCapField />
            </div>
        </td>
        <td><TemporalField :disabled="!isTemporal" v-show="isTemporal" /></td>
        <td>
            <PreselectStrategyDropdown
                :disabled="!isTemporal"
                v-show="isTemporal"
            />
        </td>
        <td style="vertical-align: middle">
            <div class="p-2">
                <div class="action-buttons d-flex gap-2">
                    <template v-if="isDeleted">
                        <button
                            v-mytooltip:left="`restore entry`"
                            type="button"
                            class="btn btn-outline-success btn-xs"
                            @click="onRestoreClicked"
                        >
                            <i class="fas fa-refresh fa-fw"></i>
                        </button>
                    </template>
                    <template v-else>
                        <button
                            v-mytooltip:left="`remove entry`"
                            type="button"
                            class="btn btn-outline-danger btn-xs"
                            @click="onRemoveClicked"
                        >
                            <i class="fas fa-trash fa-fw"></i>
                        </button>
                        <button
                            v-mytooltip:left="`duplicate entry`"
                            type="button"
                            class="btn btn-outline-secondary btn-xs"
                            @click="onCopyClicked"
                        >
                            <i class="fas fa-copy fa-fw"></i>
                        </button>
                    </template>
                </div>
            </div>
        </td>
    </tr>
</template>

<script setup>
import { inject, onMounted, provide, ref, toRefs } from 'vue'
import FhirField from './FhirField.vue'
import TemporalField from './TemporalField.vue'
import EventDropdown from './EventDropdown.vue'
import REDCapField from './REDCapField.vue'
import PreselectStrategyDropdown from './PreselectStrategyDropdown.vue'
import { isElementInView } from '@/utils'

const props = defineProps({
    store: { type: Object, default: () => ({}) },
    index: { type: Number },
})

const rowRef = ref()
const { store, index } = toRefs(props)

const mappingService = inject('mapping-service')

// dynamically create the local approval service
provide('local-mapping-service', store)

const { isNew, isDirty, isDeleted, isDuplicate, isTemporal, field } = toRefs(
    store.value
)

function onRemoveClicked() {
    mappingService.removeEntry(field.value)
}
function onRestoreClicked() {
    mappingService.restoreEntry(field.value)
}
function onCopyClicked() {
    mappingService.duplicateEntry(field.value, index.value)
}

// scroll to new element if not in view already
const scrollIfNew = () => {
    if (isElementInView(rowRef.value)) return
    const shouldScroll = isNew.value
    if (!shouldScroll) return
    const delta = 40 // account for the sticky buttons
    const elementPosition = rowRef.value.getBoundingClientRect().top
    const offsetPosition = window.scrollY + elementPosition - delta
    window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth',
    })

    /* rowRef.value.scrollIntoView({
        behavior: 'smooth',
        block: 'start', // Options are 'start', 'center', 'end', 'nearest'
    }) */
}

onMounted(() => scrollIfNew())
</script>

<style scoped>
.mapping-row {
    position: relative;
    .status-indicator {
        position: absolute;
        right: 0;
        top: 0;
        z-index: 1;
    }
}
.mapping-row td {
    max-width: 300px;
}
.mapping-row :deep(.dropdown > *),
.mapping-row :deep(.dropdown > * button) {
    /* width: 100%; */
    text-align: left;
    word-break: break-all;
    white-space: break-spaces;
}
.mapping-row td:has(.action-buttons) {
    position: relative;
}
.mapping-row .action-buttons {
    transition: opacity 300ms ease-in-out;
}
.mapping-row:not(:hover) .action-buttons {
    opacity: 0;
}
.mapping-row:not(:hover) :deep(.dropdown button) {
    border-color: transparent;
}
.mapping-row:not(:hover) :deep(.dropdown button::after) {
    visibility: hidden;
}
</style>
