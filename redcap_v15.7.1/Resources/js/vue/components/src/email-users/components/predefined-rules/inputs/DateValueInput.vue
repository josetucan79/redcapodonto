<template>
  <input class="form-control form-control-sm"  type="date" v-model="realValue" />
</template>

<script setup>
import { computed } from 'vue'
const values = defineModel({ type: Array })
const props = defineProps({
    config: { type: Object }
})

const realValue = computed({
    get: () => {
      let date = values.value.at(0)
      return date
    },
    set: (_value) => values.value = [_value]
})


</script>
