<template>
    <div class="p-2">
        <div class="d-flex gap-2">
            <div>
                <LoadMessagesButton />
            </div>
            <b-pagination
            v-model="page"
            :totalItems="total"
            :perPage="perPage"
            size="sm"
            class="mb-2"
            ></b-pagination>
        </div>
        <MessagesTable />
    </div>
</template>

<script setup>
import { toRefs, watch } from 'vue';
import { useMessagesStore } from '../store'
import MessagesTable from '../components/messages/MessagesTable.vue';
import LoadMessagesButton from '../components/messages/LoadMessagesButton.vue';

const messagesStore = useMessagesStore()
const {page, perPage, total} = toRefs(messagesStore)
const {load} = messagesStore

watch(page, () => {
    // rerun the test when the page is changed
    load()
})
</script>

<style scoped>

</style>