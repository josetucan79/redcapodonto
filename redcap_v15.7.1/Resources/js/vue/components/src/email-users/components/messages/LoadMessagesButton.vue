<template>
    <button v-if="messages.length>0" class="btn btn-sm btn-primary" @click="load">
        <template v-if="loading">
            <i class="fas fa-spinner fa-spin fa-fw"></i>
        </template>
        <template v-else>
            <i class="fas fa-refresh fa-fw"></i>
        </template>
    </button>
</template>

<script setup>
import { toRefs } from 'vue'
import { useMessagesStore } from '../../store'


const messagesStore = useMessagesStore()

const {list: messages, loading} = toRefs(messagesStore)
const { load } = messagesStore
</script>

<style scoped></style>
