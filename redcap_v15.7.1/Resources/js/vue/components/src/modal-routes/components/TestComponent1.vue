<template>
    <div>test 1</div>
    <ModalDialog ref="modalRef">
        <span>ciao</span>
    </ModalDialog>
    <button @click="show">AShow</button>
</template>

<script setup>
import { ref } from 'vue'
import ModalDialog from '../../shared/ModalRouterView/ModalDialog.vue'
const modalRef = ref()

const show = () => {
    const modal = modalRef.value
    if (!modal) return
    modal.toggle()
}
</script>

<style scoped></style>
