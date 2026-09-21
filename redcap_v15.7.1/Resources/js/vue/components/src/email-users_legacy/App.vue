<template>
    <div v-if="loading" class="p-2">
        <i class="fa fa-spinner fa-spin" />
        <span class="ms-2">Loading {{ loadingText }}</span>
    </div>

    <div v-else>
        <ComposeEmailPanel />
        <div class="mt-2">
            <UsersPanel />
        </div>
    </div>
</template>

<script setup>
import { onBeforeMount, ref } from 'vue'
import API from './API'
import { useSettingsStore, useUsersStore } from './store'
import ComposeEmailPanel from './components/ComposeEmailPanel.vue'

import UsersPanel from './components/UsersPanel.vue'

const settingsStore = useSettingsStore()
const usersStore = useUsersStore()

const loading = ref(false)
const loadingText = ref('')

async function getAllUsers() {
    // get some metadata first getting 0 results
    const metadataResponse = await API.getUsers(0, 0)
    let { count = 0 } = metadataResponse?.metadata ?? {}

    let page = 1
    let perPage = 1000
    let totalPages = Math.ceil(count / perPage)
    const users = {
        data: [],
    }
    while (page != null) {
        loadingText.value = `users (${page}/${totalPages})`
        const usersResponse = await API.getUsers(page, perPage)
        const { data, metadata } = usersResponse
        users.data = [...users.data, ...data]
        const { next_page } = metadata
        page = next_page
    }
    return users
}

async function load() {
    try {
        loading.value = true

        loadingText.value = 'settings'
        const settingsResponse = await API.getSettings()
        const settings = settingsResponse
        await settingsStore.loadData(settings)
        const users = await getAllUsers()
        await usersStore.loadData(users)
    } catch (error) {
        console.log(error)
    } finally {
        loading.value = false
    }
}

onBeforeMount(() => {
    load()
})
</script>

<style scoped></style>
