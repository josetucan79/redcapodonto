<template>
  <div class="p-2 d-flex flex-column gap-2">
    <EmailPanel />
    <div class="d-flex gap-2 align-items-center justify-content-end">
      <SendButton />
    </div>
    <router-view></router-view>
  </div>
</template>

<script setup>
import EmailPanel from '../components/email/EmailPanel.vue';
import SendButton from '../components/email/SendButton.vue'

</script>

<style scoped>
</style>
