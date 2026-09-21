<template>
    <div>
        <div class="card">
            <div class="card-header">
                <div
                    class="d-flex justify-content-start align-items-center"
                >
                    <span>R-{{ data?.reward_option_id }}</span>
                    <button
                        type="button"
                        class="btn btn-xs btn-outline-primary ms-auto"
                        @click="onCopyOptionIdClicked"
                    >
                        <i class="fas fa-copy fa-fw"></i>
                    </button>
                </div>
                <template v-if="data?.is_deleted">
                    <TextRibbon text="DELETED" />
                </template>
                <template v-else-if="data?.is_valid !== true">
                    <TextRibbon
                        text="INVALID"
                        v-if="!rewardOption?.is_valid"
                        backgroundColor="#ffc107"
                        textColor="#333"
                    />
                </template>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column align-items-center">
                    <!-- <div class="product-image">
                        <ProductImage :product-id="data.provider_product_id" size="1"/>
                    </div> -->
                    <ValueAmount :value="data.value_amount" />
                    <span class="fs-6">{{ data.description }}</span>
                    <span class="small text-muted">{{
                        data.provider_product_id
                    }}</span>
                    <!-- <h6 class="card-subtitle mb-2 text-muted">{{data.eligibility_logic}}</h6> -->
                    
                    <!-- <a href="#" class="btn btn-primary">Go somewhere</a> -->
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-center gap-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary"
                        @click="onShowLogicClicked"
                    >
                        <i
                            class="fas fa-square-root-variable fa-fw me-1 text-primary"
                        ></i>
                        <span></span>
                    </button>
                    <template v-if="data?.is_deleted">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary text-success"
                            @click="onRestoreClicked"
                        >
                            <i
                                class="fas fa-refresh fa-fw me-1 text-success"
                            ></i>
                            <span>Restore</span>
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary text-danger"
                            @click="onForceDeleteClicked"
                            :disabled="Gate.denies('manage_reward_options')"
                        >
                            <i class="fas fa-trash fa-fw me-1 text-danger"></i>
                            <span>Force Delete</span>
                        </button>
                    </template>
                    <template v-else>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary text-secondary"
                            @click="onEditClicked"
                            :disabled="Gate.denies('manage_reward_options') || loading"
                        >
                            <i
                                class="fas fa-pencil fa-fw me-1 text-primary"
                            ></i>
                            <span>Edit</span>
                        </button>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary text-danger"
                            @click="onDeleteClicked"
                            :disabled="Gate.denies('manage_reward_options')"
                        >
                            <i class="fas fa-trash fa-fw me-1 text-danger"></i>
                            <span>Delete</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, toRefs } from 'vue'
import Gate from '@/rewards/utils/Gate'
import ValueAmount from '@/rewards/components/common/ValueAmount.vue'
import TextRibbon from '@/rewards/components/common/TextRibbon.vue'
import ProductImage from '@/rewards/components/common/ProductImage.vue'
import { useRewardOptionsService } from '@/rewards/services'
import { useProductsStore, useRewardOptionsStore } from '@/rewards/store'
import { useClipboard } from '@/utils/use'
import { useToaster } from 'bootstrap-vue'

const rewardOptionsService = useRewardOptionsService()
const productsStore = useProductsStore()
const rewardOptionsStore = useRewardOptionsStore()
const clipboard = useClipboard()
const toaster = useToaster()

const props = defineProps({
    data: { type: Object, default: null },
})
const { loading } = toRefs(rewardOptionsStore)

const emit = defineEmits(['show-edit-modal'])

const { data } = toRefs(props)

const image = computed(() => {
    const details = productsStore.findItem(data.value.provider_product_id)
    if (!details) return false
    return Object.values(details?.imageUrls ?? {}).at(1)
})

async function onDeleteClicked() {
    rewardOptionsService.delete(data.value.reward_option_id)
}
async function onRestoreClicked() {
    rewardOptionsService.restore(data.value.reward_option_id)
}

function onEditClicked() {
    emit('show-edit-modal', data.value)
}

function onForceDeleteClicked() {
    rewardOptionsService.forceDelete(data.value.reward_option_id)
}

function onShowLogicClicked() {
    rewardOptionsService.showLogic(data.value)
}
async function onCopyOptionIdClicked() {
    const id = data.value?.reward_option_id
    if(!id) {
        toaster.toast({ title: 'Error', body: 'Could not copy the Option ID to clipboard' })
        return
    }
    const fullID = `R-${id}`
    await clipboard.copy(fullID)
    toaster.toast({ title: 'Success', body: `Reward Option ID '${fullID}' copied to clipboard` })
}
</script>

<style scoped>
.value-amount {
    color: #dad4d4;
    text-shadow: rgba(255, 255, 255, 0.1) -1px -1px 1px,
        rgba(0, 0, 0, 0.5) 1px 1px 1px;
    font-weight: 900;
}
.card-body {
    position: relative;
    z-index: 1;
    overflow: hidden;
    z-index: 1;
}
/* .product-image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
}
.product-image :deep(img) {
    object-fit: cover;
    height: 100%;
    width: 100%;
    filter: blur(8px);
    opacity: .5;
    transition: filter 300ms ease-in-out, opacity 300ms ease-in-out;
} */
.card-body:hover .product-image :deep(img) {
    filter: blur(3px);
    opacity: 1;
}
</style>
