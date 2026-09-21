<template>
    <div class="border rounded p-2 my-2 d-flex gap-2 align-items-end">
      <div>
        <label class="form-label" for="field">Field:</label>
        <input class="form-control form-control-sm" type="text" id="field" v-model="rule.field">
      </div>
      <div>
        <label class="form-label" for="condition">Condition:</label>
        <ConditionSelect v-model="rule.condition" />
      </div>
      <div>
        <label class="form-label" for="value">Value:</label>
        <input class="form-control form-control-sm" type="text" id="value" v-model="rule.value">
      </div>
      <div>
        <button class="btn btn-sm btn-danger" type="button" @click="onRemoveRule">
          <i class="fas fa-trash fa-fw"></i>
        </button>
      </div>
    </div>
</template>

<script setup>
import { inject } from 'vue';
import ConditionSelect from './ConditionSelect.vue'

const rule = defineModel({ type: Object })

// Inject the global QueryBuilder instance.
const qb = inject('queryBuilder');

const onRemoveRule = () => {
  qb.removeNode(rule.value);
};
</script>

<style scoped>
label {
  margin: 0;
  font-weight: 400;
}
</style>
