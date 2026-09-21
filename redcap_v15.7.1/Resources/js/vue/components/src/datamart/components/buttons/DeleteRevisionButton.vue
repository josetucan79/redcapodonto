<template>
    <button class="btn btn-sm btn-danger" @click="onDeleteRevisionClicked">
        <i class="fas fa-trash fa-fw"></i>
        <span>Delete revision</span>
    </button>
    <b-modal ref="dialogRef">
        <template #header>Confirm delete</template>
        <span>Are you sure you want to delete this item?</span>
    </b-modal>
</template>

<script setup>
import { ref } from 'vue'
import { deleteRevision } from '../../API'
import { useAppStore } from '../../store'
import { useToaster } from 'bootstrap-vue'

const appStore = useAppStore()
const toaster = useToaster()

const props = defineProps({
    revision: { type: Object, default: () => ({}) },
})

const dialogRef = ref()

async function onDeleteRevisionClicked() {
    const confirmed = await dialogRef.value.show()
    if (!confirmed) return
    try {
        const id = props?.revision?.metadata?.id
        const response = await deleteRevision(id)
        toaster.toast({
            title: 'Success',
            body: 'the revision was deleted successfully',
        })
    } catch (error) {
        toaster.toast({
            title: 'Success',
            body: 'there was an error deleting the revision - '.error,
        })
    } finally {
        appStore.init()
    }
}
</script>

<style scoped></style>