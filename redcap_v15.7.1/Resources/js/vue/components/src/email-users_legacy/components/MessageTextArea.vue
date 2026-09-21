<template>
    <div>
        <textarea
            ref="textAreaRef"
            name="emailMessage"
            class="x-form-textarea x-form-field vue-mceditor w-100"
            style="height:250px;"
            @input="onMessageChanged"
            v-model="emailMessage"
            @blur="onTextAreaBlur"
        ></textarea>
        <div class="border p-2">
            <DynamicVariables @variable-selected="onVariableSelected" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive, onUnmounted } from 'vue'
import { useFormStore } from '../store'
import DynamicVariables from './DynamicVariables.vue'

const formStore = useFormStore()

const textAreaRef = ref()

/**
 * keep tarck of the last position of the cursor in the textarea
 * (this only works in test. in prod will be used tinymce)
 */
const lastCursorPosition = ref(0)
function onTextAreaBlur() {
    lastCursorPosition.value = textAreaRef.value?.selectionStart ?? 0
}

const emailMessage = computed({
    get() {
        return formStore.message
    },
    set(value) {
        formStore.message = value
    },
})

function onMessageChanged(event) {
    const html = event?.target?.value
    if (html == null) throw new Error('Error getting the message value')
    emailMessage.value = html
}

function insertTextAtPosition(originalString, textToInsert, position) {
    if (position < 0) {
        // If position is less than 0, append at the beginning
        return textToInsert + originalString
    } else if (position > originalString.length) {
        // If position is out of bounds, append at the end
        return originalString + textToInsert
    } else {
        // Insert text at the specified position
        return (
            originalString.slice(0, position) +
            textToInsert +
            originalString.slice(position)
        )
    }
}

/**
 * insert a dynamic variable in the last position of the cursor
 * @param {*} variable
 */
function onVariableSelected(variable) {
    const normalizedVariable = `[${variable}]`
    const editor = getTinyMceEditor()
    if (editor) {
        // this is in prod
        editor?.insertContent(normalizedVariable)
        const updatedMessage = editor.getContent()
        emailMessage.value = updatedMessage
    } else {
        // this is in dev
        const position = lastCursorPosition.value
        const message = emailMessage?.value ?? ''
        const updatedMessage = insertTextAtPosition(
            message,
            normalizedVariable,
            position
        )
        emailMessage.value = updatedMessage
    }
}

/**
 * get a reference to thenactive editor in the page
 */
const getTinyMceEditor = () => {
    const tinyMceEditor = window?.tinymce?.activeEditor
    return tinyMceEditor
}

let textAreaData = {}

onMounted(() => {
    if (typeof window.initTinyMCEglobal == 'function')
        window.initTinyMCEglobal('vue-mceditor', false)
})
onUnmounted(() => {
    textAreaData?.controller?.abort()
})
</script>

<style scoped>
textarea:invalid {
    box-shadow: 0 0 5px 1px rgba(255, 0, 0, 0.5);
}
</style>
