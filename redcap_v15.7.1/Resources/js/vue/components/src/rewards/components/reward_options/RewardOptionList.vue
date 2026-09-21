<template>
    <div class="reward-options">
        <TransitionGroup name="fade">
            <template
                v-for="(option, index) in rewardOptions"
                :key="`${index}-${option.reward_option_id}`"
            >
                <RewardOption
                    class="reward-option"
                    :data="option"
                    data-reward-option
                    @show-edit-modal="openEditModal"
                />
            </template>
            <div class="d-flex" key="approval-button">
                <AddRewardOptionButton>
                    <button
                        type="button"
                        @click="onCreateOptionClicked"
                        class="btn btn-sm btn-primary"
                        :disabled="Gate.denies('manage_reward_options')"
                    >
                        <i class="fas fa-plus fa-fw"></i>
                        <span>Add Option</span>
                    </button>
                </AddRewardOptionButton>
            </div>
        </TransitionGroup>
    </div>
    <template v-if="loading">
        <LoadingIndicator />
    </template>
    <Teleport to="body">
        <CreateRewardOptionModal v-model:visible="showCreationModal" />
        <EditRewardOptionModal
            v-model:visible="showEditModal"
            :reward_option_id="editData?.reward_option_id"
        />
    </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, reactive, toRefs } from 'vue'
import { useRewardOptionsStore } from '@/rewards/store'
import { useRewardOptionsService } from '@/rewards/services'
import Gate from '@/rewards/utils/Gate'
import RewardOption from './RewardOption.vue'
import AddRewardOptionButton from './AddRewardOptionButton.vue'
import LoadingIndicator from '@/rewards/components/common/LoadingIndicator.vue'
import CreateRewardOptionModal from './CreateRewardOptionModal.vue'
import EditRewardOptionModal from './EditRewardOptionModal.vue'

const store = useRewardOptionsStore()
const rewardOptionsService = useRewardOptionsService()

const { loading } = toRefs(store)
const showCreationModal = ref(false)
const showEditModal = ref(false)
const editData = reactive({})

const rewardOptions = computed(() => store.list)

function onCreateOptionClicked() {
    showCreationModal.value = true
}

function openEditModal(data) {
    const rewardOption = data
    rewardOptionsService.edit(rewardOption)
    rewardOptionsService.prepareEditData(rewardOption?.reward_option_id)
    Object.assign(editData, rewardOption)
    showEditModal.value = true
}

onMounted(() => store.fetchList())
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
:has(> .reward-option) {
}

.reward-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: stretch;
}
.reward-option {
    min-width: 10rem;
    max-width: calc(100%/3);
}
.reward-option :deep(.card) {
    height: 100%;
}
</style>
