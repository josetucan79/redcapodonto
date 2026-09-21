<template>
    <table
        class="messages-table table table-sm table-hover table striped table-bordered"
    >
        <thead>
            <tr>
                <th>Subject</th>
                <th>Body (truncated)</th>
                <th>Sent By</th>
                <th>Sent At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <template v-if="messages.length===0">
                <tr>
                    <td colspan="5"><span class="fst-italic">No items</span></td>
                </tr>
            </template>
            <tr v-for="message in messages" :key="message.id">
                <td>{{ message.subject }}</td>
                <td>{{ truncatedBody(stripHtml(message.body)) }}</td>
                <td>{{ message.sent_by_username }}</td>
                <td>{{ message.created_at }}</td>
                <td>
                    <div class="d-flex gap-2 align-items-center">
                        <button class="btn btn-xs btn-light" @click="onUseClicked(message)">
                            <i class="fas fa-file-import fa-fw"></i> Re-use
                        </button>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<script setup>
import { computed, ref, toRefs } from 'vue'
import { useMessagesStore, useEmailStore } from '../../store'
import { useModal, useToaster } from 'bootstrap-vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const modal = useModal()
const toaster = useToaster()
const messagesStore = useMessagesStore()
const emailStore = useEmailStore()

const loading = ref(false)
const {list} = toRefs(messagesStore)
const emit = defineEmits(['use-message'])
const messages = computed(() => list.value || [])

const truncatedBody = (body) => {
    if (!body) return ''
    return body.length > 50 ? body.substring(0, 47) + '...' : body
}

// Removes HTML tags from the provided string.
const stripHtml = (html) => {
  if (!html) return '';
  return html.replace(/<[^>]*>/g, '');
};

const onUseClicked = (message) => {
    emailStore.subject = message.subject ?? ''
    emailStore.body = message.body ?? ''
    window?.tinymce?.activeEditor?.setContent(message.body)
    emit('use-message', message)
    toaster.toast({title: 'Success', body: 'Message loaded'})
    router.push({name: 'home'})
}


// const deleteMessage = async (message) => {
//   try {
//     loading.value = true
//     const id = message.id
//     if(id) {
//       await messagesStore.remove(id)
//       await messagesStore.load()
//     }
//   } finally {
//     loading.value = false
//   }
// }
//
// const onDeleteClicked = async (message) => {
//   const confirmed = await modal.confirm({title: 'Confirm', body: 'Are you sure you want to delete this item?'})
//   if(!confirmed) return
//   deleteMessage(message)
// }

</script>

<style scoped></style>
