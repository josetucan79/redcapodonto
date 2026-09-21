<template>
    <div class="email-attributes mb-2">
        <div>
            <span class="label"><tt-text tkey="email_users_108" /></span>
        </div>
        <div>
            <DropDown dropdown-email variant="outline-secondary">
                <template #button>
                    <span>{{ selectedEmail }}</span>
                </template>
                <template v-for="(email, index) in emails" :key="index">
                    <DropDownItem
                        @click="onEmailSelected(email)"
                        :active="email == selectedEmail"
                        >{{ email }}</DropDownItem
                    >
                </template>
                <template v-if="emails.length < 3">
                    <DropDownDivider />
                    <DropDownItem @click="onAddAnotherEmailClicked"
                        ><tt-text tkey="email_users_132"
                    /></DropDownItem>
                </template>
            </DropDown>
        </div>

        <div>
            <span class="label"><tt-text tkey="email_users_109" /></span>
        </div>
        <input
            type="text"
            class="form-control"
            :placeholder="`[${settingsStore?.lang?.email_users_09}]`"
            disabled
        />

        <div>
            <span class="label"><tt-text tkey="email_users_10" /></span>
        </div>
        <input type="text" class="form-control" v-model="emailSubject" />
    </div>

    <div>
        <MessageTextArea />
    </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { DropDown, DropDownItem, DropDownDivider } from '../../shared/DropDown'
import { useFormStore, useSettingsStore } from '../store'
import MessageTextArea from './MessageTextArea.vue'

const formStore = useFormStore()
const settingsStore = useSettingsStore()

const emails = computed(() => {
    return settingsStore?.user?.emails || []
})
const selectedEmail = computed({
    get() {
        return formStore.from
    },
    set(value) {
        formStore.from = value
    },
})
const emailSubject = computed({
    get() {
        return formStore.subject
    },
    set(value) {
        formStore.subject = value
    },
})
const emailMessage = computed({
    get() {
        return formStore.message
    },
    set(value) {
        formStore.message = value
    },
})

watch(
    emails,
    (_emails) => {
        if (_emails.length == 0) return
        selectedEmail.value = _emails[0]
    },
    { immediate: true }
)

function onEmailSelected(email) {
    selectedEmail.value = email
}
function onAddAnotherEmailClicked() {
    if (typeof window.setUpAdditionalEmails == 'function')
        window.setUpAdditionalEmails()
}
</script>

<style scoped>
.email-attributes {
    display: grid;
    grid-template-columns: min-content 1fr;
    gap: 10px 10px;
    align-items: center;
}
input:invalid,
textarea:invalid {
    box-shadow: 0 0 5px 1px rgba(255, 0, 0, 0.5);
}
[dropdown-email] :deep([data-button] button) {
    width: 100%;
}
</style>
