<template>
  <div class="d-flex gap-2">
    <input class="form-control form-control-sm"  type="date" v-model="from" />
    <input class="form-control form-control-sm"  type="date" v-model="to" />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
const values = defineModel({ type: Array })
const props = defineProps({
    config: { type: Object }
})

const from = ref()
const to = ref()

// Whenever `value` changes from the outside, split it by comma
// and update `from` and `to`.
watch(values, (newVal) => {
  const [first, second] = newVal
  from.value = first || ''
  to.value = second || ''
}, { immediate: true })

// Whenever `from` or `to` changes, update `value`.
watch([from, to], () => {
  // If either is empty, it will still work (e.g. "2023-01-01,")
  let dates = []
  if(from.value) dates.push(from.value)
  if(to.value) dates.push(to.value)
  values.value = dates
})

</script>
