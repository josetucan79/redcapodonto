<template>
    <div>
        <template v-if="loading">
            <div class="p-2">
                <i class="fas fa-spinner fa-spin fa-fw"></i>
                <span class="ms-1">Loading</span>
            </div>
        </template>
        <template v-else>
            <router-view />

        </template>
        <Teleport to="body">
            <ErrorsVisualizer />
        </Teleport>
    </div>
</template>

<script setup>
import { onMounted, ref, toRefs } from 'vue';
import { useAppStore, useEmailStore, useQueriesStore, useMessagesStore } from './store';
import ErrorsVisualizer from '../utils/store/plugins/ErrorsVisualizer.vue';

const appStore = useAppStore()
const emailStore = useEmailStore()
const queriesStore = useQueriesStore()
const messagesStore = useMessagesStore()

const loading = ref(false)
// const { loading } = toRefs(appStore)
const { user } = toRefs(appStore)

onMounted(async () => {
    loading.value = true
    await appStore.init()
    // extract the user and set the from to its first email
    emailStore.from = user.value?.emails?.at(0) ?? ''
    await queriesStore.load()
    await messagesStore.load()
    loading.value = false
})
</script>

<style scoped>
:deep(label) {
    font-weight: bold;
}

</style>