<template>
    <div
        class="position-fixed top-0 start-50 translate-middle-x"
        style="z-index: 1030"
    >
        <div class="p-3 mb-2 d-flex justify-content-center">
            <!-- Your content goes here -->
            <b-toast :autohide="false" visible>
                <template #title>
                    <div class="me-auto">
                        <i class="fas fa-warning fa-fw me-2 text-warning"></i>
                        <span>Unsaved changes</span>
                    </div>
                </template>
                <span>Careful - you have unsaved changes!</span>
                <div class="pt-2 d-flex justify-content-start gap-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-secondary"
                        @click="onResetClicked"
                    >
                        <i class="fas fa-rotate-left fa-fw me-2"></i>
                        <span>Revert</span>
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        @click="onSaveClicked"
                    >
                        <i class="fas fa-save fa-fw me-2"></i>
                        <span>Save Changes</span>
                    </button>
                </div>
            </b-toast>
        </div>
    </div>
</template>

<script setup>
import { useMainStore } from '../store'

const store = useMainStore()

function onResetClicked() {
    store.resetChanges()
}
async function onSaveClicked() {
    await store.saveChanges()
}
</script>

<style scoped>
:deep(.btn-close) {
    display: none;
}</style>
