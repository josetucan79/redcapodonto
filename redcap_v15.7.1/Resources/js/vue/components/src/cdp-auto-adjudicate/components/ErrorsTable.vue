<template>
    <div>
        <PageSelection v-model="page" :per-page="perPage" :totalItems="errors.length" size="sm"/>
        <table class="table table-sm table-striped table-hover table-bordered my-2">
            <template v-if="errors.length === 0">
                <tbody>
                    <tr><td class="fst-italic">No errors</td></tr>
                </tbody>
            </template>
            <template v-else>
                <thead>
                    <tr class="text-nowrap">
                        <th>Record ID</th>
                        <th>Event ID</th>
                        <th>Field Name</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(error, index) in paginatedErrors" :key="`${index}-${error?.label}`">
                        <tr>
                            <td>{{ error.field.record }}</td>
                            <td>{{ error.field.event_id }}</td>
                            <td>{{ error.field.field_name }}</td>
                            <td>{{ error.error }}</td>
                        </tr>
                    </template>
                </tbody>
            </template>
        </table>
        <div class="d-flex">
            <PageSelection v-model="page" :per-page="perPage" :totalItems="errors.length" size="sm"/>
            <slot name="footer"></slot>
        </div>
    </div>
</template>

<script setup>
import { computed, inject, ref } from 'vue'
import PageSelection from '@/shared/PageSelection.vue'

const adjudicationStore = inject('adjudication-store')

const perPage = 5
const page = ref(1)

const errors = computed(() => adjudicationStore.errors ?? [])
const paginatedErrors = computed(() => {
    const start = (page.value - 1) * perPage
    const end = start + perPage
    const items = [...errors.value].slice(start, end)
    return items
})
</script>

<style scoped></style>
