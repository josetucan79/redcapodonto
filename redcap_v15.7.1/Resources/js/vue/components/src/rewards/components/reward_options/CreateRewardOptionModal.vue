<template>
    <b-modal :visible="visible" ref="modalRef" backdrop="static" @onHidden="onHidden">
        <template #title>
            <span>Create Option</span>
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
                @click="close"
            >
                <i class="fas fa-times fa-fw"></i>
                <span>Cancel</span>
            </button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                @click="save"
                :disabled="loading || !canSave"
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
import { ref, isRef, computed, watchEffect } from 'vue'
import { useRewardOptionsService } from '@/rewards/services'
import { useRewardOptionsStore } from '@/rewards/store'
import RewardOptionForm from './RewardOptionForm.vue'

const rewardOptionsStore = useRewardOptionsStore()
const rewardOptionsService = useRewardOptionsService()

const visible = defineModel('visible', { default: false })

const modalRef = ref()

const product = ref()
const value_amount = ref()
const eligibility_logic = ref()

function isEmptyRef(refVar) {
    // Check if the refVar is a Vue ref
    if (isRef(refVar)) {
        // Get the value of the ref
        const value = refVar.value
        // Check if the value is undefined, null, or an empty string
        return value === undefined || value === null || value === ''
    } else {
        console.error('Provided variable is not a Vue ref.')
        return false
    }
}

const canSave = computed(() => {
    return (
        !isEmptyRef(product) &&
        !isEmptyRef(value_amount) &&
        !isEmptyRef(eligibility_logic)
    )
})

const loading = computed(() => rewardOptionsStore.loading)

function close() {
    visible.value = false

}

async function save() {
    const response = await rewardOptionsService.create(
        product.value,
        value_amount.value,
        eligibility_logic.value
    )
    close()
}

const reset = () => {
    product.value = undefined
    value_amount.value = undefined
    eligibility_logic.value = undefined
}

function onHidden() {
    visible.value = false
}


watchEffect(async () => {
    if (visible.value === true) reset()
})
</script>

<style scoped></style>
