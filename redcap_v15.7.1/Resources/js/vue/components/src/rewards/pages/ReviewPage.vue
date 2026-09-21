<template>
    <div>
        <router-view></router-view>
    </div>
</template>

<script setup>
import { computed, toRefs, watch, watchEffect } from 'vue'
import { useRewardOptionsStore } from '@/rewards/store'
import { useRouter, useRoute } from 'vue-router'
import { useRecordsStore } from '@/rewards/store'

const router = useRouter()
const route = useRoute()
const recordsStore = useRecordsStore()

const rewardOptionsStore = useRewardOptionsStore()

const reward_options = computed(() => rewardOptionsStore.list)

const { loading: optionsLoading } = toRefs(rewardOptionsStore)
const { pagination, loading } = toRefs(recordsStore)

watchEffect(() => {
    if (optionsLoading.value || reward_options.value?.length > 0) return
    router.push({ name: 'reward-options' })
})

// watch if arm is changed
watch(
    () => route.params.arm_num,
    async (newValue, oldvalue) => {
        await recordsStore.updatePaginationSilently('page', 1)
        await recordsStore.loadRecords()
    }
)

// Watch `pagination.page` and `pagination.perPage`
// watch([() => pagination.value?.page, () => pagination.value?.perPage], () => {
//     if (loading.value) return // Prevent double-fetch during loading
//     if (recordsStore.disablePaginationWatcher) return // Prevent fetch if watchers are disabled
//     recordsStore.loadRecords()
// })
</script>

<style scoped></style>
