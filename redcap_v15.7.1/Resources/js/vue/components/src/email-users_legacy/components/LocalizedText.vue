<template>
    <span v-html="translation"></span>
</template>

<script setup>
import { computed } from 'vue'
import { useSettingsStore } from '../store'

const settingsStore = useSettingsStore()

const props = defineProps({
    tkey: {
        type: String,
        default: '',
    },
})

const translation = computed(() => {
    const translation = settingsStore.translate(props.tkey)
    if (!translation) return '-- no translation found --'
    return translation
})
</script>

<style scoped></style>
