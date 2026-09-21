<template>
    <dialog ref="dialogRef" data-dialog @close="onClosed" @click="onClicked">
        <div class="header">
            <div class="d-flex">
                <slot name="header"></slot>
                <button class="btn btn-sm btn-light ms-auto" @click="close(false)">×</button>
            </div>
        </div>
        <div class="body">
            <slot></slot>
        </div>
            <div class="footer">
            <slot name="footer">
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-sm btn-secondary" @click="close(false)">Cancel</button>
                    <button class="btn btn-sm btn-primary" @click="close(true)">OK</button>
                </div>
            </slot>
        </div>
    </dialog>
</template>

<script setup>
import { ref } from 'vue'

class DialogManager {
    static list = new Set()
}
const dialogRef = ref()

let returnValue = false
let dialogResolve,
    dialogReject = null

async function open() {
    returnValue = false
    const promise = new Promise((resolve, reject) => {
        dialogResolve = resolve
        dialogReject = reject
    })
    dialogRef.value.showModal()
    document.body.style.overflow = 'hidden'
    DialogManager.list.add(dialogRef.value)
    return promise
}

function close(value) {
    returnValue = value
    console.log(returnValue)
    dialogRef.value.close()
}

function onClosed(event) {
    DialogManager.list.delete(dialogRef.value)
    if (DialogManager.list.size === 0) document.body.style.overflow = ''
    console.log(returnValue)
    if (dialogResolve) dialogResolve(returnValue)
}

/**
 * detect if the click is inside or outside of the dialog
 * @param {Object} event 
 */
function onClicked(event) {
    const rect = dialogRef.value.getBoundingClientRect()
    const isInDialog =
        rect.top <= event.clientY &&
        event.clientY <= rect.top + rect.height &&
        rect.left <= event.clientX &&
        event.clientX <= rect.left + rect.width
    console.log('isInDialog', isInDialog)
    if (!isInDialog) close(false)
}

defineExpose({ open, close })
</script>

<style scoped>
[data-dialog] {
    --dialog-border-width: 1px;
    --dialog-border-style: solid;
    --dialog-border-color: #dee2e6;
    --dialog-border-readius: 0.5rem;
    --dialog-spacing: 1rem;
    --dialog-min-width: 300px;
    --dialog-max-width: 5300px;
}
[data-dialog] {
    border: var(--dialog-border-width) var(--dialog-border-style) var(--dialog-border-color);
    border-radius: var(--dialog-border-readius);
    padding: 0;
    min-width: var(--dialog-min-width);
    max-width: var(--dialog-max-width);
    margin-top: 1rem;
    box-sizing: border-box;
}
[data-dialog] > .header {
    border-bottom: solid 1px var(--dialog-border-color);
}
[data-dialog] > .footer {
    border-top: solid 1px var(--dialog-border-color);
}
[data-dialog] > .header,
[data-dialog] > .body,
[data-dialog] > .footer {
    padding: var(--dialog-spacing);
}
[data-dialog]::backdrop {
    background-color: rgba(0 0 0 / 0.3);
}
</style>
