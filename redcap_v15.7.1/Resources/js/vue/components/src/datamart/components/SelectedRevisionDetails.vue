<template>
    <RevisionDetails :revision="revision">
        <template #body-start>
            <RevisionWarning :revision="revision" />
        </template>
        <template #footer>
            <template v-if="revision && userStore.isSuperUser">
                <DeleteRevisionButton :revision="revision" />
            </template>
            <template
                v-if="
                    revisionsStore.isApproved(revision) &&
                    (userStore.isSuperUser || userStore.can_create_revision)
                "
            >
                <RequestChangeButton :revision="revision" />
            </template>
            <template
                v-else-if="
                    revision &&
                    !revisionsStore.isApproved(revision) &&
                    userStore.isSuperUser
                "
            >
                <ApproveRevisionButton :revision="revision" />
            </template>
        </template>
    </RevisionDetails>
</template>

<script setup>
import { computed } from 'vue'
import { useRevisionsStore, useUserStore } from '../store'
import RevisionWarning from './RevisionWarning.vue'
import RequestChangeButton from './buttons/RequestChangeButton.vue'
import DeleteRevisionButton from './buttons/DeleteRevisionButton.vue'
import ApproveRevisionButton from './buttons/ApproveRevisionButton.vue'
import RevisionDetails from './RevisionDetails.vue'

const revisionsStore = useRevisionsStore()
const userStore = useUserStore()

const revision = computed(() => revisionsStore?.selected)
</script>

<style scoped>
/* .revision-fields {
    max-height: 500px;
    overflow-y: auto;
} */
</style>
