<template>
  <input class="form-control form-control-sm" type="number" v-model="realValue" />
</template>

<script setup>
import { computed, watchEffect } from 'vue'
const value = defineModel({ })

const realValue = computed({
    get: () => {
      let number = Number(value.value)
      if(isNaN(number)) number = 0
      return number
    },
    set: (_value) => value.value = isNaN(_value) ? 0 : _value
})

watchEffect(() => {
  if(!isNaN(value.value)) return
  value.value = 0
})
</script>
