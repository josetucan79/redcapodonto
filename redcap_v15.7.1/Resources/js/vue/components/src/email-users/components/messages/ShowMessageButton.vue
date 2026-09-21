<template>
    <button v-if="messages.length>0" class="btn btn-sm btn-outline-secondary" @click="onShowMessages">
        <i class="fas fa-clock-rotate-left fa-fw"></i>
        <span>Messages</span>
    </button>
</template>

<script setup>
import { toRefs } from 'vue'
import { useMessagesStore } from '../../store'
import useRouter from '../../router'

const router = useRouter()
const messagesStore = useMessagesStore()
const {list: messages} = toRefs(messagesStore)

function onShowMessages() {
    router.push({name: 'messages'})
}
</script>

<style lang="scss" scoped></style>
