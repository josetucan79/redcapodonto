<template>
    <div class="d-flex align-items-center gap-2">
        <div data-actions :style="style" class="me-auto">
            <StatusIndicator
                :status="loading ? 'loading' : data?.status"
                @click="onStatusClicked"
            />
        </div>
        <ScheduleIndicator :order="activeOrder" />
        <template v-if="!data?.reward_option?.is_deleted">
            <div
                class="form-check form-switch"
                v-if="
                    !activeOrder?.scheduled_action && 
                    [
                        'eligible',
                        'reviewer:approved',
                        'buyer:approved',
                    ].includes(data?.status)
                "
            >
                <input
                    v-if="selectableStates.includes(data?.status)"
                    class="form-check-input"
                    type="checkbox"
                    v-model="selected"
                    :value="record.record_id"
                />
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, ref, toRefs } from 'vue'
import useReviewSelectionStore from '@/rewards/store/dynamic-review-selection'
import StatusIndicator from './StatusIndicator.vue'
import ScheduleIndicator from './ScheduleIndicator.vue'
import { ENABLED_STATUS_LIST } from '@/rewards/variables'
import useArmNum from '@/rewards/utils/useRouteArmParam'

const selectableStates = [
    'eligible',
    'reviewer:approved',
    'buyer:approved',
]

const arm_num = useArmNum()

const props = defineProps({
    record: { type: Object },
    reward_option: { type: Object },
})

const emit = defineEmits(['show-modal', 'cell-selected'])

const loading = ref(false)

const { record, reward_option } = toRefs(props)

const selectionStore = useReviewSelectionStore(
    arm_num.value,
    reward_option.value?.reward_option_id
)

function onStatusClicked() {
    if (!ENABLED_STATUS_LIST.includes(data.value?.status)) return
    emit('cell-selected', {
        record: record.value,
        reward_option: reward_option.value,
    })
}

const selected = computed({
    get: () => selectionStore.selected,
    set: (value) => (selectionStore.selected = value),
})

const style = computed(() => {
    return {
        cursor: ENABLED_STATUS_LIST.includes(data.value?.status) ? 'pointer' : 'not-allowed',
    }
})

/**
 * the data associated with a record
 * - status
 * - orders
 * - reward_option
 */
const data = computed(
    () => record.value?.reward_options[reward_option.value.reward_option_id]
)

const activeOrder = computed(() => data.value?.orders?.[0])
</script>

<style scoped>
:has(td) [data-actions] {
    display: flex;
}
</style>
