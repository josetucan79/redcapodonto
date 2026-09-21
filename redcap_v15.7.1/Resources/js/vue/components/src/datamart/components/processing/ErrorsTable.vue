<template>
    <table class="my-2 table table-hover table-striped table-bordered">
        <thead>
            <tr>
                <th>MRN</th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody>
            <template
                v-for="(item, index) in items"
                :key="`${item.mrn}-error-${index}`"
            >
                <tr>
                    <td>{{ item.mrn }}</td>
                    <td>{{ item.error.message }}</td>
                </tr>
            </template>
            <tr v-if="noErrors">
                <td colspan="2" class="fst-italic">No errors</td>
            </tr>
        </tbody>
    </table>
    <b-pagination
        v-model="page"
        :totalItems="errors.length"
        :perPage="limit"
        size="sm"
    ></b-pagination>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    errors: { type: Array, default: () => [] },
})
const page = ref(1)
const limit = ref(10)
const items = computed(() => {
    const start = (page.value - 1) * limit.value
    const end = start + limit.value
    return props.errors.slice(start, end)
})
const noErrors = computed(() => props.errors?.length === 0)
</script>

<style scoped></style>
