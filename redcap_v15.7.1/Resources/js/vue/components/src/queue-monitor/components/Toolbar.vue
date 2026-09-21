<template>
    <b-dropdown variant="outline-secondary" size="sm">
        <template #button="{ show }">
            <i class="fas fa-cog fa-fw" :class="{ 'fa-rotate-90': show }"></i>
            <span class="ms-2">actions</span>
        </template>
        <b-dropdown-item>
            <button
                class="d-flex align-items-center justify-content-between"
                :disabled="modifyDisabled"
                @click="onModifyClicked"
            >
                <i class="fas fa-pencil fa-fw"></i>
                <span class="ms-2">edit priority</span>
            </button>
        </b-dropdown-item>
        <b-dropdown-item>
            <button
                class=""
                :disabled="deleteDisabled"
                @click="onDeleteClicked"
            >
                <i class="fas fa-trash fa-fw"></i>
                <span class="ms-2">delete</span>
            </button>
        </b-dropdown-item>
    </b-dropdown>

    <b-modal ref="priorityModal">
        <template #header> Modify priority </template>
        <template v-slot="{ hide }">
            <p class="alert alert-info">
                Please note that priority will only be updated for messages with
                a 'waiting' stutus.
            </p>
            <input
                class="form-control form-control-sm"
                v-model="priorityValue"
                type="number"
                @keyup.enter="hide(true)"
            />
        </template>
    </b-modal>
</template>

<script setup>
import { ref, toRefs, computed } from 'vue'
import { clamp } from '../../utils'
import store from '../store'

import ModalManager from '../../shared/Modal/ModalManager'
import STATUS from '../models/Status'

const emit = defineEmits(['onDelete'])
const props = defineProps({
    list: { type: Array, default: () => [] },
})

const priorityValue = ref('')
const priorityModal = ref(null)

const { list: messages } = toRefs(props)

const modifyDisabled = computed(() => {
    const list = messages.value.filter(
        ({ status }) => status === STATUS.WAITING
    )
    return list.length == 0
})
async function onModifyClicked() {
    const totalMessages = messages.value.length
    priorityValue.value =
        totalMessages === 1 ? messages.value?.[0]?.priority : ''
    const confirmed = await priorityModal.value.show()
    messages.value.forEach((message) => {
        updatePriority(message)
    })
}
/**
 * Update priority for a message in the WAITING status
 *
 * @param {Object} message
 */
async function updatePriority(message) {
    if (message.status !== STATUS.WAITING) return
    if (!Number.isInteger(priorityValue.value)) return
    const currentPriority = message.priority
    let newPriority = clamp(priorityValue.value, 1, 100)
    if (isNaN(newPriority)) return
    message.priority = newPriority // optimistic update
    const success = await store.updatePriority(message.id, newPriority)
    if (!success) message.priority = currentPriority // revert if update fails
    return success
}

const deleteDisabled = computed(() => {
    const list = messages.value.filter(
        ({ status }) => status !== STATUS.PROCESSING
    )
    return list.length == 0
})

async function onDeleteClicked() {
    const cardinalityText =
        messages.value.length === 1 ? 'this item' : 'these items'
    const confirmed = await ModalManager.confirm({
        title: 'Are you sure?',
        body: `Do you really want to delete ${cardinalityText}? This action cannot be undone.`,
    })
    if (!confirmed) return

    const list = messages.value.filter(
        ({ status }) => status !== STATUS.PROCESSING
    )
    const promises = []
    list.forEach(async (message) => {
        if (message.status === STATUS.PROCESSING) return
        const promise = store.deleteMessage(message.id)
        promises.push(promise)
    })
    await Promise.all(promises)
    emit('onDelete')
}
</script>

<style scoped>
.fas.fa-cog {
    transition: transform 0.3s;
}

button {
    border: 0;
    display: inline-block;
    background-color: transparent;
}
button:focus,
button:hover {
    outline: 0;
}
</style>
