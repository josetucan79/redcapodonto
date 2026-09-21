<template>
    <b-modal :visible="visible" backdrop="static" @onHidden="onHidden">
        <template #title>
            <span>Editing Option #{{ reward_option_id }}</span>
        </template>
        <RewardOptionForm
            v-model:product="product"
            v-model:value_amount="value_amount"
            v-model:eligibility_logic="eligibility_logic"
        >
        </RewardOptionForm>
        <template #footer>
            <button
                type="button"
                class="btn btn-sm btn-secondary"
                @click="goBack"
            >
                <i class="fas fa-times fa-fw"></i>
                <span>Cancel</span>
            </button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                @click="save"
                :disabled="loading"
            >
                <template v-if="loading">
                    <i class="fas fa-spinner fa-spin fa-fw"></i>
                </template>
                <template v-else>
                    <i class="fas fa-save fa-fw"></i>
                </template>
                <span>Save</span>
            </button>
        </template>
    </b-modal>
</template>

<script setup>
import { ref, computed, toRefs, watchEffect } from 'vue'
import { useProductsStore, useRewardOptionsStore } from '@/rewards/store'
import RewardOptionForm from './RewardOptionForm.vue'
import { useRewardOptionsService } from '@/rewards/services'

const props = defineProps({
    reward_option_id: { type: Number },
})

const { reward_option_id } = toRefs(props)

const visible = defineModel('visible', { default: false })
const modalRef = ref()

const rewardOptionsService = useRewardOptionsService()
const rewardOptionsStore = useRewardOptionsStore()
const productsStore = useProductsStore()

const product = ref()
const value_amount = ref()
const eligibility_logic = ref()

const loading = computed(() => rewardOptionsStore.loading)

/**
 * hydrate the data from the store
 */
function hydrateFormFromStores() {
    // find the product that matches the product_id in the reward
    const product_id = rewardOptionsStore.currentItem?.provider_product_id
    const found = productsStore.findItem(product_id)
    product.value = found
    value_amount.value = rewardOptionsStore.currentItem?.value_amount
    eligibility_logic.value = rewardOptionsStore.currentItem?.eligibility_logic
}

function reset() {
    product.value = null
    value_amount.value = null
    eligibility_logic.value = null
}

function goBack() {
    visible.value = false
}

async function save() {
    const response = await rewardOptionsService.update(
        reward_option_id.value,
        product.value,
        value_amount.value,
        eligibility_logic.value
    )
    goBack()
}

function onHidden() {
    visible.value = false
}

watchEffect(async () => {
    if (visible.value === true) hydrateFormFromStores()
})
</script>

<style scoped></style>
