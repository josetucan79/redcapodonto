<template>
    <b-dropdown variant="outline-secondary" size="sm" class="events-dropdown">
        <template #button>
            <div class="button-label">
                <span v-if="currentEvent.name_ext">{{
                    `${currentEvent.name_ext}`
                }}</span>
                <span v-else>
                    <NoSelection />
                </span>
            </div>
        </template>
        <div class="results">
            <template v-for="(arm, arm_num) in arms" :key="`arm-${arm.id}`">
                <div class="p-2 bg-light" data-prevent-close>
                    Arm {{ arm_num }}: {{ arm.name }}
                </div>
                <b-dropdown-item :active="!event_id" @click="onEventSelected(null)">
                    <span>-- none --</span>
                </b-dropdown-item>
                <template
                    v-for="(event, _event_id) in arm.events"
                    :key="`event-${_event_id}`"
                >
                    <b-dropdown-item
                        class="d-block"
                        @click="onEventSelected(_event_id)"
                        :active="_event_id === event_id"
                    >
                        {{ getEvent(_event_id)?.name_ext }}
                    </b-dropdown-item>
                </template>
                <b-dropdown-divider></b-dropdown-divider>
            </template>
        </div>
    </b-dropdown>
</template>

<script setup>
import { computed, inject, ref, toRefs } from 'vue'
import NoSelection from './NoSelection.vue'

const appStore = inject('app-store')
const { events, arms } = toRefs(appStore.settings.project)
const localMappingService = inject('local-mapping-service')
const { event_id } = toRefs(localMappingService.value.field)

const getEvent = (_event_id) => {
    return events.value?.[_event_id] ?? {}
}

const currentEvent = computed(() => {
    return getEvent(event_id.value)
})

function onEventSelected(_event_id) {
    event_id.value = _event_id
}
</script>

<style scoped>
.events-dropdown {
    --size: 250px;
}
.results {
    max-height: var(--size);
    overflow-y: auto;
}
.button-label,
.results li {
    display: inline-block;
    width: var(--size);
    white-space: nowrap;
}
.button-label > *,
.results li :deep(.dropdown-item) {
    text-overflow: ellipsis;
    overflow: hidden;
}
</style>
