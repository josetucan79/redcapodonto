<template>
    <div>
        <div data-progress-bar-container @click="onClick">
            <ProgressStep ref="reviewStep" :active="state.reviewStep.active" :status="state.reviewStep.status" >
                <template #circle>
                    <i class="fas fa-clipboard fa-fw"></i>
                </template>
                <span>Eligibility Review</span>
            </ProgressStep>
            <ProgressStep ref="financeStep" :active="state.financeStep.active" :status="state.financeStep.status" >
                <template #circle>
                    <i class="fas fa-shopping-cart fa-fw"></i>
                </template>
                <span>Financial Authorization</span>
            </ProgressStep>
            <ProgressStep ref="deliveryStep" :active="state.deliveryStep.active" :status="state.deliveryStep.status" >
                <template #circle>
                    <i class="fas fa-gift fa-fw"></i>
                </template>
                <span>Compensation Delivery</span>
            </ProgressStep>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, watch, watchEffect } from 'vue'
import ProgressStep from './ProgressStep.vue'
import { PROGRESS_STATUS as STATUS, ORDER_STATUS } from '../../variables'

const props = defineProps({
    status: { type: String, default: null },
})

const state = reactive({
    reviewStep: { active: false, status: STATUS.PENDING, completed: false, rejected: false },
    financeStep: { active: false, status: STATUS.PENDING, completed: false, rejected: false },
    deliveryStep: { active: false, status: STATUS.PENDING, completed: false, rejected: false },
})

const reviewStep = ref()
const financeStep = ref()
const deliveryStep = ref()

const emit = defineEmits(['go-to-step'])

watch(
    () => props.status,
    (_status) => {
        switch (_status) {
            case ORDER_STATUS.PENDING:
            case ORDER_STATUS.ELIGIBLE:
            case ORDER_STATUS.BUYER_REJECTED:
                Object.assign(state.reviewStep, { status: STATUS.PENDING, active: true})
                Object.assign(state.financeStep, { status: STATUS.PENDING, active: false })
                Object.assign(state.deliveryStep, { status: STATUS.PENDING, active: false })
                break
            case ORDER_STATUS.REVIEWER_REJECTED:
                Object.assign(state.reviewStep, { status: STATUS.REJECTED, active: false })
                Object.assign(state.financeStep, { status: STATUS.PENDING, active: false })
                Object.assign(state.deliveryStep, { status: STATUS.PENDING, active: false })
                break
            case ORDER_STATUS.REVIEWER_APPROVED:
                Object.assign(state.reviewStep, { status: STATUS.COMPLETED, active: false })
                Object.assign(state.financeStep, { status: STATUS.PENDING, active: true })
                Object.assign(state.deliveryStep, { status: STATUS.PENDING, active: false })
                break
            case ORDER_STATUS.BUYER_APPROVED:
                Object.assign(state.reviewStep, { status: STATUS.COMPLETED, active: false })
                Object.assign(state.financeStep, { status: STATUS.COMPLETED, active: false })
                Object.assign(state.deliveryStep, { status: STATUS.PENDING, active: false })
                break
            case ORDER_STATUS.ORDER_PLACED:
                Object.assign(state.reviewStep, { status: STATUS.COMPLETED, active: false })
                Object.assign(state.financeStep, { status: STATUS.COMPLETED, active: false })
                Object.assign(state.deliveryStep, { status: STATUS.PENDING, active: false })
                break
            case ORDER_STATUS.COMPLETED:
                Object.assign(state.reviewStep, { status: STATUS.COMPLETED, active: false })
                Object.assign(state.financeStep, { status: STATUS.COMPLETED, active: false })
                Object.assign(state.deliveryStep, { status: STATUS.COMPLETED, active: true })
                break

            default:
                Object.assign(state.reviewStep, { status: STATUS.PENDING, active: false})
                Object.assign(state.financeStep, { status: STATUS.PENDING, active: false })
                Object.assign(state.deliveryStep, { status: STATUS.PENDING, active: false })
                break
        }
    },
    {
        immediate: true,
    }
)
</script>

<style scoped>
[data-progress-bar-container] {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    --completed-color: #4fc72b;
    --rejected-color: #da3232;
    --active-color: #4070f4;
    --inactive-color: #e0e0e0;
    --circle-size: 35px;
}
</style>
