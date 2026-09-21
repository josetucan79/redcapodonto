<template>
  <div class="demo-container">
    <textarea ref="text-area"></textarea>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, useTemplateRef } from "vue";

import { insertText, saveCaretPosition } from '@/libs.js';

const textAreaRef = useTemplateRef('text-area')


onMounted(() => {
  // Get your textarea
  const textarea = textAreaRef.value;

  console.log(textarea)
  textarea.focus()
  
  // Insert text at current cursor position
  insertText(textarea, 'Text to insert');
  
  // Or save position for later use
  const lastPosition = saveCaretPosition(textarea);
  console.log(lastPosition)
  // Then insert at that saved position
  insertText(textarea, 'More text', lastPosition);
})

onUnmounted(() => {

});
</script>

<style scoped></style>
