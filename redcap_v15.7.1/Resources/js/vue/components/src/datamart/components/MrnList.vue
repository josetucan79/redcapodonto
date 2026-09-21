<template>
    <div class="mrn-list border p-2 rounded">
        <details>
            <summary>
                <span>Total MRNs: </span>
                <span>{{ totalMRNs }}</span>
            </summary>
            <ul>
                <template v-for="(mrn, index) in mrns" :key="`${index}-${mrn}`">
                    <li>{{ mrn }}</li>
                </template>
            </ul>
        </details>
        <div class="d-flex d-none">
            <div class="ms-auto text-muted">
                <span>Total MRNs: </span>
                <span>{{ totalMRNs }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    mrns: { type: Array, default: () => [] },
})

const totalMRNs = computed(() => props?.mrns?.length ?? 0)
</script>

<style scoped>
.mrn-list > details {
    max-height: 300px;
    overflow-y: auto;
}
</style>
