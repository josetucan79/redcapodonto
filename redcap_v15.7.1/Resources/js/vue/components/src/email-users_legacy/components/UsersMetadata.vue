<template>
    <div
        class="d-flex justify-content-start align-items-center small fst-italic"
    >
        <span
            ><tt-text tkey="email_users_110" /> (<tt-text
                tkey="email_users_133"
            />):
            <span class="font-weigth-bold"
                >{{ metadata.selectedTotal }}/{{ metadata.total }}</span
            ></span
        >
        <template v-if="metadata.isFilterActive">
            <div class="vr mx-2"></div>
            <span
                ><tt-text tkey="email_users_110" /> (<tt-text
                    tkey="email_users_134"
                />):
                <span class="font-weigth-bold"
                    >{{ metadata.filteredTotal }}/{{
                        metadata.totalFiltered
                    }}</span
                ></span
            >
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useUsersStore } from '../store'

const usersStore = useUsersStore()

const metadata = computed(() => {
    return usersStore.metadata
})
</script>

<style scoped></style>
