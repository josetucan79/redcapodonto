<template>
    <table class="table table-sm table-striped table-hover table-bordered">
        <thead>
            <tr>
                <th>Username</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>
            <template v-if="users.length===0">
                <tr>
                    <td colspan="4"><span class="fst-italic">No Users</span></td>
                </tr>
            </template>
            <template v-for="(user, index) in users" :key="`${index}-${user?.ui_id}`">
                <tr :class="{suspended: user.is_suspended}">
                    <td>
                        <div class="d-flex gap-2 align-items-center">
                            <span>{{user?.username}}</span>
                            <!-- <i v-if="user.is_suspended" class="fas fa-ban fa-fw text-danger"></i> -->
                            <span v-if="user.is_suspended">
                                <span>(suspended)</span>
                            </span>
                        </div>
                    </td>
                    <td>{{user?.user_firstname}}</td>
                    <td>{{user?.user_lastname}}</td>
                    <td>
                        <div class="d-flex">
                            <span>{{user?.user_email}}</span>
                            <button type="text" class="btn btn-sm btn-light ms-auto"
                                @click="showPreview(user?.user_email)" :disabled="!previewAvailable">
                                <i class="fas fa-file-lines fa-fw text-info"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
        <Teleport to="body">
            <b-modal ref="preview-modal" ok-only size="xl">
                <template #title>
                    <span v-tt:email_users_157>Message Preview</span>
                </template>
                <div>
                    <span class="fw-bold" v-tt:email_users_108></span>:
                    <span>{{ fromName ? `${fromName} <${from}>` : from }}</span>
                </div>
                <div>
                    <span class="fw-bold" v-tt:email_users_109>To</span>:
                    <span>{{ to }}</span>
                </div>
                <div>
                    <span class="fw-bold" v-tt:email_users_10>Subject</span>:
                    <span v-html="previewSubject"></span>
                </div>
                <hr>
                <div>
                    <div v-html="previewBody"></div>
                </div>
            </b-modal>
        </Teleport>
    </table>
</template>

<script setup>
import { computed, ref, toRefs, useTemplateRef } from 'vue';
import { previewMessage } from '../../API'
import { useEmailStore } from '../../store'

const props = defineProps({
    users: { type: Array, default: () => [] },
})

const emailStore = useEmailStore()
const { subject, body, from, fromName, previewAvailable } = toRefs(emailStore)
const previewModal = useTemplateRef('preview-modal')

const to = ref()
const previewSubject = ref('')
const previewBody = ref('')

const resetPreviewData = () => {
    to.value = ''
    previewSubject.value = ''
    previewBody.value = ''
}

async function showPreview(user_email) {
    try {
        to.value = user_email
        const response = await previewMessage(subject.value, body.value, user_email)
        previewBody.value = response?.data?.body ?? ''
        previewSubject.value = response?.data?.subject ?? ''
        await previewModal.value.show()
        // reset
        resetPreviewData()
    } catch (error) {
        console.log(error)
    } 
}

</script>

<style scoped>
.suspended td {
    --bs-text-opacity: 1;
    color: var(--bs-secondary-color) !important;
    font-size: .95rem;
    font-style: italic;
}
</style>