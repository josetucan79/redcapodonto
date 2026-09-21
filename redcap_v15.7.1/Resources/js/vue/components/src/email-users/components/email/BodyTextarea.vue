<template>
    <textarea v-model="body" ref="body-textarea" class="vue-mceditor" rows="10"
    @input="onMessageChanged" @blur="onTextAreaBlur" v-bind="$attrs" required></textarea>
</template>

<script setup>
import { inject, useTemplateRef, onMounted, onUnmounted, ref, watch } from 'vue'
import {insertTextAtPosition} from '@/utils/insertText'

const eventBus = inject('eventBus')
const body = defineModel({type: String, default: ''})
const bodyTextArea = useTemplateRef('body-textarea')

const lastCursorPosition = ref(0)
function onTextAreaBlur() {
    lastCursorPosition.value = bodyTextArea.value?.selectionStart ?? 0
}
function onMessageChanged(event) {
    const html = event?.target?.value
    if (html == null) throw new Error('Error getting the message value')
    body.value = html
}

let textAreaData = {}


const getTinyMceEditor = () => {
    const tinyMceEditor = window?.tinymce?.activeEditor
    return tinyMceEditor
}

/**
 * insert a dynamic variable in the last position of the cursor
 * @param {*} variable
 */
function insertDynamicVariable(variable) {
    const normalizedVariable = `[${variable}]`
    const editor = getTinyMceEditor()
    if (editor) {
        // this is in prod
        editor?.insertContent(normalizedVariable)
        const updatedMessage = editor.getContent()
        body.value = updatedMessage
    } else {
        // this is in dev
        const position = lastCursorPosition.value
        const message = body?.value ?? ''
        const updatedMessage = insertTextAtPosition(
            message,
            normalizedVariable,
            position
        )
        body.value = updatedMessage
    }
}

onMounted(() => {
    try {
        if (typeof window.initTinyMCEglobal == 'function')
            window.initTinyMCEglobal('vue-mceditor', false)
    } catch (error) {
        console.log(error)
    }
    eventBus.emit('body-textarea-ready', {element: bodyTextArea.value})
})

defineExpose({ insertDynamicVariable })

</script>

<style scoped>
</style>