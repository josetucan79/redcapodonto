<template>
    <b-modal ref="modalRef">
        <template #header>
            <slot name="header"></slot>
        </template>
        <slot></slot>
        <template #footer>
            <slot name="footer"></slot>
        </template>
    </b-modal>
</template>

<script setup>
import { ref, watchEffect } from 'vue'

const visible = defineModel('visible', { default: false })

const modalRef = ref()

watchEffect(async () => {
    if (!modalRef.value) return
    if (visible.value === true) {
        await modalRef.value.show()
        visible.value = false
    }
})
</script>

<style scoped></style>
