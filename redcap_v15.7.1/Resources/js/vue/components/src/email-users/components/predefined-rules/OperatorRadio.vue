<template>
    <!-- Wrap your radio inputs in a Bootstrap button group -->
    <div class="btn-group btn-group-sm mx-auto" role="group" aria-label="Operator toggle">
      <!-- Loop over your OPERATORS (key/value) -->
      <template v-for="(label, option) in OPERATORS" :key="option">
        <!-- Radio input (hidden by .btn-check in Bootstrap 5) -->
        <input
          type="radio"
          class="btn-check"
          :id="`${option}-${id}`"
          :name="`operator-${id}`"
          v-model="operator"
          :value="option"
          autocomplete="off"
          v-bind="{...$attrs}"
        />
  
        <!-- The corresponding label styled as a button -->
        <label
          class="btn btn-outline-primary"
          :class="{ active: operator === option }"
          :for="`${option}-${id}`"
        >
          {{ label }}
        </label>
      </template>
    </div>
  </template>

<script setup>
import { ref } from 'vue';
import { OPERATORS } from '../../models/query-builder';

const id = ref(crypto.randomUUID())
const operator = defineModel( {type: String, default: 'AND' })
</script>

<style scoped>

</style>