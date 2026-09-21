<template>
    <div v-bind="$attrs">
        <div v-if="creator">
            <span class="fw-bold"> Created by: </span>
            <a :href="`mailto:${creator.user_email}`">
                {{ `${creator.user_firstname} ${creator.user_lastname}` }}
            </a>
        </div>
        <div>
            <span class="fw-bold">Creation date: </span>
            <span> {{ creationTime }}</span>
        </div>
        <div>
            <span class="fw-bold">Last executed: </span>
            <template v-if="revisionWasExecuted && revisionExecutedAt">
                <span> {{ revisionExecutedAt }} </span>
            </template>
            <template v-else> never</template>
        </div>
    </div>
</template>

<script setup>
import { computed, toRefs } from 'vue'
import moment from 'moment'

const props = defineProps({
    revision: { type: Object },
})
const { revision } = toRefs(props)
const creator = computed(() => revision.value?.metadata?.creator)

const revisionWasExecuted = computed(() =>
    Boolean(revision.value?.metadata?.executed)
)
const revisionExecutedAt = computed(() => {
    const executionDate = moment(revision.value?.metadata?.executed_at)
    if (executionDate.isValid) return executionDate.format('YYYY-MM-DD hh:mm:ss')
    else return false
})


const creationTime = computed(() => {
    const date = revision.value?.metadata?.date
    if (!date) return ''
    return moment(date).fromNow()
})
</script>

<style scoped></style>
