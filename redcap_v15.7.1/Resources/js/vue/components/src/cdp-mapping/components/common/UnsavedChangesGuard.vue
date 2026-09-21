<template>
    <b-modal ref="confirmDialogRef">
        <template #title>
            <span v-tt:unsaved_changes_warning_title />
        </template>
        <div>
            <span v-tt:unsaved_changes_warning_description />
        </div>
        <template #footer>
            <div class="d-flex justify-content-end gap-2">
                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    @click="onSaveClicked"
                    :disabled="loading"
                >
                    <i v-if="loading" class="fas fa-spinner fa-spin fa-fw me-1"></i>
                    <i v-else class="fas fa-save fa-fw me-2"></i>
                    <span v-tt:unsaved_changes_warning_save_button></span>
                </button>
                <button
                    type="button"
                    class="btn btn-danger btn-sm"
                    @click="onDiscardClicked"
                    :disabled="loading"
                >
                    <i class="fas fa-trash fa-fw me-2"></i>
                    <span v-tt:unsaved_changes_warning_discard_button></span>
                </button>
                <button
                    type="button"
                    class="btn btn-secondary btn-sm"
                    @click="onCancelClicked"
                    :disabled="loading"
                >
                    <i class="fas fa-times-circle fa-fw me-2"></i>
                    <span v-tt:unsaved_changes_warning_cancel_button></span>
                </button>
            </div>
        </template>
    </b-modal>
</template>

<script setup>
import { ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

const props = defineProps({
    checkFn: { type: Function },
    saveFn: { type: Function },
    discardFn: { type: Function },
})
const loading = ref(false)
const confirmDialogRef = ref()

onBeforeRouteLeave(async (from, to) => {
    if (!props.checkFn()) return true
    const dialogBox = confirmDialogRef.value
    const confirmed = await dialogBox.show()
    return confirmed
})

async function onSaveClicked() {
    try {
        loading.value = true
        await props.saveFn()
        const dialogBox = confirmDialogRef.value
        return dialogBox.hide(true)
    } finally {
        loading.value = false
    }
}
async function onDiscardClicked() {
    await props.discardFn()
    const dialogBox = confirmDialogRef.value
    return dialogBox.hide(true)
}
function onCancelClicked() {
    const dialogBox = confirmDialogRef.value
    return dialogBox.hide(false)
}
</script>

<style scoped></style>
