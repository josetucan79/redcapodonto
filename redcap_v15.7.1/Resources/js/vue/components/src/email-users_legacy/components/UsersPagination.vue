<template>
    <PageSelection
        :per-page="perPage"
        :total-items="totalFiltered"
        v-model="page"
        size="sm"
    />
</template>

<script setup>
import { computed } from 'vue'
import PageSelection from '../../shared/PageSelection.vue'
import { useUsersStore } from '../store'

const usersStore = useUsersStore()

const page = computed({
    get() {
        return usersStore.metadata.page
    },
    set(number) {
        usersStore.doAction('setPage', [number])
    },
})
const perPage = computed({
    get() {
        return usersStore.metadata.perPage
    },
    set(number) {
        usersStore.doAction('setPerPage', [number])
    },
})
const totalFiltered = computed({
    get() {
        return usersStore.metadata.totalFiltered
    },
    set(number) {},
})
</script>

<style scoped></style>
