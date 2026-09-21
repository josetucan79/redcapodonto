<template>

    <template v-for="(warning, index) in warnings" :key="index">
        <div class="alert alert-warning mt-2">
            <span class="d-block fw-bold">
                <span>{{ warning.title }}</span>
            </span>
            <span>{{ warning.description }}</span>
        </div>
    </template>
</template>

<script>
class Warning {
    title = ''
    description = ''

    constructor(title, description) {
        this.title = title
        this.description = description
    }
}
</script>

<script setup>
import { computed, toRefs } from 'vue'
import { useRevisionsStore, useUserStore } from '../store'

const revisionsStore = useRevisionsStore()
const userStore = useUserStore()

const props = defineProps({
    revision: { type: Object },
})
const { revision } = toRefs(props)
const totalMRNs = computed(
    () => revision.value?.metadata?.total_fetchable_mrns ?? 0
)
const activeResivion = computed(() => revisionsStore.active)

/* can_create_revision
can_repeat_revision
can_use_datamart
has_valid_access_token
super_user */

const warnings = computed(() => {
    const selectedRevision = revision.value
    const warnings = []
    if (selectedRevision?.metadata?.approved !== true) {
        let warning = new Warning(
            'This revision was not approved',
            'An administrator must approve this revision before it can be run.'
        )
        warnings.push(warning)
    }
    if (
        selectedRevision === activeResivion.value &&
        !userStore?.can_repeat_revision &&
        selectedRevision?.metadata.executed
    ) {
        let warning = new Warning(
            'This revision was already run',
            'You are allowed to run a revision only once.'
        )
        warnings.push(warning)
    }
    if (selectedRevision !== activeResivion.value) {
        let warning = new Warning(
            'This is not the active revision',
            'Only the most recent revision can be run.'
        )
        warnings.push(warning)
    }
    if (totalMRNs.value < 1) {
        let warning = new Warning(
            'No fetchable records in the project',
            'You can only run a revision if you have at least 1 fetchable record in your project.'
        )
        warnings.push(warning)
    }
    return warnings
})
</script>

<style scoped></style>
