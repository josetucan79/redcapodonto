<template>
    <div>
        <RewardButton :rewardOption="rewardOption" />
        <b-modal ref="panelModal">
            <RewardPanel />
        </b-modal>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import RewardButton from './RewardButton.vue'
import RewardPanel from './RewardPanel.vue'

const props = defineProps({
    rewardOption: { type: Object },
    record: { type: Object },
})

const panelModal = ref()

</script>

<style scoped></style>
