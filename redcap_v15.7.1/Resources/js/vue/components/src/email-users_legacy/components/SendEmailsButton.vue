<template>
    <div>
        <div class="text-nowrap">
            <button
                type="button"
                class="btn btn-primary btn-sm"
                :disabled="sendDisabled"
                @click="onSendEmailsClicked"
            >
                <i v-if="sending" class="fas fa-spinner fa-spin fa-fw"></i>
                <i v-else class="fas fa-envelope fa-fw"></i>
                <span class="ms-2"><tt-text tkey="email_users_158" /></span>
            </button>
        </div>

        <Modal ref="confirmationModal" :ok-text="tt('email_users_158')">
            <template #header>
                <span class="font-weight-bold"
                    ><tt-text tkey="email_users_131"
                /></span>
            </template>
            <div class="confirmation-data">
                <span class="data-label"
                    ><tt-text tkey="email_users_108" /></span
                ><span class="data-value">{{ formData.from }}</span>
                <span class="data-label"
                    ><tt-text tkey="email_users_109" /></span
                ><span class="data-value"
                    >{{ formData.ui_ids.length }}
                    {{ formData.ui_ids.length == 1 ? 'user' : 'users' }}</span
                >
                <span class="data-label"><tt-text tkey="email_users_10" /></span
                ><span class="data-value">{{ formData.subject }}</span>
                <span class="data-label"
                    ><tt-text tkey="email_users_114"
                /></span>
                <div class="card">
                    <span
                        class="card-body p-1"
                        v-html="formData.message"
                    ></span>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import API from '../API'
import { useUsersStore, useFormStore, useSettingsStore } from '../store'
import Modal from '../../shared/Modal/Modal.vue'
import ModalManager from '../../shared/Modal/ModalManager'

import { GROUPS } from '../models/UsersManager'

const usersStore = useUsersStore()
const formStore = useFormStore()
const settingsStore = useSettingsStore()
const tt = (key) => settingsStore.translate(key)

const confirmationModal = ref()

const formData = computed(() => {
    const from = formStore.from
    const subject = formStore.subject
    const message = formStore.message
    const ui_ids = [...usersStore.selectedUsers]
    return { from, subject, message, ui_ids }
})

const sending = computed({
    get() {
        return formStore.sending
    },
    set(value) {
        formStore.sending = value
    },
})

const errors = computed(() => {
    const errors = []
    const {
        from = '',
        subject = '',
        message = '',
        ui_ids = [],
    } = formData.value
    if (from.trim() === '') errors.push(`a 'from' email must be selected`)
    if (subject.trim() === '') errors.push(`subject cannot be empty`)
    if (message.trim() === '') errors.push(`message cannot be empty`)
    if (ui_ids.length == 0) errors.push(`you must select at least 1 recipient`)
    return errors
})

const sendDisabled = computed(() => errors.value.length > 0 || sending.value)

async function onSendEmailsClicked() {
    const response = await confirmationModal.value.show()
    if (response !== true) return
    sendEmails()
}

async function sendEmails() {
    try {
        sending.value = true
        const data = { ...formData.value }
        await API.scheduleEmails(data)
        const successTitle = tt('email_users_127') || 'Success'
        const successBody =
            tt('email_users_128') ||
            'Emails scheduled. You can leave this page.'
        const options = { title: successTitle, body: successBody }
        const response = await ModalManager.alert(options)
        resetForm()
    } catch (error) {
        const errorTitle = tt('email_users_126') || 'Error'
        const errorMessage =
            error?.response?.data?.message ||
            'There was an error scheduling your emails.'
        ModalManager.alert({ title: errorTitle, body: errorMessage })
    } finally {
        sending.value = false
    }
}

async function resetForm() {
    formStore.subject = ''
    formStore.message = ''
    // make sure the editor is reset too
    window?.tinymce?.activeEditor?.setContent('')
    await usersStore.doAction('deselectGroups', [Object.values(GROUPS)])
    await usersStore.doAction('selectAll', [false])
}
</script>

<style scoped>
.confirmation-data {
    display: grid;
    gap: 5px 10px;
    grid-template-columns: min-content 1fr;
}
.data-label {
    font-weight: bold;
}
</style>
