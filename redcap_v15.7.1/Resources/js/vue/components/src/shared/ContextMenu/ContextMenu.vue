<template>
  <div
    v-if="visible"
    ref="menu"
    class="context-menu"
    :style="{ top: y + 'px', left: x + 'px' }"
    @click.stop
  >
    <slot />
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, defineExpose } from 'vue';

const emit = defineEmits(['show','hide'])

const visible = ref(false);
const x = ref(0);
const y = ref(0);
const menu = ref(null);

function open(xPos, yPos) {
  x.value = xPos;
  y.value = yPos;
  visible.value = true;
  emit('show')
}

function close() {
  visible.value = false;
  emit('hide')
}

function handleClickOutside(event) {
  if (menu.value && !menu.value.contains(event.target)) {
    close();
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});

defineExpose({ open, close });
</script>

<style scoped>
.context-menu {
  position: fixed;
  background-color: white;
  border: 1px solid #ccc;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
  z-index: 9999;
  border-radius: 6px;
  padding: 0;
  min-width: 120px;
}
</style>