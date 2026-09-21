<template>
    <button
        type="button"
        class="btn btn-sm btn-outline-success"
        :disabled="loading"
        @click="onTestClicked"
        v-bind="{...$attrs}"
    >
        <template v-if="loading">
            <i class="fas fa-spinner fa-spin fa-fw"></i>
        </template>
        <template v-else>
            <i class="fas fa-vial fa-fw"></i>
        </template>
        <slot></slot>
        <Teleport to="body">
            <b-modal ref="users-modal" size="xl" ok-only>
                <template #title>
                    <span v-tt:email_users_144></span>
                </template>
                <div class="users-table">
                    <UsersTable :users="users"/>
                </div>

                <div v-if="!previewAvailable" class="alert alert-warning mt-2 m-0">
                    <span class="fw-bold">Please note:</span>
                    preview is only available when <span class="fst-italic">subject</span> or <span class="fst-italic">body</span> are defined
                </div>

                
                <template #footer="{hide}" class="justify-content-start">
                    <div class="my-modal-footer d-flex gap-2 align-items-end w-100">
                        <div>
                            <div class="text-muted small">
                                <span v-tt:email_users_145></span>
                                <span>: {{ metadata?.total ?? 0 }}</span>
                            </div>
                            <b-pagination
                            v-model="page"
                            :totalItems="totalUsers"
                            :perPage="perPage"
                            size="sm"
                            ></b-pagination>
                            
                        </div>
                        <div class="ms-auto d-flex gap-2">
                            <CsvDownloadButton :query="query" />
                            <button type="button" class="btn btn-sm btn-primary " @click="hide">OK</button>
                        </div>
                    </div>
                </template>
            </b-modal>
        </Teleport>
    </button>

</template>

<script setup>
import { computed, toRefs, useTemplateRef, watch } from 'vue';
import { useTestQueryStore, useEmailStore } from '../../store'
import UsersTable from './UsersTable.vue';
import CsvDownloadButton from './CsvDownloadButton.vue'

const testQueryStore = useTestQueryStore()
const emailStore = useEmailStore()
const usersModal = useTemplateRef('users-modal')

const props = defineProps({
    query: { type: Object }
})
const { query } = toRefs(props)
const { previewAvailable } = toRefs(emailStore)
const {loading, list: users, page, perPage, metadata, totalUsers} = toRefs(testQueryStore)

const test = async () => {
    return await testQueryStore.test(query.value)
}

const onTestClicked = async () => {
    testQueryStore.reset()
    const response = await test()
    if(!response) return // do nothing on error
    await usersModal.value.show()
}

watch(page, () => {
    // rerun the test when the page is changed
    test()
})
</script>

<style scoped>

:deep(:has(> .my-modal-footer)) {
    justify-content: start;
}
.users-table {
    max-height: 500px;
    overflow: auto;
}
</style>