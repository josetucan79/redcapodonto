<template>
    <div>
        <textarea class="form-control" rows="5" v-model="mrns"></textarea>
        <div class="d-flex">
            <div class="ms-auto text-muted">
                <span>Total MRNs: </span>
                <span>{{ totalMRNs }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { debounce } from '../../utils'
import { useRevisionEditorStore } from '../store'

const revisionEditorStore = useRevisionEditorStore()

const textToArray = (text) => {
    return text.split('\n').filter((line) => line.trim() !== '')
}

const debounceMrns = debounce((text) => {
    mrnsText.value = text
    revisionEditorStore.mrns = textToArray(text)
}, 300)

const mrnsText = ref('')
const mrns = computed({
    get: () => mrnsText.value,
    set: (value) => debounceMrns(value),
})
const totalMRNs = computed(() => revisionEditorStore.mrns.length)
</script>

<style scoped></style>
