<template>
    <div>
        <label class="m-0" for="show-suspended"
            ><tt-text tkey="email_users_111"
        /></label>
        <input
            class="ms-2"
            id="show-suspended"
            type="checkbox"
            :value="true"
            :checked="showSuspended"
            @click.prevent="onShowSuspendedClicked"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue'

import { useUsersStore } from '../store'
const usersStore = useUsersStore()

const showSuspended = computed(() => {
    return usersStore.metadata?.showSuspended ?? false
})

function onShowSuspendedClicked() {
    usersStore.doAction('toggleSuspended')
}
</script>

<style scoped></style>
