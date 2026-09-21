<template>
    <button
        type="button"
        class="btn btn-sm btn-primary"
        :disabled="!canSend"
        @click="onSendClicked"
    >
        <template v-if="loading">
            <i class="fas fa-spin fa-spinner fa-fw"></i>
        </template>
        <template v-else>
            <i class="fas fa-envelope fa-fw"></i>
        </template>
        <span class="ms-1" v-tt:email_users_158>Send</span>
    </button>
    <Teleport to="body">
        <b-modal ref="confirmation-modal">
            <template #title>
                <span v-tt:email_users_131>Please aaconfirm before sending</span>
            </template>
            <div class="d-flex flex-column gap-2 confirmation-data">
                <div>
                    <label for="from" v-tt:email_users_108>From</label>
                    <input class="form-control form-control-sm" type="text" :value="from" readonly />
                </div>
                <div v-if="fromName?.trim()">
                    <label for="fromName" v-tt:email_users_152>From Name</label>
                    <input class="form-control form-control-sm" type="text" :value="fromName" readonly />
                </div>
                <div>
                    <label for="subject" v-tt:email_users_10>Subject</label>
                    <input class="form-control form-control-sm" type="text" :value="subject" readonly />
                </div>
                <div>
                    <label for="body" v-tt:email_users_114>Message</label>
                    <textarea class="form-control form-control-sm" readonly>{{ body }}</textarea>
                </div>
            </div>
        </b-modal>
    </Teleport>
</template>

<script setup>
import { computed, ref, toRefs, useTemplateRef } from 'vue';
import { useToaster } from 'bootstrap-vue';
import { useQueriesStore, useEmailStore, useMessagesStore } from '../../store';

const toaster = useToaster()
const queriesStore = useQueriesStore()
const emailStore = useEmailStore()
const messagesStore = useMessagesStore()

const loading = ref(false)
const confirmationModal = useTemplateRef('confirmation-modal')
const { selected: selectedQuery } = toRefs(queriesStore)
const { from, subject, body, fromName } = toRefs(emailStore)

// make sure the filterTags function is defined
const filter_tags = (text) => {
    if(window.filter_tags) return window.filter_tags(text)
    return text
}

const canSend = computed(() => {
    const _subject = filter_tags(subject.value?.trim() ?? null)
    const _body = filter_tags(body.value?.trim() ?? null)
    const _query = selectedQuery.value ?? null
    return _subject && _body // && _query
})

const send = async () => {
    try {
        loading.value = true
        const query = selectedQuery.value?.query
        await emailStore.send(query)
        toaster.toast({title: 'Success', body: 'Scheduled'})
    } finally {
        loading.value = false
    }
}

const onSendClicked = async () => {
    const confirmed = await confirmationModal.value.show()
    if(!confirmed) return

    await send()
    // reload sent messages
    await messagesStore.load()
}
</script>

<style scoped>
.confirmation-data label {
    font-weight: bold;
}
/* .confirmation-data {
    display: grid;
    grid-template-columns: min-content 1fr;
    gap: 10px;
    align-items: start;
} */
</style>