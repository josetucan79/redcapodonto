<template>
    <div class="input-group">
        <input
            :type="type"
            class="form-control form-control-sm"
            autocomplete="one-time-code"
            v-model="value"
            v-bind="{ ...$attrs }"
        />
        <button
            type="button"
            class="btn btn-sm btn-outline-secondary"
            @click="onToggleClicked"
        >{{ buttonText }}</button>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    showText: { type: String, default: 'show' },
    hideText: { type: String, default: 'hide' },
})
const emit = defineEmits(['update:modelValue'])

const value = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
})

const buttonText = computed(() => {
    if (type.value === 'password') return props.showText
    else return props.hideText
})

const type = ref('password')

function onToggleClicked() {
    if (type.value === 'password') {
        type.value = 'text'
    } else {
        type.value = 'password'
    }
}
</script>

<style scoped></style>
