<template>
    <input
        type="text"
        class="form-control form-control-sm"
        v-model="text"
        @blur="onSubjectBlur"
        required
    />
</template>

<script setup>
import { ref } from 'vue'
import { insertTextAtPosition } from '@/utils/insertText'


const text = defineModel({ type: String })
const lastCursorPosition = ref(0)
function onSubjectBlur(e) {
    lastCursorPosition.value = e.target?.selectionStart ?? 0
}

function insertDynamicVariable(variable) {
    text.value = insertTextAtPosition(text.value, `[${variable}]`, lastCursorPosition.value)
}

defineExpose({insertDynamicVariable})
</script>

<style lang="scss" scoped>

</style>