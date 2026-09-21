<template>
    <div>
        <template v-if="loading">
            <LoadingIndicator />
        </template>
        <template v-else>
            <div class="d-flex align-items-center mb-2">
                <span class="me-2">Current Arm: </span>
                <ArmSelect v-model="arm" />
            </div>
            <ReviewToolbar class="mb-2" />
            <template v-if="records.length === 0">
                <span class="fst-italic">No items</span>
            </template>
            <template v-else>
                <ReviewTable
                    v-model:records="records"
                    v-model:rewardOptions="reward_options"
                />
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import {
    useRecordsStore,
    useRewardOptionsStore,
} from '@/rewards/store'
import ReviewTable from './ReviewTable.vue'
import ReviewToolbar from './ReviewToolbar.vue'
import LoadingIndicator from '@/rewards/components/common/LoadingIndicator.vue'
import { useRoute, useRouter } from 'vue-router'
import ArmSelect from '@/rewards/components/common/ArmSelect.vue'

const route = useRoute()
const router = useRouter()

const arm = computed({
    get: () => route.params?.arm_num ?? 1,
    set: (value) =>
        router.push({ name: `arm-review`, params: { arm_num: value } }),
})

const recordsStore = useRecordsStore()
const rewardOptionsStore = useRewardOptionsStore()

const loading = computed(
    () => recordsStore.loading && rewardOptionsStore.loading
)

const records = computed(() => recordsStore.list)
const reward_options = computed(() => rewardOptionsStore.list)

function loadData() {
    recordsStore.loadRecords()
    // rewardOptionsStore.fetchList()
}

onMounted(() => loadData())
</script>

<style scoped></style>
